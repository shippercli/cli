<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\FormatsDeploymentPlan;
use App\Flows\DestroyDeploymentFlow;
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
            $flow = new DestroyDeploymentFlow;

            // First, plan the destruction (no execution yet)
            $planResult = $flow->handle($configPath, $projectName, $profileName);

            if (! $planResult['success'] && $planResult['errors'] !== []) {
                $this->error('Configuration validation failed:');
                foreach ($planResult['errors'] as $error) {
                    $this->error("  ✗ {$error}");
                }

                return self::FAILURE;
            }

            if (! $planResult['success']) {
                $this->error($planResult['error_message']);

                return self::FAILURE;
            }

            $plan = $planResult['plan'];
            $project = $planResult['project'];
            $profile = $planResult['profile'];
            $provider = $planResult['provider'];

            // These should be set if success is true, but assert for type safety
            \assert($project !== null);
            \assert($profile !== null);
            \assert($provider !== null);

            $this->info("Destroying {$projectName} ({$profileName})...");
            $this->line('');

            $this->info('Deployment Configuration:');
            $this->line('  Provider: '.$this->getPlanValue($plan, 'provider'));
            $this->line('  Domain:   '.$this->getPlanValue($plan, 'domain'));
            $this->line('  Branch:   '.$this->getPlanValue($plan, 'branch'));
            $this->line('');

            // Get confirmation BEFORE destroying
            if (! $force && ! $this->confirm('Do you want to destroy this site?', false)) {
                $this->warn('Destroy cancelled.');

                return self::SUCCESS;
            }

            $this->info('Destroying site...');
            $this->line('');

            // Now execute the destruction
            $executeResult = $flow->execute($provider, $project, $profile);

            if ($executeResult['success']) {
                $this->info('✓ Site destroyed successfully!');

                return self::SUCCESS;
            }

            $this->error('✗ Destroy failed!');

            if ($executeResult['error_message'] !== '') {
                $this->line('');
                $this->error('Error Details:');
                $this->line("  {$executeResult['error_message']}");
            }

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Destroy failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
