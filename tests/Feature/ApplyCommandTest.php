<?php

declare(strict_types=1);

\test('apply command runs successfully with force flag', function (): void {
    $this->artisan('apply', ['project' => 'api', '--profile' => 'production', '--force' => true])
        ->expectsOutputToContain('Deploying api')
        ->assertExitCode(0);
});

\test('apply command shows error for nonexistent project', function (): void {
    $this->artisan('apply', ['project' => 'nonexistent', '--profile' => 'production', '--force' => true])
        ->expectsOutput('Project not found: nonexistent')
        ->assertExitCode(1);
});

\test('apply command shows error for nonexistent profile', function (): void {
    $this->artisan('apply', ['project' => 'api', '--profile' => 'nonexistent', '--force' => true])
        ->expectsOutput('Profile not found: nonexistent')
        ->assertExitCode(1);
});
