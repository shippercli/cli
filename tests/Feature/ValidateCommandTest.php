<?php

declare(strict_types=1);

use Illuminate\Testing\PendingCommand;

\test('validate command shows error for missing config', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('validate', ['--config' => 'nonexistent.yml']);
    \assert($command instanceof PendingCommand);
    $command->assertExitCode(1);
});

\test('validate command runs successfully with valid config', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('validate', ['--config' => 'shipper.yml']);
    \assert($command instanceof \Illuminate\Testing\PendingCommand);
    $command->assertExitCode(0);
});
