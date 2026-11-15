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

\test('logs prints provider lines up to the requested limit', function (int $limit, string $expected): void {
    $exitCode = Artisan::call('logs', [
        'project' => 'app',
        '--config' => __DIR__.'/../Fixtures/provider-operations-config.yml',
        '--lines' => $limit,
    ]);

    \expect($exitCode)->toBe(0)
        ->and(\trim(Artisan::output()))->toBe($expected);
})->with([
    [1, 'application booted'],
    [5000, "application booted\nrequest completed"],
]);

\test('logs reports errors and returns a failing exit code', function (string $project, int $limit, string $message): void {
    $exitCode = Artisan::call('logs', [
        'project' => $project,
        '--config' => __DIR__.'/../Fixtures/provider-operations-config.yml',
        '--lines' => $limit,
    ]);

    \expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain($message);
})->with([
    ['missing', 0, 'The --lines option must be between 1 and 5000.'],
    ['missing', 5001, 'The --lines option must be between 1 and 5000.'],
    ['unsupported', 1, 'Provider blocked-test does not support deployment logs.'],
    ['missing', 1, 'Project not found: missing'],
]);
