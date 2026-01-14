<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\FormatsDeploymentPlan;
use App\Flows\ApplyDeploymentFlow;
use Illuminate\Console\Command;

final class ApplyCommand extends Command
{
    use FormatsDeploymentPlan;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apply 
                            {project : Project name to deploy} 
                            {--profile=production : Profile to use}
                            {--config=shipper.yml : Path to config file}
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
        $projectName = $this->argument('project');
        $profileName = $this->option('profile');
        $force = $this->option('force');

        try {
            $flow = new ApplyDeploymentFlow;

            // First, plan the deployment (no execution yet)
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

            $this->info("Deploying {$projectName} ({$profileName})...");
            $this->line('');

            $this->info('Deployment Configuration:');
            $this->line('  Provider: '.$this->getPlanValue($plan, 'provider'));
            $this->line('  Branch:   '.$this->getPlanValue($plan, 'branch'));
            $this->line('  Path:     '.$this->getPlanValue($plan, 'path'));
            $this->line('');

            // Get confirmation BEFORE executing
            if (! $force && ! $this->confirm('Do you want to continue?', false)) {
                $this->warn('Deployment cancelled.');

                return self::SUCCESS;
            }

            $this->info('Executing deployment...');
            $this->line('');

            $this->comment('Debug Information:');
            $this->line('  Server ID: '.$this->getPlanValue($plan, 'server_id'));
            $this->line('  Domain:    '.$this->getPlanValue($plan, 'domain'));
            $this->line('');

            $this->comment('Triggering deployment and waiting for completion...');
            $this->line('');

            // Now execute the deployment
            $executeResult = $flow->execute($provider, $project, $profile, $plan);

            if ($executeResult['success']) {
                $this->info('✓ Deployment completed successfully!');

                if ($executeResult['logs'] !== []) {
                    $this->line('');
                    $this->info('Deployment Logs:');
                    $this->line('');
                    foreach ($executeResult['logs'] as $log) {
                        $this->line("  {$log}");
                    }
                }

                return self::SUCCESS;
            }

            $this->error('✗ Deployment failed!');

            if ($executeResult['error_message'] !== '') {
                $this->line('');
                $this->error('Error Details:');
                $this->line("  {$executeResult['error_message']}");
            }

            if ($executeResult['logs'] !== []) {
                $this->line('');
                $this->info('Deployment Logs:');
                $this->line('');
                foreach ($executeResult['logs'] as $log) {
                    $this->line("  {$log}");
                }
            }

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Deployment failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
