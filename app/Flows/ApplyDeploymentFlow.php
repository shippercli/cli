<?php

declare(strict_types=1);

namespace App\Flows;

use App\Actions\CreateDeploymentPlanAction;
use App\Actions\ExecuteDeploymentAction;
use App\Actions\LoadConfigurationAction;
use App\Actions\ValidateProjectAction;
use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\ContractDeploymentProviderAdapter;
use App\Deployment\DeploymentProviderInterface;
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

        $result = $deployAction->handle($provider, $project, $profile);

        if (! $result) {
            return ['success' => false, 'logs' => [], 'error_message' => $provider->getLastError()];
        }

        $capabilityProvider = $provider instanceof ContractDeploymentProviderAdapter
            ? $provider->contractProvider()
            : $provider;

        if (\method_exists($capabilityProvider, 'postApply')) {
            $postApply = $capabilityProvider->postApply($project, $profile);
            if (! \is_array($postApply)) {
                return ['success' => false, 'logs' => [], 'error_message' => 'Provider returned an invalid post-apply result'];
            }

            $logs = \is_array($postApply['logs'] ?? null)
                ? \array_values(\array_filter($postApply['logs'], '\is_string'))
                : [];
            $success = ($postApply['success'] ?? false) === true;
            $message = $postApply['message'] ?? 'Provider post-apply configuration failed';

            return [
                'success' => $success,
                'logs' => $logs,
                'error_message' => $success ? '' : (\is_string($message) ? $message : 'Provider post-apply configuration failed'),
            ];
        }

        return [
            'success' => true,
            'logs' => [],
            'error_message' => '',
        ];
    }
}
