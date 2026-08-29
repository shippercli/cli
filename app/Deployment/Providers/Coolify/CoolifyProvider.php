<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Coolify;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\AbstractDeploymentProvider;

final class CoolifyProvider extends AbstractDeploymentProvider
{
    private string $lastError = '';

    private ?CoolifyApiClient $apiClient = null;

    public function getName(): string
    {
        return 'coolify';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getApiClient(): CoolifyApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = new CoolifyApiClient(
                $this->getServerUrl(),
                $this->getApiToken(),
            );
        }

        return $this->apiClient;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        $url = $this->config['url'] ?? null;
        if (! \is_string($url) || $url === '') {
            $errors[] = 'Coolify server URL is required (e.g., https://coolify.example.com)';
        }

        $token = $this->config['api_token'] ?? null;
        if (! \is_string($token) || $token === '') {
            $errors[] = 'Coolify API token is required';
        }

        $domain = $profile->get('domain');
        if ($domain === null || $domain === '') {
            $errors[] = "Domain is required for profile: {$profile->name()}";
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
            "Connect to Coolify server: {$this->getServerUrl()}",
            "Find or create project: {$project->name()}",
            "Create application: {$repoName}",
            "Configure Git source: {$repoProvider}:{$repoName} ({$branch})",
            "Setup domain: {$domain}",
        ];

        $databases = $project->databases();
        if (! empty($databases)) {
            foreach ($databases as $database) {
                $dbName = $this->interpolateDatabaseName($database->name(), $project->name(), $profile->name());
                $actions[] = "Create database: {$dbName} ({$database->type()})";
            }
        }

        $actions[] = 'Trigger deployment';

        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $branch,
            'path' => $project->path(),
            'domain' => $domain,
            'repository' => "{$repoProvider}:{$repoName}",
            'coolify_url' => $this->getServerUrl(),
            'actions' => $actions,
            'note' => 'This will configure deployment via Coolify API',
        ];
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        try {
            $client = $this->getApiClient();
            /** @var array<string, mixed> $repository */
            $repository = $project->repository();
            /** @var string $repoUrl */
            $repoUrl = $repository['url'] ?? '';
            $branch = $profile->branch();
            $domainValue = $profile->get('domain');
            $domain = \is_string($domainValue) ? $domainValue : '';

            $projectUuid = $this->findOrCreateProject($client, $project->name());

            /** @var array<string, mixed> $application */
            $application = $client->createApplication(
                $projectUuid,
                $project->name(),
                $repoUrl,
                $branch,
            );

            $appSuccess = $application['success'] ?? false;
            if (! $appSuccess) {
                /** @var string $errorMsg */
                $errorMsg = $application['message'] ?? 'Failed to create application';
                $this->lastError = $errorMsg;

                return false;
            }

            /** @var array<string, mixed> $appData */
            $appData = $application['data'] ?? [];
            /** @var string $applicationUuid */
            $applicationUuid = $appData['uuid'] ?? '';

            if ($domain !== '') {
                $client->addDomain($applicationUuid, $domain);
            }

            $client->deploy($applicationUuid);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function destroy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $this->lastError = 'Coolify destroy is not implemented';

        return false;
    }

    private function getServerUrl(): string
    {
        $url = $this->config['url'] ?? '';

        return \is_string($url) ? $url : '';
    }

    private function getApiToken(): string
    {
        $token = $this->config['api_token'] ?? '';

        return \is_string($token) ? $token : '';
    }

    private function findOrCreateProject(CoolifyApiClient $client, string $name): string
    {
        /** @var array<string, mixed> $projects */
        $projects = $client->listProjects();

        $projSuccess = $projects['success'] ?? false;
        if (! $projSuccess) {
            return $this->createProject($client, $name);
        }

        /** @var array<array<string, mixed>> $projectList */
        $projectList = $projects['data'] ?? [];
        foreach ($projectList as $project) {
            if (($project['name'] ?? '') === $name) {
                /** @var string $uuid */
                $uuid = $project['uuid'] ?? '';

                return $uuid;
            }
        }

        return $this->createProject($client, $name);
    }

    private function createProject(CoolifyApiClient $client, string $name): string
    {
        /** @var array<string, mixed> $result */
        $result = $client->createProject($name);

        $resultSuccess = $result['success'] ?? false;
        if (! $resultSuccess) {
            /** @var string $errorMsg */
            $errorMsg = $result['message'] ?? 'Failed to create project';
            $this->lastError = $errorMsg;

            return '';
        }

        /** @var array<string, mixed> $resultData */
        $resultData = $result['data'] ?? [];
        /** @var string $uuid */
        $uuid = $resultData['uuid'] ?? '';

        return $uuid;
    }

    private function interpolateDatabaseName(string $name, string $projectName, string $profileName): string
    {
        $name = \str_replace('${PROJECT_NAME}', $projectName, $name);
        $name = \str_replace('${PROFILE}', $profileName, $name);

        return $name;
    }
}
