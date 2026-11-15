<?php

declare(strict_types=1);

use App\Deployment\ProviderRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use Tests\Fixtures\TestBlockedPlanProvider;
use Tests\Fixtures\TestCredentialUrlProvider;
use Tests\Fixtures\TestShipperPlugin;
use Tests\TestCase;

\beforeEach(function (): void {
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    ProviderRegistry::register('blocked-test', TestBlockedPlanProvider::class);
    ProviderRegistry::register('credential-url-test', TestCredentialUrlProvider::class);
});

\test('rollback forwards the trimmed release identifier and reports success', function (): void {
    $exitCode = Artisan::call('rollback', [
        'project' => 'app',
        '--config' => __DIR__.'/../Fixtures/provider-operations-config.yml',
        '--release' => ' release-20260727-001 ',
        '--force' => true,
    ]);

    \expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Rollback completed successfully.');
});

\test('rollback cancels when the user declines confirmation', function (): void {
    /** @var TestCase $this */
    $command = $this->artisan('rollback', [
        'project' => 'rejected',
        '--config' => __DIR__.'/../Fixtures/provider-operations-config.yml',
    ]);
    \assert($command instanceof PendingCommand);
    $command->expectsConfirmation('Roll back rejected (production)?', 'no')
        ->expectsOutput('Rollback cancelled.')
        ->assertExitCode(0);
});

\test('rollback reports errors and returns a failing exit code', function (string $project, ?string $release, string $message): void {
    $exitCode = Artisan::call('rollback', [
        'project' => $project,
        '--config' => __DIR__.'/../Fixtures/provider-operations-config.yml',
        '--release' => $release,
        '--force' => true,
    ]);

    \expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain($message);
})->with([
    ['unsupported', null, 'Provider blocked-test does not support deployment rollback.'],
    ['rejected', null, 'Request failed for https://[redacted]@example.test/repo.git'],
    ['app', 'unknown-release', 'Rollback failed.'],
    ['missing', null, 'Project not found: missing'],
]);
