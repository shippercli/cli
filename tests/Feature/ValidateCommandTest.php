<?php

declare(strict_types=1);

use Illuminate\Testing\PendingCommand;

\test('validate command runs successfully with valid config', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('validate', ['--config' => 'shipper.yml']);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Validating configuration: shipper.yml')
        ->assertExitCode(0);
});

\test('validate command shows error for missing config', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('validate', ['--config' => 'nonexistent.yml']);
    \assert($command instanceof PendingCommand);
    $command->assertExitCode(1);
});
