<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\DeploymentProviderInterface;

final class ValidateProjectAction
{
    /**
     * Validate a project and profile configuration.
     *
     * @return array<int, string> Array of validation error messages, empty if valid
     */
    public function handle(
        DeploymentProviderInterface $provider,
        ProjectConfig $project,
        ProfileConfig $profile,
    ): array {
        return $provider->validate($project, $profile);
    }
}
