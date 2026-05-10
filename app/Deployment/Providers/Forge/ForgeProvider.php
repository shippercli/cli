<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Forge;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\AbstractDeploymentProvider;
use App\Deployment\Contracts\AliasManagerInterface;
use App\Deployment\Contracts\DeployScriptManagerInterface;
use App\Deployment\Contracts\EnvironmentManagerInterface;
use App\Deployment\Contracts\SslManagerInterface;

final class ForgeProvider extends AbstractDeploymentProvider
{
    private string $lastError = '';

    private ?ForgeAliasManager $aliasManager = null;
    private ?ForgeDeployScriptManager $deployScriptManager = null;
    private ?ForgeEnvironmentManager $environmentManager = null;
    private ?ForgeSslManager $sslManager = null;

    public function getName(): string
    {
        return 'forge';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getAliasManager(): AliasManagerInterface
    {
        if ($this->aliasManager === null) {
            $this->aliasManager = new ForgeAliasManager($this->getApiToken());
        }

        return $this->aliasManager;
    }

    public function getDeployScriptManager(): DeployScriptManagerInterface
    {
        if ($this->deployScriptManager === null) {
            $this->deployScriptManager = new ForgeDeployScriptManager($this->getApiToken());
        }

        return $this->deployScriptManager;
    }

    public function getEnvironmentManager(): EnvironmentManagerInterface
    {
        if ($this->environmentManager === null) {
            $this->environmentManager = new ForgeEnvironmentManager($this->getApiToken());
        }

        return $this->environmentManager;
    }

    public function getSslManager(): SslManagerInterface
    {
        if ($this->sslManager === null) {
            $this->sslManager = new ForgeSslManager($this->getApiToken());
        }

        return $this->sslManager;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        if (! isset($this->config['api_token']) || $this->config['api_token'] === '') {
            $errors[] = 'Forge API token is required';
        }

        if (! isset($this->config['server_id']) || $this->config['server_id'] === '') {
            $errors[] = 'Forge server ID is required';
        }

        $domain = $profile->get('domain');
        if ($domain === null || $domain === '') {
            $errors[] = "Domain is required for profile: {$profile->name()}";
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

        $actions = [
            "Create or find site for domain: {$domain}",
            "Install repository: {$repoProvider}:{$repoName} ({$profile->branch()})",
        ];

        $databases = $project->databases();
        if (! empty($databases)) {
            foreach ($databases as $database) {
                $dbName = $this->interpolateDatabaseName($database->name(), $project->name(), $profile->name());
                $dbUser = $this->interpolateDatabaseName($database->user(), $project->name(), $profile->name());
                $actions[] = "Create or find database: {$dbName} (user: {$dbUser}, type: {$database->type()})";
            }
        }

        $actions[] = 'Deploy site via Forge API';
        $actions[] = 'Run deployment script';

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
            'databases' => \array_map(
                fn ($db) => [
                    'name' => $this->interpolateDatabaseName($db->name(), $project->name(), $profile->name()),
                    'user' => $this->interpolateDatabaseName($db->user(), $project->name(), $profile->name()),
                    'type' => $db->type(),
                ],
                $databases,
            ),
            'actions' => $actions,
            'note' => 'This will create a real deployment on Forge server '.$serverId,
        ];
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        return true;
    }

    public function destroy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        return true;
    }

    public function getServerId(): string
    {
        $serverId = $this->config['server_id'] ?? '';
        if (\is_string($serverId)) {
            return $serverId;
        }
        if (\is_int($serverId)) {
            return (string) $serverId;
        }

        return '';
    }

    public function getApiToken(): string
    {
        $apiToken = $this->config['api_token'] ?? '';
        if (\is_string($apiToken)) {
            return $apiToken;
        }

        return '';
    }

    private function interpolateDatabaseName(string $name, string $projectName, string $profileName): string
    {
        $name = \str_replace('${PROJECT_NAME}', $projectName, $name);
        $name = \str_replace('${PROFILE}', $profileName, $name);

        return $name;
    }
}