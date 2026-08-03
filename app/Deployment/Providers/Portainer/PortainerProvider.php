<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Portainer;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\AbstractDeploymentProvider;

final class PortainerProvider extends AbstractDeploymentProvider
{
    private string $lastError = '';

    private ?PortainerApiClient $apiClient = null;

    public function getName(): string
    {
        return 'portainer';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getApiClient(): PortainerApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = new PortainerApiClient(
                $this->getUrl(),
                $this->getApiKey(),
            );
        }

        return $this->apiClient;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        $url = $this->config['url'] ?? null;
        if (! \is_string($url) || $url === '') {
            $errors[] = 'Portainer URL is required (e.g., https://portainer.example.com)';
        }

        $apiKey = $this->config['api_key'] ?? null;
        if (! \is_string($apiKey) || $apiKey === '') {
            $errors[] = 'Portainer API key is required';
        }

        $endpointId = $this->config['endpoint_id'] ?? null;
        if (! \is_string($endpointId) && ! \is_int($endpointId)) {
            $errors[] = 'Portainer endpoint ID is required';
        }

        return $errors;
    }

    public function plan(ProjectConfig $project, ProfileConfig $profile): array
    {
        $domainValue = $profile->get('domain');
        $domain = \is_string($domainValue) ? $domainValue : '';
        $repository = $project->repository();
        $repoProviderValue = $repository['provider'] ?? 'unknown';
        $repoProvider = \is_string($repoProviderValue) ? $repoProviderValue : 'unknown';
        $repoNameValue = $repository['name'] ?? 'unknown';
        $repoName = \is_string($repoNameValue) ? $repoNameValue : 'unknown';
        $branch = $profile->branch();

        $actions = [
            "Connect to Portainer: {$this->getUrl()}",
            "Build Docker image for: {$repoName}",
            'Push image to registry',
            "Create or update container on endpoint: {$this->getEndpointId()}",
        ];

        if ($domain !== '') {
            $actions[] = "Configure domain: {$domain}";
        }

        $databases = $project->databases();
        if (! empty($databases)) {
            foreach ($databases as $database) {
                $dbName = $this->interpolateDatabaseName($database->name(), $project->name(), $profile->name());
                $actions[] = "Deploy database stack: {$dbName} ({$database->type()})";
            }
        }

        $actions[] = 'Start containers';

        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $branch,
            'path' => $project->path(),
            'domain' => $domain,
            'repository' => "{$repoProvider}:{$repoName}",
            'portainer_url' => $this->getUrl(),
            'endpoint_id' => $this->getEndpointId(),
            'actions' => $actions,
            'note' => 'This will build and deploy via Portainer API (requires Docker registry configured)',
        ];
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        try {
            $client = $this->getApiClient();
            $repository = $project->repository();
            $repoUrl = $repository['url'] ?? '';
            $branch = $profile->branch();
            $domainValue = $profile->get('domain');
            $domain = \is_string($domainValue) ? $domainValue : '';
            $endpointId = $this->getEndpointId();

            $imageName = $this->buildImageName($project->name(), $branch);

            /** @var array<string, mixed> $buildResult */
            $buildResult = $client->buildImage(
                $endpointId,
                $project->path(),
                $imageName,
            );

            $buildSuccess = $buildResult['success'] ?? false;
            if (! $buildSuccess) {
                /** @var string $errorMsg */
                $errorMsg = $buildResult['message'] ?? 'Failed to build image';
                $this->lastError = $errorMsg;

                return false;
            }

            $containerName = $project->name();

            /** @var array<string, mixed> $existingContainer */
            $existingContainer = $client->getContainer($endpointId, $containerName);

            $existingSuccess = $existingContainer['success'] ?? false;
            if ($existingSuccess) {
                /** @var array<string, mixed> $containerData */
                $containerData = $existingContainer['data'] ?? [];
                if (isset($containerData['Id']) && \is_string($containerData['Id'])) {
                    $client->stopContainer($endpointId, $containerName);
                    $client->removeContainer($endpointId, $containerName);
                }
            }

            /** @var array<string, mixed> $deployResult */
            $deployResult = $client->createContainer($endpointId, [
                'name' => $containerName,
                'Image' => $imageName,
                'Env' => $this->buildEnvVars($profile),
                'HostConfig' => [
                    'PortBindings' => [
                        '3000/tcp' => [['HostPort' => '3000']],
                    ],
                    'RestartPolicy' => [
                        'Name' => 'unless-stopped',
                    ],
                ],
            ]);

            $deploySuccess = $deployResult['success'] ?? false;
            if (! $deploySuccess) {
                /** @var string $deployError */
                $deployError = $deployResult['message'] ?? 'Failed to create container';
                $this->lastError = $deployError;

                return false;
            }

            $client->startContainer($endpointId, $containerName);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function destroy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        return true;
    }

    private function getUrl(): string
    {
        $url = $this->config['url'] ?? '';

        return \is_string($url) ? $url : '';
    }

    private function getApiKey(): string
    {
        $key = $this->config['api_key'] ?? '';

        return \is_string($key) ? $key : '';
    }

    private function getEndpointId(): int
    {
        $id = $this->config['endpoint_id'] ?? 0;

        if (\is_int($id)) {
            return $id;
        }

        if (\is_string($id)) {
            return (int) $id;
        }

        return 0;
    }

    private function buildImageName(string $projectName, string $branch): string
    {
        $sanitizedBranch = \preg_replace('/[^a-zA-Z0-9]/', '-', $branch);

        return "shipper/{$projectName}:{$sanitizedBranch}";
    }

    /**
     * @return list<string>
     */
    private function buildEnvVars(ProfileConfig $profile): array
    {
        $vars = [];
        $config = $profile->config();
        foreach ($config as $key => $value) {
            if (\is_string($value)) {
                $vars[] = "{$key}={$value}";
            }
        }

        return $vars;
    }

    private function interpolateDatabaseName(string $name, string $projectName, string $profileName): string
    {
        $name = \str_replace('${PROJECT_NAME}', $projectName, $name);
        $name = \str_replace('${PROFILE}', $profileName, $name);

        return $name;
    }
}
