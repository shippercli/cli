<?php

declare(strict_types=1);

use App\Flows\Validation\ValidateConfigurationFlow;

\test('ValidateConfigurationFlow validates valid configuration', function (): void {
    $flow = new ValidateConfigurationFlow;
    $result = $flow->handle('shipper.yml');

    \expect($result)
        ->toHaveKey('success')
        ->toHaveKey('errors');
});

\test('ValidateConfigurationFlow throws exception for missing file', function (): void {
    $flow = new ValidateConfigurationFlow;

    \expect(fn () => $flow->handle('nonexistent.yml'))
        ->toThrow(\RuntimeException::class);
});
