<?php

declare(strict_types=1);

namespace App\Commands;

use App\Flows\Validation\ValidateConfigurationFlow;
use Illuminate\Console\Command;

final class ValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'validate {--config=shipper.yml : Path to config file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate the shipper configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configPath = $this->option('config');
        \assert(\is_string($configPath));

        $this->info("Validating configuration: {$configPath}");

        try {
            $flow = new ValidateConfigurationFlow;
            $result = $flow->handle($configPath);

            foreach ($result['errors'] as $projectName => $projectErrors) {
                $this->line("  Checking project: {$projectName}");

                foreach ($projectErrors as $profileName => $errors) {
                    if ($profileName === '_provider') {
                        foreach ($errors as $error) {
                            $this->error("    ✗ {$error}");
                        }
                    } else {
                        $this->line("    Profile: {$profileName}");
                        foreach ($errors as $error) {
                            $this->error("      ✗ {$error}");
                        }
                    }
                }
            }

            if (! $result['success']) {
                $this->error('Configuration validation failed!');

                return self::FAILURE;
            }

            $this->info('✓ Configuration is valid!');

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error("Failed to load configuration: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
