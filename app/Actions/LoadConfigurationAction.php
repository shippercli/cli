<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ConfigLoader;
use App\Config\ShipperConfig;

final class LoadConfigurationAction
{
    /**
     * Load configuration from file.
     */
    public function handle(string $configPath): ShipperConfig
    {
        $loader = new ConfigLoader($configPath);

        return $loader->load();
    }
}
