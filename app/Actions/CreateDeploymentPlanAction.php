<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\DeploymentProviderInterface;

final class CreateDeploymentPlanAction
{
    /**
     * Create a deployment plan for a project and profile.
     *
     * @return array<string, mixed>
     */
    public function handle(
        DeploymentProviderInterface $provider,
        ProjectConfig $project,
        ProfileConfig $profile,
    ): array {
        $plan = $provider->plan($project, $profile);
        self::assertNotBlocked($plan);

        return $plan;
    }

    /** @param array<string, mixed> $plan */
    public static function assertNotBlocked(array $plan): void
    {
        if (! \array_key_exists('blocked', $plan) || $plan['blocked'] === false) {
            return;
        }
        $actions = \is_array($plan['actions'] ?? null) ? $plan['actions'] : [];
        $reasons = \array_filter($actions, '\is_string');
        throw new \RuntimeException('Deployment plan is blocked. '.\implode(' ', $reasons));
    }
}
