<?php

declare(strict_types=1);

namespace App\Deployment\Providers\EasyPanel;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\AbstractDeploymentProvider;

final class EasyPanelProvider extends AbstractDeploymentProvider
{
    private string $lastError = '';

    private ?EasyPanelApiClient $apiClient = null;

    public function getName(): string
    {
        return 'easypanel';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getApiClient(): EasyPanelApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = new EasyPanelApiClient(
                $this->getBaseUrl(),
                $this->getAuthToken(),
            );
        }

        return $this->apiClient;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        $url = $this->config['url'] ?? null;
        if (! \is_string($url) || $url === '') {
            $errors[] = 'EasyPanel URL is required (e.g., https://easypanel.example.com)';
        }

        $token = $this->config['auth_token'] ?? null;
        if (! \is_string($token) || $token === '') {
            $errors[] = 'EasyPanel auth token is required';
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
            "Connect to EasyPanel server: {$this->getBaseUrl()}",
            "Find or create project: {$project->name()}",
            "Create app service: {$repoName}",
            "Configure Git source: {$repoProvider}:{$repoName} ({$branch})",
            "Setup domain: {$domain}",
        ];

        $actions[] = 'Trigger deployment';

        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $branch,
            'path' => $project->path(),
            'domain' => $domain,
            'repository' => "{$repoProvider}:{$repoName}",
            'easypanel_url' => $this->getBaseUrl(),
            'actions' => $actions,
            'note' => 'This will configure deployment via EasyPanel API',
        ];
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $this->lastError = '';

        try {
            $repoUrl = $this->repositoryUrl($project->repository());
            if ($repoUrl === null) {
                $this->lastError = 'EasyPanel requires a usable repository URL or a supported repository provider and name';

                return false;
            }

            $client = $this->getApiClient();
            $branch = $profile->branch();
            $domainValue = $profile->get('domain');
            $domain = \is_string($domainValue) ? $domainValue : '';

            /** @var array<string, mixed> $projectData */
            $projectData = $this->findOrCreateProject($client, $project->name());

            $projSuccess = $projectData['success'] ?? false;
            if (! $projSuccess) {
                /** @var string $projError */
                $projError = $projectData['message'] ?? 'Failed to find or create project';
                $this->lastError = $projError;

                return false;
            }

            /** @var array<string, mixed> $projResultData */
            $projResultData = $projectData['data'] ?? [];
            /** @var string $projectName */
            $projectName = $projResultData['name'] ?? $project->name();

            $serviceData = $this->createAppService($client, $projectName, $project->name(), $repoUrl, $branch);

            $svcSuccess = $serviceData['success'] ?? false;
            if (! $svcSuccess) {
                /** @var string $svcError */
                $svcError = $serviceData['message'] ?? 'Failed to create service';
                $this->lastError = $svcError;

                return false;
            }

            /** @var array<string, mixed> $svcResultData */
            $svcResultData = $serviceData['data'] ?? [];
            /** @var string $serviceName */
            $serviceName = $svcResultData['name'] ?? $project->name();

            if ($domain !== '') {
                $client->setServiceDomain($projectName, $serviceName, $domain);
            }

            $client->startService($projectName, $serviceName);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function destroy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $this->lastError = 'EasyPanel destroy is not implemented';

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrCreateProject(EasyPanelApiClient $client, string $name): array
    {
        $projects = $client->listProjects();

        if (! $projects['success']) {
            return $projects;
        }

        /** @var array<array<string, mixed>> $projectList */
        $projectList = $projects['data'] ?? [];
        foreach ($projectList as $projectItem) {
            if (($projectItem['name'] ?? '') === $name) {
                return [
                    'success' => true,
                    'message' => 'Project found',
                    'data' => $projectItem,
                ];
            }
        }

        return $client->createProject($name);
    }

    /**
     * @return array<string, mixed>
     */
    private function createAppService(EasyPanelApiClient $client, string $projectName, string $serviceName, string $gitRepo, string $branch): array
    {
        $gitRepoFormatted = $this->formatGitUrl($gitRepo);

        return $client->createAppService($projectName, [
            'name' => $serviceName,
            'git_url' => $gitRepoFormatted,
            'git_branch' => $branch,
            'build_type' => 'nixpacks',
            'port' => 3000,
            'env' => [],
        ]);
    }

    private function formatGitUrl(string $gitRepo): string
    {
        $gitRepo = \trim($gitRepo);

        if (\preg_match('/^https?:\/\/[^\s]+$/', $gitRepo) === 1) {
            return $gitRepo;
        }

        if (\preg_match('/^git@([^:]+):(.+)$/', $gitRepo, $matches) === 1) {
            return "https://{$matches[1]}/{$matches[2]}";
        }

        return '';
    }

    /**
     * @param array<string, mixed> $repository
     */
    private function repositoryUrl(array $repository): ?string
    {
        $url = $repository['url'] ?? null;
        if (\is_string($url) && $url !== '') {
            $formatted = $this->formatGitUrl($url);

            return $formatted !== '' ? $formatted : null;
        }

        $provider = $repository['provider'] ?? null;
        $name = $repository['name'] ?? null;
        if (! \is_string($provider) || ! \is_string($name)) {
            return null;
        }

        $host = match (\strtolower($provider)) {
            'github' => 'github.com',
            'gitlab' => 'gitlab.com',
            'bitbucket' => 'bitbucket.org',
            default => null,
        };
        $name = \trim($name, '/');

        if ($host === null || \preg_match('/^[A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_.-]+)+$/', $name) !== 1) {
            return null;
        }

        return "https://{$host}/{$name}".(\str_ends_with($name, '.git') ? '' : '.git');
    }

    private function getBaseUrl(): string
    {
        $url = $this->config['url'] ?? '';

        return \is_string($url) ? $url : '';
    }

    private function getAuthToken(): string
    {
        $token = $this->config['auth_token'] ?? '';

        return \is_string($token) ? $token : '';
    }
}
