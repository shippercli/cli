<?php

declare(strict_types=1);

use App\Deployment\DeploymentProviderInterface;
use App\Flows\DestroyDeploymentFlow;
use Illuminate\Testing\PendingCommand;

\test('destroy command runs successfully with force flag', function (): void {
    /** @var Tests\TestCase $this */
    \putenv('PLOI_API_KEY=test-mock-key');

    $planData = [
        'provider' => 'ploi',
        'project' => 'api',
        'profile' => 'production',
        'branch' => 'main',
        'path' => './examples/api',
        'server_id' => '12345',
        'domain' => 'api.example.com',
        'repository' => 'github:test/repo',
        'web_directory' => '/public',
        'project_root' => '/',
        'databases' => [],
        'actions' => [],
        'note' => 'mock deployment',
    ];

    /** @var DeploymentProviderInterface&\Mockery\MockInterface $mockProvider */
    $mockProvider = \Mockery::mock(DeploymentProviderInterface::class, [
        'validate' => [],
        'plan' => $planData,
        'destroy' => true,
        'getLastError' => '',
        'getName' => 'ploi',
    ]);

    $flow = new DestroyDeploymentFlow(
        providerResolver: static fn (string $name, array $config): DeploymentProviderInterface => $mockProvider,
    );
    $this->app->instance(DestroyDeploymentFlow::class, $flow); /** @phpstan-ignore property.protected */
    $command = $this->artisan('destroy', ['project' => 'api', '--profile' => 'production', '--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('Destroying api')
        ->assertExitCode(0);
    unset($command);

    \putenv('PLOI_API_KEY');
});

\test('destroy command shows error for nonexistent project', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('destroy', ['project' => 'nonexistent', '--profile' => 'production', '--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Project not found: nonexistent')
        ->assertExitCode(1);
});

\test('destroy command shows error for nonexistent profile', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('destroy', ['project' => 'api', '--profile' => 'nonexistent', '--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Profile not found: nonexistent')
        ->assertExitCode(1);
});
