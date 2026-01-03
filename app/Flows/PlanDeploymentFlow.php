<?php

declare(strict_types=1);

namespace App\Flows;

use App\Actions\CreateDeploymentPlanAction;
use App\Actions\LoadConfigurationAction;
use App\Actions\ValidateProjectAction;
use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Providers\Deployment\ProviderFactory;

final class PlanDeploymentFlow
{
    /**
     * Plan a deployment.
     *
     * @return array{success: bool, project: ProjectConfig|null, profile: ProfileConfig|null, plan: array<string, mixed>, errors: array<int, string>, error_message: string}
     */
    public function handle(string $configPath, string $projectName, string $profileName): array
    {
        $loadAction = new LoadConfigurationAction;
        $validateAction = new ValidateProjectAction;
        $planAction = new CreateDeploymentPlanAction;

        $config = $loadAction->handle($configPath);

        $project = $config->getProject($projectName);
        if ($project === null) {
            return [
                'success' => false,
                'project' => null,
                'profile' => null,
                'plan' => [],
                'errors' => [],
                'error_message' => "Project not found: {$projectName}",
            ];
        }

        $profile = $project->getProfile($profileName);
        if ($profile === null) {
            return [
                'success' => false,
                'project' => $project,
                'profile' => null,
                'plan' => [],
                'errors' => [],
                'error_message' => "Profile not found: {$profileName}",
            ];
        }

        $providerFactory = new ProviderFactory($config->providers());
        $provider = $providerFactory->create($project->provider());

        $errors = $validateAction->handle($provider, $project, $profile);
        if ($errors !== []) {
            return [
                'success' => false,
                'project' => $project,
                'profile' => $profile,
                'plan' => [],
                'errors' => $errors,
                'error_message' => 'Configuration validation failed',
            ];
        }

        $plan = $planAction->handle($provider, $project, $profile);

        return [
            'success' => true,
            'project' => $project,
            'profile' => $profile,
            'plan' => $plan,
            'errors' => [],
            'error_message' => '',
        ];
    }
}
