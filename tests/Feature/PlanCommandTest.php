<?php

declare(strict_types=1);

\test('plan command runs successfully', function (): void {
    $this->artisan('plan', ['project' => 'api', '--profile' => 'production'])
        ->expectsOutputToContain('Planning deployment')
        ->assertExitCode(0);
});

\test('plan command shows error for nonexistent project', function (): void {
    $this->artisan('plan', ['project' => 'nonexistent', '--profile' => 'production'])
        ->expectsOutput('Project not found: nonexistent')
        ->assertExitCode(1);
});

\test('plan command shows error for nonexistent profile', function (): void {
    $this->artisan('plan', ['project' => 'api', '--profile' => 'nonexistent'])
        ->expectsOutput('Profile not found: nonexistent')
        ->assertExitCode(1);
});
