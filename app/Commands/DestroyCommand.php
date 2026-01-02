<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\FormatsDeploymentPlan;
use App\Config\ConfigLoader;
use App\Providers\Deployment\ProviderFactory;
use Illuminate\Console\Command;

final class DestroyCommand extends Command
{
    use FormatsDeploymentPlan;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'destroy 
                            {project : Project name to destroy} 
                            {--profile=production : Profile to use}
                            {--config=deployer.yml : Path to config file}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Destroy a deployment';

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

            // Show what will be destroyed
            $this->info("Destroying {$projectName} ({$profileName})...");
            $this->line('');

            $plan = $provider->plan($project, $profile);
            $this->info('Deployment Configuration:');
            $this->line('  Provider: '.$this->getPlanValue($plan, 'provider'));
            $this->line('  Domain:   '.$this->getPlanValue($plan, 'domain'));
            $this->line('  Branch:   '.$this->getPlanValue($plan, 'branch'));
            $this->line('');

            // Confirm
            if (! $force && ! $this->confirm('Do you want to destroy this site?', false)) {
                $this->warn('Destroy cancelled.');

                return self::SUCCESS;
            }

            // Destroy
            $this->info('Destroying site...');
            $this->line('');

            $result = $provider->destroy($project, $profile);

            if ($result) {
                $this->info('✓ Site destroyed successfully!');

                return self::SUCCESS;
            }

            $this->error('✗ Destroy failed!');

            // Display error details if available
            $errorMessage = $provider->getLastError();
            if ($errorMessage !== '') {
                $this->line('');
                $this->error('Error Details:');
                $this->line("  {$errorMessage}");
            }

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Destroy failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
