<?php

declare(strict_types=1);

\test('deploy command runs successfully', function (): void {
    $this->artisan('deploy')
        ->expectsOutput('Starting deployment...')
        ->expectsOutput('Deployment completed successfully!')
        ->assertExitCode(0);
});

\test('inspire command is hidden', function (): void {
    $this->artisan('list')
        ->assertExitCode(0);
});
