<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;

interface DeploymentProviderInterface
{
    /**
     * Validate the configuration for this provider.
     *
     * @return array<string> Array of validation errors, empty if valid
     */
    public function validate(ProjectConfig $project, ProfileConfig $profile): array;

    /**
     * Plan the deployment (dry-run).
     *
     * @return array<string, mixed> Plan details
     */
    public function plan(ProjectConfig $project, ProfileConfig $profile): array;

    /**
     * Execute the deployment.
     */
    public function apply(ProjectConfig $project, ProfileConfig $profile): bool;

    /**
     * Get provider name.
     */
    public function getName(): string;
}
