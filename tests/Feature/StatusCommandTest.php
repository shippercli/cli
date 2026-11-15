<?php

declare(strict_types=1);

use App\Deployment\ProviderRegistry;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\TestBlockedPlanProvider;
use Tests\Fixtures\TestShipperPlugin;

\beforeEach(function (): void {
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    ProviderRegistry::register('blocked-test', TestBlockedPlanProvider::class);
});

\test('status prints the provider deployment state as JSON', function (): void {
    $exitCode = Artisan::call('status', [
        'project' => 'app',
        '--config' => __DIR__.'/../Fixtures/provider-operations-config.yml',
    ]);

    \expect($exitCode)->toBe(0)
        ->and(\json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->toBe([
            'state' => 'healthy',
            'release' => 'release-20260727-001',
        ]);
});

\test('status reports errors and returns a failing exit code', function (string $project, string $message): void {
    $exitCode = Artisan::call('status', [
        'project' => $project,
        '--config' => __DIR__.'/../Fixtures/provider-operations-config.yml',
    ]);

    \expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain($message);
})->with([
    ['unsupported', 'Provider blocked-test does not support deployment status.'],
    ['missing', 'Project not found: missing'],
]);
