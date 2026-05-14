<?php

declare(strict_types=1);

namespace App\Deployment\Providers\CloudflarePages;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\AbstractDeploymentProvider;

final class CloudflarePagesProvider extends AbstractDeploymentProvider
{
    private string $lastError = '';

    private ?CloudflarePagesApiClient $apiClient = null;

    public function getName(): string
    {
        return 'cloudflare-pages';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getApiClient(): CloudflarePagesApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = new CloudflarePagesApiClient(
                $this->getAccountId(),
                $this->getApiToken(),
            );
        }

        return $this->apiClient;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        $accountId = $this->config['account_id'] ?? null;
        if (! \is_string($accountId) || $accountId === '') {
            $errors[] = 'Cloudflare account ID is required';
        }

        $token = $this->config['api_token'] ?? null;
        if (! \is_string($token) || $token === '') {
            $errors[] = 'Cloudflare API token is required';
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
            "Create Cloudflare Pages project: {$repoName}",
            "Configure GitHub integration: {$repoProvider}:{$repoName} ({$branch})",
            "Setup custom domain: {$domain}",
        ];

        $actions[] = 'Deploy via Cloudflare Pages';

        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $branch,
            'path' => $project->path(),
            'domain' => $domain,
            'repository' => "{$repoProvider}:{$repoName}",
            'cloudflare_account_id' => $this->getAccountId(),
            'actions' => $actions,
            'note' => 'This will configure deployment via Cloudflare Pages API',
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

            /** @var array<string, mixed> $result */
            $result = $client->createProject(
                $project->name(),
                $repoUrl,
                $branch,
            );

            /** @var bool $success */
            $success = $result['success'] ?? false;
            if (! $success) {
                /** @var string $errorMessage */
                $errorMessage = $result['message'] ?? 'Failed to create project';
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

    private function getAccountId(): string
    {
        $accountId = $this->config['account_id'] ?? '';

        return \is_string($accountId) ? $accountId : '';
    }

    private function getApiToken(): string
    {
        $token = $this->config['api_token'] ?? '';

        return \is_string($token) ? $token : '';
    }
}
