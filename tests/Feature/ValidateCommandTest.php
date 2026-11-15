<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ValidateCommandTest extends TestCase
{
    public function test_validate_shows_error_for_missing_config(): void
    {
        $exitCode = Artisan::call('validate', ['--config' => 'nonexistent.yml']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to load configuration: Config file not found: nonexistent.yml', Artisan::output());
    }

    public function test_validate_runs_successfully_with_valid_config(): void
    {
        $exitCode = Artisan::call('validate', ['--config' => __DIR__.'/../../test-validate.yml']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('✓ Configuration is valid!', Artisan::output());
    }

    public function test_validate_reports_provider_and_profile_errors(): void
    {
        $exitCode = Artisan::call('validate', ['--config' => __DIR__.'/../Fixtures/invalid-validation-config.yml']);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Checking project: unknown', $output);
        $this->assertStringContainsString('✗ Unknown provider: not-registered', $output);
        $this->assertStringContainsString('Checking project: api', $output);
        $this->assertStringContainsString('Profile: production', $output);
        $this->assertStringContainsString('✗ Domain is required for profile', $output);
        $this->assertStringContainsString('Configuration validation failed!', $output);
    }
}
