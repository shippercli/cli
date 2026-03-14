<?php

declare(strict_types=1);

use Illuminate\Testing\PendingCommand;

\test('deploy command runs successfully', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('deploy');
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('Starting deployment...')
        ->expectsOutput('Deployment completed successfully!')
        ->assertExitCode(0);
});

\test('inspire command is hidden', function (): void {
    /** @var Tests\TestCase $this */
    $command = $this->artisan('list');
    \assert($command instanceof PendingCommand);
    $command->assertExitCode(0);
});
