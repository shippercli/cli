<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Hostinger;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\AbstractDeploymentProvider;

final class HostingerProvider extends AbstractDeploymentProvider
{
    private string $lastError = '';

    private ?HostingerApiClient $apiClient = null;

    public function getName(): string
    {
        return 'hostinger';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getApiClient(): HostingerApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = new HostingerApiClient(
                $this->getApiToken(),
                $this->getEmail(),
                $this->getPassword(),
            );
        }

        return $this->apiClient;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        $token = $this->config['api_token'] ?? null;
        if (! \is_string($token) || $token === '') {
            $errors[] = 'Hostinger API token is required';
        }

        $email = $this->config['email'] ?? null;
        if (! \is_string($email) || $email === '') {
            $errors[] = 'Hostinger email is required';
        }

        $password = $this->config['password'] ?? null;
        if (! \is_string($password) || $password === '') {
            $errors[] = 'Hostinger password is required';
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
            'Authenticate with Hostinger API',
            "Find or create website: {$domain}",
            "Configure Git deployment: {$repoProvider}:{$repoName}",
            "Set deployment branch: {$branch}",
        ];

        $actions[] = 'Deploy via Hostinger';

        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $branch,
            'path' => $project->path(),
            'domain' => $domain,
            'repository' => "{$repoProvider}:{$repoName}",
            'actions' => $actions,
            'note' => 'This will configure deployment via Hostinger API',
        ];
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        try {
            $client = $this->getApiClient();

            $domainValue = $profile->get('domain');
            $domain = \is_string($domainValue) ? $domainValue : '';
            $branch = $profile->branch();
            /** @var array<string, mixed> $repository */
            $repository = $project->repository();
            /** @var string $repoUrl */
            $repoUrl = $repository['url'] ?? '';

            /** @var array<string, mixed> $website */
            $website = $client->findOrCreateWebsite($domain);
            /** @var bool $websiteSuccess */
            $websiteSuccess = $website['success'] ?? false;
            if (! $websiteSuccess) {
                /** @var string $errorMessage */
                $errorMessage = $website['message'] ?? 'Failed to find or create website';
                $this->lastError = $errorMessage;

                return false;
            }

            /** @var array<string, mixed> $websiteData */
            $websiteData = $website['data'] ?? [];
            /** @var string $websiteId */
            $websiteId = $websiteData['id'] ?? '';

            /** @var array<string, mixed> $deployResult */
            $deployResult = $client->configureGitDeployment($websiteId, $repoUrl, $branch);
            /** @var bool $deploySuccess */
            $deploySuccess = $deployResult['success'] ?? false;
            if (! $deploySuccess) {
                /** @var string $deployError */
                $deployError = $deployResult['message'] ?? 'Failed to configure git deployment';
                $this->lastError = $deployError;

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

    private function getApiToken(): string
    {
        $token = $this->config['api_token'] ?? '';

        return \is_string($token) ? $token : '';
    }

    private function getEmail(): string
    {
        $email = $this->config['email'] ?? '';

        return \is_string($email) ? $email : '';
    }

    private function getPassword(): string
    {
        $password = $this->config['password'] ?? '';

        return \is_string($password) ? $password : '';
    }
}
