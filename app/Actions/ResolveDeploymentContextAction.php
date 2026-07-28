<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\DeploymentProviderInterface;
use App\Deployment\ProviderFactory;
use InvalidArgumentException;

final class ResolveDeploymentContextAction
{
    /**
     * @return array{
     *     project: ProjectConfig,
     *     profile: ProfileConfig,
     *     provider: DeploymentProviderInterface
     * }
     */
    public function handle(string $configPath, string $projectName, string $profileName): array
    {
        $config = (new LoadConfigurationAction)->handle($configPath);
        $project = $config->getProject($projectName);
        if ($project === null) {
            throw new InvalidArgumentException("Project not found: {$projectName}");
        }

        $profile = $project->getProfile($profileName);
        if ($profile === null) {
            throw new InvalidArgumentException("Profile not found: {$profileName}");
        }

        $provider = (new ProviderFactory($config->providers()))->create($project->provider());
        $errors = $provider->validate($project, $profile);
        if ($errors !== []) {
            throw new InvalidArgumentException(
                "Configuration validation failed:\n  - ".\implode("\n  - ", $errors),
            );
        }

        return [
            'project' => $project,
            'profile' => $profile,
            'provider' => $provider,
        ];
    }
}
