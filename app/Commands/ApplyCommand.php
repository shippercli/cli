<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\FormatsDeploymentPlan;
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
            $flow = new \App\Flows\ApplyDeploymentFlow;
            $result = $flow->handle($configPath, $projectName, $profileName);

            if (! $result['success'] && $result['errors'] !== []) {
                $this->error('Configuration validation failed:');
                foreach ($result['errors'] as $error) {
                    $this->error("  ✗ {$error}");
                }

                return self::FAILURE;
            }

            if (! $result['success'] && $result['project'] === null) {
                $this->error($result['error_message']);

                return self::FAILURE;
            }

            if (! $result['success'] && $result['profile'] === null) {
                $this->error($result['error_message']);

                return self::FAILURE;
            }

            $plan = $result['plan'];

            $this->info("Deploying {$projectName} ({$profileName})...");
            $this->line('');

            $this->info('Deployment Configuration:');
            $this->line('  Provider: '.$this->getPlanValue($plan, 'provider'));
            $this->line('  Branch:   '.$this->getPlanValue($plan, 'branch'));
            $this->line('  Path:     '.$this->getPlanValue($plan, 'path'));
            $this->line('');

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

            if ($result['success']) {
                $this->info('✓ Deployment completed successfully!');

                if ($result['logs'] !== []) {
                    $this->line('');
                    $this->info('Deployment Logs:');
                    $this->line('');
                    foreach ($result['logs'] as $log) {
                        $this->line("  {$log}");
                    }
                }

                return self::SUCCESS;
            }

            $this->error('✗ Deployment failed!');

            if ($result['error_message'] !== '') {
                $this->line('');
                $this->error('Error Details:');
                $this->line("  {$result['error_message']}");
            }

            if ($result['logs'] !== []) {
                $this->line('');
                $this->info('Deployment Logs:');
                $this->line('');
                foreach ($result['logs'] as $log) {
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
