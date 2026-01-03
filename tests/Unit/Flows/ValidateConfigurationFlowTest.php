<?php

declare(strict_types=1);

use App\Flows\ValidateConfigurationFlow;

\test('ValidateConfigurationFlow validates valid configuration', function (): void {
    $flow = new ValidateConfigurationFlow;
    $result = $flow->handle('deployer.yml');

    \expect($result)
        ->toHaveKey('success')
        ->toHaveKey('errors');
});

\test('ValidateConfigurationFlow throws exception for missing file', function (): void {
    $flow = new ValidateConfigurationFlow;

    \expect(fn () => $flow->handle('nonexistent.yml'))
        ->toThrow(\RuntimeException::class);
});
