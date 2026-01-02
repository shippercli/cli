<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use Ploi\Ploi;

final class PloiProvider extends AbstractDeploymentProvider
{
    private ?Ploi $client = null;

    private string $lastError = '';

    public function getName(): string
    {
        return 'ploi';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        // Validate Ploi-specific configuration
        if (! isset($this->config['api_key']) || $this->config['api_key'] === '') {
            $errors[] = 'Ploi API key is required';
        }

        if (! isset($this->config['server_id']) || $this->config['server_id'] === '') {
            $errors[] = 'Ploi server ID is required';
        }

        $domain = $profile->get('domain');
        if ($domain === null || $domain === '') {
            $errors[] = "Domain is required for profile: {$profile->name()}";
        }

        // Validate repository configuration
        $repository = $project->repository();
        if (empty($repository)) {
            $errors[] = 'Repository configuration is required';
        } else {
            if (! isset($repository['provider']) || $repository['provider'] === '') {
                $errors[] = 'Repository provider is required (github, gitlab, bitbucket, or custom)';
            }
            if (! isset($repository['name']) || $repository['name'] === '') {
                $errors[] = 'Repository name is required (e.g., username/repository)';
            }
        }

        return $errors;
    }

    public function plan(ProjectConfig $project, ProfileConfig $profile): array
    {
        $serverId = $this->getServerId();
        $domainValue = $profile->get('domain');
        $domain = \is_string($domainValue) ? $domainValue : '';
        $repository = $project->repository();
        $repoProviderValue = $repository['provider'] ?? 'unknown';
        $repoProvider = \is_string($repoProviderValue) ? $repoProviderValue : 'unknown';
        $repoNameValue = $repository['name'] ?? 'unknown';
        $repoName = \is_string($repoNameValue) ? $repoNameValue : 'unknown';

        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $profile->branch(),
            'path' => $project->path(),
            'server_id' => $serverId,
            'domain' => $domain,
            'repository' => "{$repoProvider}:{$repoName}",
            'web_directory' => $project->webDirectory(),
            'project_root' => $project->projectRoot(),
            'actions' => [
                "Create or find site for domain: {$domain}",
                "Install repository: {$repoProvider}:{$repoName} ({$profile->branch()})",
                'Deploy site via Ploi API',
                'Run deployment script',
            ],
            'note' => 'This will create a real deployment on Ploi server '.$serverId,
        ];
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $this->lastError = '';
        $serverId = 0;
        $domain = '';

        try {
            $client = $this->getClient();
            $serverId = (int) $this->getServerId();
            $domainValue = $profile->get('domain');
            $domain = \is_string($domainValue) ? $domainValue : '';

            if ($domain === '') {
                $this->lastError = 'Domain is empty or invalid';

                return false;
            }

            // Get the server
            $server = $client->server($serverId);

            // Get repository configuration
            $repository = $project->repository();
            $repoProvider = \is_string($repository['provider'] ?? null) ? $repository['provider'] : '';
            $repoName = \is_string($repository['name'] ?? null) ? $repository['name'] : '';
            $branch = $profile->branch();

            // Check if site already exists
            $sites = $server->sites()->get();
            $existingSite = null;

            $siteData = $sites->getJson()->data ?? null;
            if ($siteData !== null && \is_array($siteData)) {
                foreach ($siteData as $site) {
                    if (\is_object($site) && \property_exists($site, 'domain') && $site->domain === $domain) {
                        $existingSite = $site;
                        break;
                    }
                }
            }

            // Create site if it doesn't exist
            if ($existingSite === null) {
                $response = $server->sites()->create(
                    $domain,
                    $project->webDirectory(),
                    $project->projectRoot(),
                );
                $responseData = $response->getJson()->data ?? null;
                if ($responseData === null || ! \property_exists($responseData, 'id')) {
                    $this->lastError = 'Failed to create site: Invalid response from Ploi API';

                    return false;
                }
                $siteId = (int) $responseData->id;
            } else {
                if (! \property_exists($existingSite, 'id')) {
                    $this->lastError = 'Existing site found but has no ID';

                    return false;
                }
                $siteId = (int) $existingSite->id;
            }

            // Install/update repository
            $site = $server->sites($siteId);
            try {
                $site->repository()->install($repoProvider, $branch, $repoName);
            } catch (\Exception $e) {
                $this->lastError = "Failed to install repository: {$e->getMessage()}";

                return false;
            }

            // Deploy the site
            $deployResponse = $site->deployment()->deploy();

            // Check if deployment was successful
            $deployData = $deployResponse->getJson();
            if (isset($deployData->message) && \is_string($deployData->message)) {
                $this->lastError = "Ploi API message: {$deployData->message}";
            }

            return true;
        } catch (\Ploi\Exceptions\Http\Unauthenticated $e) {
            $this->lastError = "Authentication failed: Invalid Ploi API key. {$e->getMessage()}";

            return false;
        } catch (\Ploi\Exceptions\Http\NotFound $e) {
            $serverInfo = $serverId > 0 ? "Server ID {$serverId}" : 'The requested server';
            $this->lastError = "Resource not found: {$serverInfo} may not exist or you don't have access. {$e->getMessage()}";

            return false;
        } catch (\Ploi\Exceptions\Http\NotValid $e) {
            $this->lastError = "Validation error: {$e->getMessage()}";

            return false;
        } catch (\Exception $e) {
            $this->lastError = "Deployment error: {$e->getMessage()} (Type: ".\get_class($e).')';

            return false;
        }
    }

    private function getClient(): Ploi
    {
        if ($this->client === null) {
            $apiKey = $this->config['api_key'] ?? '';
            if (! \is_string($apiKey)) {
                $apiKey = '';
            }
            $this->client = new Ploi($apiKey);
        }

        return $this->client;
    }

    private function getServerId(): string
    {
        $serverId = $this->config['server_id'] ?? '';

        return \is_string($serverId) ? $serverId : '';
    }
}
