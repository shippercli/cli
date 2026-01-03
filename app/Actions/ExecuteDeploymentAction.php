<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Providers\Deployment\DeploymentProviderInterface;

final class ExecuteDeploymentAction
{
    /**
     * Execute deployment for a project and profile.
     */
    public function handle(
        DeploymentProviderInterface $provider,
        ProjectConfig $project,
        ProfileConfig $profile,
    ): bool {
        return $provider->apply($project, $profile);
    }
}
