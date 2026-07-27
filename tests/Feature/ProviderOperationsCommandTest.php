<?php

declare(strict_types=1);

use App\Deployment\ProviderRegistry;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\TestShipperPlugin;

function providerOperationsConfig(): string
{
    $path = \tempnam(\sys_get_temp_dir(), 'shipper-operations-');
    \assert(\is_string($path));
    \file_put_contents($path, <<<'YAML'
providers:
  contract-test:
    token: configured
projects:
  app:
    provider: contract-test
    path: "."
    profiles:
      production:
        branch: main
        domain: app.example.com
YAML);

    return $path;
}

\test('status command prints provider deployment state', function (): void {
    /** @var Tests\TestCase $this */
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    $config = \providerOperationsConfig();

    try {
        $exitCode = Artisan::call('status', [
            'project' => 'app',
            '--config' => $config,
        ]);
        \expect($exitCode)->toBe(0)
            ->and(Artisan::output())->toContain('"state": "healthy"');
    } finally {
        @\unlink($config);
    }
});

\test('logs command prints provider log lines', function (): void {
    /** @var Tests\TestCase $this */
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    $config = \providerOperationsConfig();

    try {
        $exitCode = Artisan::call('logs', [
            'project' => 'app',
            '--config' => $config,
            '--lines' => 1,
        ]);
        $output = Artisan::output();
        \expect($exitCode)->toBe(0)
            ->and($output)->toContain('application booted')
            ->and(\str_contains($output, 'request completed'))->toBeFalse();
    } finally {
        @\unlink($config);
    }
});

\test('rollback command restores provider release with force flag', function (): void {
    /** @var Tests\TestCase $this */
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    $config = \providerOperationsConfig();

    try {
        $exitCode = Artisan::call('rollback', [
            'project' => 'app',
            '--config' => $config,
            '--release' => 'release-20260727-001',
            '--force' => true,
        ]);
        \expect($exitCode)->toBe(0)
            ->and(Artisan::output())->toContain('Rollback completed successfully.');
    } finally {
        @\unlink($config);
    }
});
