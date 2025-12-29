<?php

declare(strict_types=1);

namespace App\Commands;

use App\Config\ConfigLoader;
use App\Providers\Deployment\ProviderFactory;
use Illuminate\Console\Command;

final class ApplyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apply 
                            {project : Project name to deploy} 
                            {--profile=production : Profile to use}
                            {--config=deployer.yml : Path to config file}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute a deployment';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configPath = $this->option('config');
        \assert(\is_string($configPath));

        $projectName = $this->argument('project');
        \assert(\is_string($projectName));

        $profileName = $this->option('profile');
        \assert(\is_string($profileName));

        $force = $this->option('force');
        \assert(\is_bool($force));

        try {
            $loader = new ConfigLoader($configPath);
            $config = $loader->load();

            $project = $config->getProject($projectName);
            if ($project === null) {
                $this->error("Project not found: {$projectName}");

                return self::FAILURE;
            }

            $profile = $project->getProfile($profileName);
            if ($profile === null) {
                $this->error("Profile not found: {$profileName}");

                return self::FAILURE;
            }

            $providerFactory = new ProviderFactory($config->providers());
            $provider = $providerFactory->create($project->provider());

            // Validate first
            $errors = $provider->validate($project, $profile);
            if ($errors !== []) {
                $this->error('Configuration validation failed:');
                foreach ($errors as $error) {
                    $this->error("  ✗ {$error}");
                }

                return self::FAILURE;
            }

            // Show plan
            $this->info("Deploying {$projectName} ({$profileName})...");
            $this->line('');

            $plan = $provider->plan($project, $profile);
            $this->info('Deployment Configuration:');
            $planProvider = $plan['provider'] ?? 'unknown';
            $this->line('  Provider: '.(\is_scalar($planProvider) ? (string) $planProvider : 'unknown'));
            $planBranch = $plan['branch'] ?? 'unknown';
            $this->line('  Branch:   '.(\is_scalar($planBranch) ? (string) $planBranch : 'unknown'));
            $planPath = $plan['path'] ?? 'unknown';
            $this->line('  Path:     '.(\is_scalar($planPath) ? (string) $planPath : 'unknown'));
            $this->line('');

            // Confirm
            if (! $force && ! $this->confirm('Do you want to continue?', false)) {
                $this->warn('Deployment cancelled.');

                return self::SUCCESS;
            }

            // Apply
            $this->info('Executing deployment...');
            $result = $provider->apply($project, $profile);

            if ($result) {
                $this->info('✓ Deployment completed successfully!');

                return self::SUCCESS;
            }

            $this->error('✗ Deployment failed!');

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Deployment failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
