<?php

declare(strict_types=1);

namespace App\Commands;

use App\Config\ConfigLoader;
use App\Providers\Deployment\ProviderFactory;
use Illuminate\Console\Command;

final class ValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'validate {--config=deployer.yml : Path to config file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate the deployer configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configPath = $this->option('config');
        \assert(\is_string($configPath));

        $this->info("Validating configuration: {$configPath}");

        try {
            $loader = new ConfigLoader($configPath);
            $config = $loader->load();

            $hasErrors = false;
            $providerFactory = new ProviderFactory($config->providers());

            foreach ($config->projects() as $project) {
                $this->line("  Checking project: {$project->name()}");

                try {
                    $provider = $providerFactory->create($project->provider());

                    foreach ($project->profiles() as $profile) {
                        $this->line("    Profile: {$profile->name()}");

                        $errors = $provider->validate($project, $profile);

                        if ($errors !== []) {
                            $hasErrors = true;
                            foreach ($errors as $error) {
                                $this->error("      ✗ {$error}");
                            }
                        } else {
                            $this->info('      ✓ Valid');
                        }
                    }
                } catch (\InvalidArgumentException $e) {
                    $hasErrors = true;
                    $this->error("    ✗ {$e->getMessage()}");
                }
            }

            if ($hasErrors) {
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
