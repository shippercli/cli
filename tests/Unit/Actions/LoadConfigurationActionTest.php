<?php

declare(strict_types=1);

use App\Actions\LoadConfigurationAction;

\test('LoadConfigurationAction loads valid configuration', function (): void {
    $action = new LoadConfigurationAction;
    $config = $action->handle('deployer.yml');

    \expect($config)->toBeInstanceOf(\App\Config\DeployerConfig::class);
});

\test('LoadConfigurationAction throws exception for missing file', function (): void {
    $action = new LoadConfigurationAction;

    \expect(fn () => $action->handle('nonexistent.yml'))
        ->toThrow(\RuntimeException::class);
});
