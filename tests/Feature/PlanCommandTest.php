<?php

declare(strict_types=1);

use App\Deployment\ProviderRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use Tests\Fixtures\TestShipperPlugin;
use Tests\TestCase;

\test('plan command shows optional deployment fields, actions, and a note', function (): void {
    /** @var TestCase $this */
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    $config = \tempnam(\sys_get_temp_dir(), 'shipper-plan-');
    \assert(\is_string($config));
    \file_put_contents($config, <<<'YAML'
providers:
  contract-test:
    token: configured
    plan_fields:
      project: api
      profile: production
      branch: main
      path: .
      server_id: 12345
      domain: api.example.com
      repository: github:example/api
      web_directory: /public
      project_root: /var/www/api
      actions:
        - Configure application
        - Enable TLS
      note: Review this plan before deploying.
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
            ->and(Artisan::output())->toContain(
                'Planning deployment for api (production)...',
                'Deployment Plan:',
                '  Provider: contract-test',
                '  Server:   12345',
                '  Domain:   api.example.com',
                '  Repository: github:example/api',
                '  Web Dir:  /public',
                '  Root:     /var/www/api',
                'Actions:',
                '  • Configure application',
                '  • Enable TLS',
                'Review this plan before deploying.',
            );
    } finally {
        @\unlink($config);
    }
});

\test('plan command shows error for nonexistent project', function (): void {
    /** @var TestCase $this */
    $command = $this->artisan('plan', ['project' => 'nonexistent', '--profile' => 'production', '--config' => 'shipper.yml.example']);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Project not found: nonexistent')
        ->assertExitCode(1);
});

\test('plan command shows error for nonexistent profile', function (): void {
    /** @var TestCase $this */
    $command = $this->artisan('plan', ['project' => 'api', '--profile' => 'nonexistent', '--config' => 'shipper.yml.example']);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Profile not found: nonexistent')
        ->assertExitCode(1);
});

\test('plan command lists provider validation errors and exits unsuccessfully', function (): void {
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    $config = \tempnam(\sys_get_temp_dir(), 'shipper-plan-');
    \assert(\is_string($config));

    try {
        \file_put_contents($config, <<<'YAML'
providers:
  contract-test:
    validation_errors:
      - API token is required
      - Domain is required for profile production
projects:
  api:
    provider: contract-test
    path: "."
    profiles:
      production:
        branch: main
YAML);

        $exitCode = Artisan::call('plan', ['project' => 'api', '--config' => $config]);
        \expect($exitCode)->toBe(1)
            ->and(Artisan::output())->toContain(
                'Configuration validation failed:',
                '  ✗ API token is required',
                '  ✗ Domain is required for profile production',
            )->not->toContain('Deployment Plan:');
    } finally {
        @\unlink($config);
    }
});

\test('plan command reports a missing configuration file', function (): void {
    /** @var TestCase $this */
    $config = \sys_get_temp_dir().'/shipper-plan-'.\bin2hex(\random_bytes(8)).'.yml';
    $command = $this->artisan('plan', ['project' => 'api', '--config' => $config]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput("Plan failed: Config file not found: {$config}")
        ->assertExitCode(1);
});
