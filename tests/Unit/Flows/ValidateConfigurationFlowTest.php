<?php

declare(strict_types=1);

use App\Flows\ValidateConfigurationFlow;

\test('ValidateConfigurationFlow validates valid configuration', function (): void {
    $flow = new ValidateConfigurationFlow;
    $result = $flow->handle(__DIR__.'/../../../test-validate.yml');

    \expect($result)->toBe([
        'success' => true,
        'errors' => [],
    ]);
});

\test('ValidateConfigurationFlow groups unknown provider and invalid profile errors by project', function (): void {
    $flow = new ValidateConfigurationFlow;
    $result = $flow->handle(__DIR__.'/../../Fixtures/invalid-validation-config.yml');

    \expect($result)->toBe([
        'success' => false,
        'errors' => [
            'unknown' => ['_provider' => ['Unknown provider: not-registered']],
            'api' => ['production' => ['Domain is required for profile']],
        ],
    ]);
});

\test('ValidateConfigurationFlow throws exception for missing file', function (): void {
    $flow = new ValidateConfigurationFlow;

    \expect(fn () => $flow->handle('nonexistent.yml'))
        ->toThrow(RuntimeException::class);
});
