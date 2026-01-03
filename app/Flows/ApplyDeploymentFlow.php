<?php

declare(strict_types=1);

namespace App\Flows;

use App\Actions\CreateDeploymentPlanAction;
use App\Actions\ExecuteDeploymentAction;
use App\Actions\GetDeploymentLogsAction;
use App\Actions\LoadConfigurationAction;
use App\Actions\ValidateProjectAction;
use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Providers\Deployment\DeploymentProviderInterface;
use App\Providers\Deployment\PloiProvider;
use App\Providers\Deployment\ProviderFactory;

final class ApplyDeploymentFlow
{
    /**
     * Apply a deployment.
     *
     * @return array{success: bool, project: ProjectConfig|null, profile: ProfileConfig|null, plan: array<string, mixed>, logs: array<int, string>, errors: array<int, string>, error_message: string, provider: DeploymentProviderInterface|null}
     */
    public function handle(string $configPath, string $projectName, string $profileName): array
    {
        $loadAction = new LoadConfigurationAction;
        $validateAction = new ValidateProjectAction;
        $planAction = new CreateDeploymentPlanAction;
        $deployAction = new ExecuteDeploymentAction;
        $logsAction = new GetDeploymentLogsAction;

        $config = $loadAction->handle($configPath);

        $project = $config->getProject($projectName);
        if ($project === null) {
            return [
                'success' => false,
                'project' => null,
                'profile' => null,
                'plan' => [],
                'logs' => [],
                'errors' => [],
                'error_message' => "Project not found: {$projectName}",
                'provider' => null,
            ];
        }

        $profile = $project->getProfile($profileName);
        if ($profile === null) {
            return [
                'success' => false,
                'project' => $project,
                'profile' => null,
                'plan' => [],
                'logs' => [],
                'errors' => [],
                'error_message' => "Profile not found: {$profileName}",
                'provider' => null,
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
                'logs' => [],
                'errors' => $errors,
                'error_message' => 'Configuration validation failed',
                'provider' => $provider,
            ];
        }

        $plan = $planAction->handle($provider, $project, $profile);
        $result = $deployAction->handle($provider, $project, $profile);

        $logs = [];
        if ($provider instanceof PloiProvider) {
            $serverId = (int) ($plan['server_id'] ?? 0);
            $siteId = $provider->getLastSiteId();
            $logs = $logsAction->handle($provider, $serverId, $siteId);
        }

        return [
            'success' => $result,
            'project' => $project,
            'profile' => $profile,
            'plan' => $plan,
            'logs' => $logs,
            'errors' => [],
            'error_message' => $result ? '' : $provider->getLastError(),
            'provider' => $provider,
        ];
    }
}
