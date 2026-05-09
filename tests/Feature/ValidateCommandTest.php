<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

final class ValidateCommandTest extends Tests\TestCase
{
    public function test_validate_shows_error_for_missing_config(): void
    {
        $process = new Process(['php', 'shipper', 'validate', '--config', 'nonexistent.yml']);
        $process->run();
        $this->assertEquals(1, $process->getExitCode());
    }

    public function test_validate_runs_successfully_with_valid_config(): void
    {
        $process = new Process(['php', 'shipper', 'validate', '--config', 'shipper.yml']);
        $process->run();
        $this->assertEquals(0, $process->getExitCode(), 'Expected exit 0, got ' . $process->getExitCode() . '. Output: ' . $process->getOutput() . '. Error: ' . $process->getErrorOutput());
    }
}
