<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\ResolveDeploymentContextAction;
use App\Deployment\ContractDeploymentProviderAdapter;
use Illuminate\Console\Command;
use ShipperCli\Contracts\DeploymentRollbackProviderInterface;
use Throwable;

final class RollbackCommand extends Command
{
    /** @var string */
    protected $signature = 'rollback
                            {project : Project name to roll back}
                            {--profile=production : Profile to use}
                            {--config=shipper.yml : Path to config file}
                            {--release= : Release identifier to restore; defaults to latest}
                            {--force : Skip confirmation prompt}';

    /** @var string */
    protected $description = 'Restore a previous provider-managed deployment release';

    public function handle(): int
    {
        try {
            $context = \app(ResolveDeploymentContextAction::class)->handle(
                (string) $this->option('config'),
                (string) $this->argument('project'),
                (string) $this->option('profile'),
            );
            $provider = $context['provider'];
            if (! $provider instanceof ContractDeploymentProviderAdapter
                || ! $provider->contractProvider() instanceof DeploymentRollbackProviderInterface) {
                $this->error("Provider {$provider->getName()} does not support deployment rollback.");

                return self::FAILURE;
            }

            $projectName = (string) $this->argument('project');
            $profileName = (string) $this->option('profile');
            if (! (bool) $this->option('force')
                && ! $this->confirm("Roll back {$projectName} ({$profileName})?", false)) {
                $this->warn('Rollback cancelled.');

                return self::SUCCESS;
            }

            $release = \trim((string) $this->option('release'));
            $operation = $provider->contractProvider();
            $result = $operation->rollback(
                $context['project'],
                $context['profile'],
                $release === '' ? null : $release,
            );
            if (! $result) {
                $this->error($operation->getLastError() ?: 'Rollback failed.');

                return self::FAILURE;
            }

            $this->info('Rollback completed successfully.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
