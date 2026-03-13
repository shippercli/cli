<?php

declare(strict_types=1);

use Illuminate\Testing\PendingCommand;

\test('plan command runs successfully', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('plan', ['project' => 'api', '--profile' => 'production']);
    \assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('Planning deployment')
        ->assertExitCode(0);
});

\test('plan command shows error for nonexistent project', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('plan', ['project' => 'nonexistent', '--profile' => 'production']);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Project not found: nonexistent')
        ->assertExitCode(1);
});

\test('plan command shows error for nonexistent profile', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('plan', ['project' => 'api', '--profile' => 'nonexistent']);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Profile not found: nonexistent')
        ->assertExitCode(1);
});
