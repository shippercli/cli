<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Railway;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\AbstractDeploymentProvider;

final class RailwayProvider extends AbstractDeploymentProvider
{
    private string $lastError = '';

    private ?RailwayApiClient $apiClient = null;

    public function getName(): string
    {
        return 'railway';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getApiClient(): RailwayApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = new RailwayApiClient($this->getToken());
        }

        return $this->apiClient;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        $token = $this->config['token'] ?? null;
        if (! \is_string($token) || $token === '') {
            $errors[] = 'Railway token is required';
        }

        $projectId = $this->config['project_id'] ?? null;
        if (! \is_string($projectId) || $projectId === '') {
            $errors[] = 'Railway project ID is required';
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
            "Configure Railway project: {$this->getProjectId()}",
            "Link repository: {$repoProvider}:{$repoName}",
            "Configure domain: {$domain}",
        ];

        $databases = $project->databases();
        if (! empty($databases)) {
            foreach ($databases as $database) {
                $dbName = $this->interpolateDatabaseName($database->name(), $project->name(), $profile->name());
                $actions[] = "Create database: {$dbName} ({$database->type()})";
            }
        }

        $actions[] = 'Deploy via Railway';

        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $branch,
            'path' => $project->path(),
            'domain' => $domain,
            'repository' => "{$repoProvider}:{$repoName}",
            'railway_project_id' => $this->getProjectId(),
            'actions' => $actions,
            'note' => 'This will configure deployment via Railway API',
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

            $service = $client->createServiceFromGit(
                $this->getProjectId(),
                $project->name(),
                $repoUrl,
            );

            /** @var array<string, mixed> $serviceData */
            $serviceData = $service;
            if (! ($serviceData['success'] ?? false)) {
                /** @var string $errorMessage */
                $errorMessage = $serviceData['message'] ?? 'Failed to create service';
                $this->lastError = $errorMessage;

                return false;
            }

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

    private function getToken(): string
    {
        $token = $this->config['token'] ?? '';

        return \is_string($token) ? $token : '';
    }

    private function getProjectId(): string
    {
        $projectId = $this->config['project_id'] ?? '';

        return \is_string($projectId) ? $projectId : '';
    }

    private function interpolateDatabaseName(string $name, string $projectName, string $profileName): string
    {
        $name = \str_replace('${PROJECT_NAME}', $projectName, $name);
        $name = \str_replace('${PROFILE}', $profileName, $name);

        return $name;
    }
}
