<?php

declare(strict_types=1);

use Illuminate\Testing\PendingCommand;

\test('cleanup-orphaned command requires GITHUB_TOKEN', function (): void {
    /** @var Tests\TestCase $this */
    \putenv('GITHUB_TOKEN=');

    $command = $this->artisan('cleanup-orphaned', ['--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('GITHUB_TOKEN environment variable is required')
        ->assertExitCode(1);
});

\test('cleanup-orphaned command requires GITHUB_REPOSITORY', function (): void {
    /** @var Tests\TestCase $this */
    \putenv('GITHUB_TOKEN=test-token');
    \putenv('GITHUB_REPOSITORY=');

    $command = $this->artisan('cleanup-orphaned', ['--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('GITHUB_REPOSITORY environment variable is required (format: owner/repo)')
        ->assertExitCode(1);
    unset($command);

    \putenv('GITHUB_TOKEN=');
});

\test('cleanup-orphaned command supports dry-run flag', function (): void {
    /** @var Tests\TestCase $this */
    \putenv('GITHUB_TOKEN=');

    $command = $this->artisan('cleanup-orphaned', ['--dry-run' => true, '--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('GITHUB_TOKEN environment variable is required')
        ->assertExitCode(1);
});
