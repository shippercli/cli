<?php

declare(strict_types=1);

use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

\test('plan command runs successfully', function (): void {
    /** @var TestCase $this */
    $command = $this->artisan('plan', ['project' => 'api', '--profile' => 'production', '--config' => 'shipper.yml.example']);
    \assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('Planning deployment')
        ->assertExitCode(0);
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
