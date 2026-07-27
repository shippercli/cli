<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\ResolveDeploymentContextAction;
use App\Deployment\ContractDeploymentProviderAdapter;
use Illuminate\Console\Command;
use ShipperCli\Contracts\DeploymentStatusProviderInterface;
use Throwable;

final class StatusCommand extends Command
{
    /** @var string */
    protected $signature = 'status
                            {project : Project name to inspect}
                            {--profile=production : Profile to use}
                            {--config=shipper.yml : Path to config file}';

    /** @var string */
    protected $description = 'Show the current deployment and provider resource state';

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
                || ! $provider->contractProvider() instanceof DeploymentStatusProviderInterface) {
                $this->error("Provider {$provider->getName()} does not support deployment status.");

                return self::FAILURE;
            }

            $status = $provider->contractProvider()->status($context['project'], $context['profile']);
            $this->line(\json_encode(
                $status,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
