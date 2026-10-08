<?php

declare(strict_types=1);

namespace App\Flows;

use App\Actions\CreateDeploymentPlanAction;
use App\Actions\DestroySiteAction;
use App\Actions\LoadConfigurationAction;
use App\Actions\ValidateProjectAction;
use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\DeploymentProviderInterface;
use App\Deployment\ProviderFactory;

final class DestroyDeploymentFlow
{
    /**
     * @param (\Closure(string, array<string, mixed>): DeploymentProviderInterface)|null $providerResolver
     */
    public function __construct(
        private readonly ?\Closure $providerResolver = null,
    ) {}

    /**
     * Plan a site destruction (validation + plan creation, no execution).
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

        $lifecycle = ServerLifecycleRuntime::resolve($provider, $profile);
        if ($lifecycle['error'] !== '') {
            return [
                'success' => false,
                'project' => $project,
                'profile' => $profile,
                'plan' => [],
                'errors' => [$lifecycle['error']],
                'error_message' => 'Server lifecycle resolution failed',
                'provider' => $provider,
            ];
        }
        $profile = $lifecycle['profile'];

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
     * Execute site destruction (after planning and confirmation).
     *
     * @return array{success: bool, error_message: string}
     */
    public function execute(
        DeploymentProviderInterface $provider,
        ProjectConfig $project,
        ProfileConfig $profile,
    ): array {
        $destroyAction = new DestroySiteAction;

        $serverConfig = $profile->server();
        $server = null;
        if ($serverConfig !== null) {
            $lifecycle = ServerLifecycleRuntime::resolve($provider, $profile);
            if ($lifecycle['error'] !== '') {
                return ['success' => false, 'error_message' => $lifecycle['error']];
            }
            $profile = $lifecycle['profile'];
            $server = $lifecycle['server'];
            if ($serverConfig->isCreate() && $server === null) {
                return ['success' => false, 'error_message' => 'No Shipper-managed server matching the configured create-mode server was found.'];
            }
        }
        if ($serverConfig !== null && $serverConfig->isCreate() && $serverConfig->cleanup() === 'destroy'
            && ($server['server_record_only'] ?? false) === true) {
            return ['success' => false, 'error_message' => 'Refusing to destroy the deployment because Shipper cannot prove ownership of the server resource. The application and data were left intact.'];
        }

        $result = $destroyAction->handle($provider, $project, $profile);
        if ($result && $serverConfig !== null && $serverConfig->isCreate() && $serverConfig->cleanup() === 'destroy') {
            $lifecycleProvider = ServerLifecycleRuntime::provider($provider);
            $serverId = ServerLifecycleRuntime::serverId($server ?? []);
            $ownershipToken = $server['ownership_token'] ?? null;
            if ($lifecycleProvider === null || ! \is_string($ownershipToken) || $ownershipToken === '') {
                return ['success' => false, 'error_message' => 'Managed server cleanup requires a provider lifecycle implementation and an ownership token.'];
            }
            if (! $lifecycleProvider->deleteServer($serverId, $ownershipToken)) {
                return ['success' => false, 'error_message' => $provider->getLastError() ?: 'Managed server cleanup failed.'];
            }
        }

        return [
            'success' => $result,
            'error_message' => $result ? '' : $provider->getLastError(),
        ];
    }
}
