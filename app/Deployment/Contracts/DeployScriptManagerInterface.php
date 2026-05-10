<?php

declare(strict_types=1);

namespace App\Deployment\Contracts;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;

interface DeployScriptManagerInterface
{
    /**
     * Plan deploy script configuration.
     *
     * @return array<string>
     */
    public function plan(ProjectConfig $project, ProfileConfig $profile): array;

    /**
     * Apply deploy script configuration.
     *
     * @return array<string, mixed>
     */
    public function apply(int $serverId, int $siteId, string $script): array;
}
