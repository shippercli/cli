<?php

declare(strict_types=1);

use App\Actions\LoadConfigurationAction;
use App\Config\ShipperConfig;

\test('LoadConfigurationAction loads valid configuration', function (): void {
    $action = new LoadConfigurationAction;
    $config = $action->handle('shipper.yml.example');

    \expect($config)->toBeInstanceOf(ShipperConfig::class);
});

\test('LoadConfigurationAction throws exception for missing file', function (): void {
    $action = new LoadConfigurationAction;

    \expect(fn () => $action->handle('nonexistent.yml'))
        ->toThrow(RuntimeException::class);
});
