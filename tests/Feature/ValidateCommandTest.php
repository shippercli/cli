<?php

declare(strict_types=1);

\test('validate command runs successfully with valid config', function (): void {
    $this->artisan('validate', ['--config' => 'deployer.yml'])
        ->expectsOutput('Validating configuration: deployer.yml')
        ->assertExitCode(0);
});

\test('validate command shows error for missing config', function (): void {
    $this->artisan('validate', ['--config' => 'nonexistent.yml'])
        ->assertExitCode(1);
});
