<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ConfigLoader;
use App\Config\DeployerConfig;

final class LoadConfigurationAction
{
    /**
     * Load configuration from file.
     */
    public function handle(string $configPath): DeployerConfig
    {
        $loader = new ConfigLoader($configPath);

        return $loader->load();
    }
}
