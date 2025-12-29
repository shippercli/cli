<?php

declare(strict_types=1);

namespace App\Commands;

use App\Config\ConfigLoader;
use App\Providers\Deployment\ProviderFactory;
use Illuminate\Console\Command;

final class PlanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plan 
                            {project : Project name to plan} 
                            {--profile=production : Profile to use}
                            {--config=deployer.yml : Path to config file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Plan a deployment (dry-run)';

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

            // First validate
            $errors = $provider->validate($project, $profile);
            if ($errors !== []) {
                $this->error('Configuration validation failed:');
                foreach ($errors as $error) {
                    $this->error("  ✗ {$error}");
                }

                return self::FAILURE;
            }

            // Then plan
            $this->info("Planning deployment for {$projectName} ({$profileName})...");
            $this->line('');

            $plan = $provider->plan($project, $profile);

            $this->info('Deployment Plan:');
            $provider = $plan['provider'] ?? 'unknown';
            $this->line('  Provider: '.(\is_scalar($provider) ? (string) $provider : 'unknown'));
            $project = $plan['project'] ?? 'unknown';
            $this->line('  Project:  '.(\is_scalar($project) ? (string) $project : 'unknown'));
            $profile = $plan['profile'] ?? 'unknown';
            $this->line('  Profile:  '.(\is_scalar($profile) ? (string) $profile : 'unknown'));
            $branch = $plan['branch'] ?? 'unknown';
            $this->line('  Branch:   '.(\is_scalar($branch) ? (string) $branch : 'unknown'));
            $path = $plan['path'] ?? 'unknown';
            $this->line('  Path:     '.(\is_scalar($path) ? (string) $path : 'unknown'));

            if (isset($plan['server_id']) && \is_scalar($plan['server_id'])) {
                $this->line('  Server:   '.(string) $plan['server_id']);
            }
            if (isset($plan['site_id']) && \is_scalar($plan['site_id'])) {
                $this->line('  Site:     '.(string) $plan['site_id']);
            }

            $this->line('');
            $this->info('Actions:');
            if (isset($plan['actions']) && \is_array($plan['actions'])) {
                foreach ($plan['actions'] as $action) {
                    if (\is_string($action)) {
                        $this->line("  • {$action}");
                    }
                }
            }

            if (isset($plan['note']) && \is_string($plan['note'])) {
                $this->line('');
                $this->comment($plan['note']);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Plan failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
