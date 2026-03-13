<?php

declare(strict_types=1);

use Illuminate\Testing\PendingCommand;

\test('apply command runs successfully with force flag', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('apply', ['project' => 'api', '--profile' => 'production', '--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('Deploying api')
        ->assertExitCode(0);
});

\test('apply command shows error for nonexistent project', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('apply', ['project' => 'nonexistent', '--profile' => 'production', '--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Project not found: nonexistent')
        ->assertExitCode(1);
});

\test('apply command shows error for nonexistent profile', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('apply', ['project' => 'api', '--profile' => 'nonexistent', '--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Profile not found: nonexistent')
        ->assertExitCode(1);
});
