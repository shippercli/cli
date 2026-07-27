<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\ResolveDeploymentContextAction;
use App\Deployment\ContractDeploymentProviderAdapter;
use Illuminate\Console\Command;
use ShipperCli\Contracts\DeploymentLogsProviderInterface;
use Throwable;

final class LogsCommand extends Command
{
    /** @var string */
    protected $signature = 'logs
                            {project : Project name to inspect}
                            {--profile=production : Profile to use}
                            {--config=shipper.yml : Path to config file}
                            {--lines=100 : Maximum log lines to return}';

    /** @var string */
    protected $description = 'Show recent provider or application logs';

    public function handle(): int
    {
        try {
            $lines = (int) $this->option('lines');
            if ($lines < 1 || $lines > 5000) {
                $this->error('The --lines option must be between 1 and 5000.');

                return self::FAILURE;
            }

            $context = \app(ResolveDeploymentContextAction::class)->handle(
                (string) $this->option('config'),
                (string) $this->argument('project'),
                (string) $this->option('profile'),
            );
            $provider = $context['provider'];
            if (! $provider instanceof ContractDeploymentProviderAdapter
                || ! $provider->contractProvider() instanceof DeploymentLogsProviderInterface) {
                $this->error("Provider {$provider->getName()} does not support deployment logs.");

                return self::FAILURE;
            }

            foreach ($provider->contractProvider()->logs($context['project'], $context['profile'], $lines) as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
