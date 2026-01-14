<?php

declare(strict_types=1);

namespace App\Actions\Server;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\DeploymentProviderInterface;

final class DestroySiteAction
{
    /**
     * Destroy a site for a project and profile.
     */
    public function handle(
        DeploymentProviderInterface $provider,
        ProjectConfig $project,
        ProfileConfig $profile,
    ): bool {
        return $provider->destroy($project, $profile);
    }
}
