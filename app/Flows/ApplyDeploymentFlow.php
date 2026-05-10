<?php

declare(strict_types=1);

namespace App\Flows;

use App\Actions\ApplyAliasAction;
use App\Actions\ApplyDeployScriptAction;
use App\Actions\CreateDeploymentPlanAction;
use App\Actions\ExecuteDeploymentAction;
use App\Actions\GetDeploymentLogsAction;
use App\Actions\LoadConfigurationAction;
use App\Actions\ValidateProjectAction;
use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\DeploymentProviderInterface;
use App\Deployment\PloiProvider;
use App\Deployment\ProviderFactory;

final class ApplyDeploymentFlow
{
    /**
     * @param (\Closure(string, array<string, mixed>): DeploymentProviderInterface)|null $providerResolver
     */
    public function __construct(
        private readonly ?\Closure $providerResolver = null,
    ) {}

    /**
     * Plan a deployment (validation + plan creation, no execution).
     *
     * @return array{success: bool, project: ProjectConfig|null, profile: ProfileConfig|null, plan: array<string, mixed>, errors: array<int, string>, error_message: string, provider: DeploymentProviderInterface|null}
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
                'errors' => [],
                'error_message' => "Profile not found: {$profileName}",
                'provider' => null,
            ];
        }

        if ($this->providerResolver !== null) {
            $provider = ($this->providerResolver)($project->provider(), $config->providers());
        } else {
            $providerFactory = new ProviderFactory($config->providers());
            $provider = $providerFactory->create($project->provider());
        }

        $errors = $validateAction->handle($provider, $project, $profile);
        if ($errors !== []) {
            return [
                'success' => false,
                'project' => $project,
                'profile' => $profile,
                'plan' => [],
                'errors' => $errors,
                'error_message' => 'Configuration validation failed',
                'provider' => $provider,
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
            'provider' => $provider,
        ];
    }

    /**
     * Execute a deployment (after planning and confirmation).
     *
     * @param array<string, mixed> $plan
     *
     * @return array{success: bool, logs: array<int, string>, error_message: string}
     */
    public function execute(
        DeploymentProviderInterface $provider,
        ProjectConfig $project,
        ProfileConfig $profile,
        array $plan,
    ): array {
        $deployAction = new ExecuteDeploymentAction;
        $logsAction = new GetDeploymentLogsAction;
        $aliasAction = new ApplyAliasAction;
        $deployScriptAction = new ApplyDeployScriptAction;

        $result = $deployAction->handle($provider, $project, $profile);

        $logs = [];
        $serverId = 0;
        $siteId = 0;

        if ($provider instanceof PloiProvider) {
            $serverIdValue = $plan['server_id'] ?? 0;
            \assert(\is_int($serverIdValue) || \is_string($serverIdValue) || \is_numeric($serverIdValue));
            $serverId = \is_int($serverIdValue) ? $serverIdValue : (int) $serverIdValue;
            $siteId = $provider->getLastSiteId();
            $logs = $logsAction->handle($provider, $serverId, $siteId);
        }

        if ($result && $serverId > 0 && $siteId > 0) {
            $aliases = $profile->aliases();
            if ($aliases !== []) {
                $aliasResult = $aliasAction->handle(
                    $provider->getName(),
                    $provider instanceof PloiProvider ? $provider->getApiKey() : '',
                    $serverId,
                    $siteId,
                    $project,
                    $profile,
                );
                if (! $aliasResult['success']) {
                    $errorMsg = 'Alias configuration failed';
                    if (isset($aliasResult['message']) && \is_string($aliasResult['message'])) {
                        $errorMsg = $aliasResult['message'];
                    }

                    return [
                        'success' => false,
                        'logs' => $logs,
                        'error_message' => $errorMsg,
                    ];
                }
            }

            $projectScript = $project->deployScript();
            $profileScript = $profile->deployScript();
            $hasProjectScript = $projectScript !== '';
            $hasProfileScript = $profileScript !== null && $profileScript !== '';

            if ($hasProjectScript || $hasProfileScript) {
                $deployScriptResult = $deployScriptAction->handle(
                    $provider->getName(),
                    $provider instanceof PloiProvider ? $provider->getApiKey() : '',
                    $serverId,
                    $siteId,
                    $project,
                    $profile,
                );
                if (! $deployScriptResult['success']) {
                    $errorMsg = 'Deploy script configuration failed';
                    if (isset($deployScriptResult['message']) && \is_string($deployScriptResult['message'])) {
                        $errorMsg = $deployScriptResult['message'];
                    }

                    return [
                        'success' => false,
                        'logs' => $logs,
                        'error_message' => $errorMsg,
                    ];
                }
            }
        }

        return [
            'success' => $result,
            'logs' => $logs,
            'error_message' => $result ? '' : $provider->getLastError(),
        ];
    }
}
