<?php

declare(strict_types=1);

\test('destroy command runs successfully with force flag', function (): void {
    $this->artisan('destroy', ['project' => 'api', '--profile' => 'production', '--force' => true])
        ->expectsOutputToContain('Destroying api')
        ->assertExitCode(0);
});

\test('destroy command shows error for nonexistent project', function (): void {
    $this->artisan('destroy', ['project' => 'nonexistent', '--profile' => 'production', '--force' => true])
        ->expectsOutput('Project not found: nonexistent')
        ->assertExitCode(1);
});

\test('destroy command shows error for nonexistent profile', function (): void {
    $this->artisan('destroy', ['project' => 'api', '--profile' => 'nonexistent', '--force' => true])
        ->expectsOutput('Profile not found: nonexistent')
        ->assertExitCode(1);
});
