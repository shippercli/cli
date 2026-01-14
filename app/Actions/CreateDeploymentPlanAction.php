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
        return $provider->plan($project, $profile);
    }
}
