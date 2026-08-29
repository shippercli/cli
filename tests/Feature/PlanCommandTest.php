<?php

declare(strict_types=1);

use App\Deployment\ProviderRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use Tests\Fixtures\TestShipperPlugin;
use Tests\TestCase;

\test('plan command runs successfully', function (): void {
    /** @var TestCase $this */
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    $config = \tempnam(\sys_get_temp_dir(), 'shipper-plan-');
    \assert(\is_string($config));
    \file_put_contents($config, <<<'YAML'
providers:
  contract-test:
    token: configured
projects:
  api:
    provider: contract-test
    path: "."
    profiles:
      production:
        branch: main
        domain: api.example.com
YAML);

    try {
        $exitCode = Artisan::call('plan', ['project' => 'api', '--profile' => 'production', '--config' => $config]);
        \expect($exitCode)->toBe(0)
            ->and(Artisan::output())->toContain('Planning deployment');
    } finally {
        @\unlink($config);
    }
});

\test('plan command shows error for nonexistent project', function (): void {
    /** @var TestCase $this */
    $command = $this->artisan('plan', ['project' => 'nonexistent', '--profile' => 'production']);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Project not found: nonexistent')
        ->assertExitCode(1);
});

\test('plan command shows error for nonexistent profile', function (): void {
    /** @var TestCase $this */
    $command = $this->artisan('plan', ['project' => 'api', '--profile' => 'nonexistent']);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Profile not found: nonexistent')
        ->assertExitCode(1);
});
