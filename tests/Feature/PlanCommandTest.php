<?php

declare(strict_types=1);

use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

\test('plan command runs successfully', function (): void {
    /** @var TestCase $this */
    \putenv('GITHUB_PR_NUMBER=123');
    \putenv('PLOI_API_KEY=test-mock-key');

    try {
        $command = $this->artisan('plan', ['project' => 'api', '--profile' => 'production']);
        \assert($command instanceof PendingCommand);
        $command->expectsOutputToContain('Planning deployment')
            ->assertExitCode(0);
        unset($command);
    } finally {
        \putenv('GITHUB_PR_NUMBER');
        \putenv('PLOI_API_KEY');
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
