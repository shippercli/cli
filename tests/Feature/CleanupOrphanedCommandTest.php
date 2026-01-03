<?php

declare(strict_types=1);

\test('cleanup-orphaned command requires GITHUB_TOKEN', function (): void {
    // Ensure GITHUB_TOKEN is not set
    \putenv('GITHUB_TOKEN=');

    $this->artisan('cleanup-orphaned', ['--force' => true])
        ->expectsOutput('GITHUB_TOKEN environment variable is required')
        ->assertExitCode(1);
});

\test('cleanup-orphaned command requires GITHUB_REPOSITORY', function (): void {
    // Set GITHUB_TOKEN but not GITHUB_REPOSITORY
    \putenv('GITHUB_TOKEN=test-token');
    \putenv('GITHUB_REPOSITORY=');

    $this->artisan('cleanup-orphaned', ['--force' => true])
        ->expectsOutput('GITHUB_REPOSITORY environment variable is required (format: owner/repo)')
        ->assertExitCode(1);

    // Clean up
    \putenv('GITHUB_TOKEN=');
});

\test('cleanup-orphaned command supports dry-run flag', function (): void {
    // This test just verifies the command accepts the --dry-run flag
    // We can't test actual cleanup without real API credentials
    \putenv('GITHUB_TOKEN=');

    $this->artisan('cleanup-orphaned', ['--dry-run' => true, '--force' => true])
        ->expectsOutput('GITHUB_TOKEN environment variable is required')
        ->assertExitCode(1);
});
