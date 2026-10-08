<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\LoadConfigurationAction;
use App\Config\ProfileConfig;
use App\Deployment\ProviderFactory;
use App\Flows\DestroyDeploymentFlow;
use App\Flows\ServerLifecycleRuntime;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class CleanupExpiredServersCommand extends Command
{
    protected $signature = 'servers:cleanup-expired {--config=shipper.yml : Path to Shipper configuration}';

    protected $description = 'Destroy expired Shipper-managed servers configured with cleanup: destroy';

    public function handle(): int
    {
        $configPath = (string) $this->option('config');
        $config = (new LoadConfigurationAction)->handle($configPath);
        $failed = false;

        foreach ($config->projects() as $project) {
            foreach ($project->profiles() as $profile) {
                if (! $profile instanceof ProfileConfig) {
                    continue;
                }
                $serverConfig = $profile->server();
                if ($serverConfig === null || ! $serverConfig->isCreate()
                    || $serverConfig->cleanup() !== 'destroy' || $serverConfig->ttl() === null) {
                    continue;
                }
                $ttlSeconds = $serverConfig->ttlSeconds();
                if ($ttlSeconds === null) {
                    $this->error("{$project->name()}/{$profile->name()}: invalid server TTL '{$serverConfig->ttl()}'. Use a positive integer or a duration such as 72h.");
                    $failed = true;
                    continue;
                }

                try {
                    $provider = (new ProviderFactory($config->providers()))->create($project->provider());
                    $lifecycleProvider = ServerLifecycleRuntime::provider($provider);
                    if ($lifecycleProvider === null) {
                        $this->warn("{$project->name()}/{$profile->name()}: provider has no server lifecycle API; skipped.");
                        continue;
                    }
                    $resolved = ServerLifecycleRuntime::resolve($provider, $profile);
                    if ($resolved['error'] !== '') {
                        $this->error("{$project->name()}/{$profile->name()}: {$resolved['error']}");
                        $failed = true;
                        continue;
                    }
                    $server = $resolved['server'];
                    if ($server === null) {
                        continue;
                    }
                    if (($server['server_record_only'] ?? false) === true) {
                        $this->warn("{$project->name()}/{$profile->name()}: Shipper cannot prove ownership of this server resource; workloads were left intact.");
                        continue;
                    }
                    $createdAt = $server['created_at'] ?? null;
                    if (! \is_string($createdAt) || \trim($createdAt) === '') {
                        $this->warn("{$project->name()}/{$profile->name()}: provider returned no server creation time; skipped.");
                        continue;
                    }
                    $created = new DateTimeImmutable($createdAt);
                    if ((new DateTimeImmutable)->getTimestamp() < $created->getTimestamp() + $ttlSeconds) {
                        continue;
                    }

                    $flow = new DestroyDeploymentFlow;
                    $plan = $flow->handle($configPath, $project->name(), $profile->name());
                    if (! $plan['success']) {
                        $this->error("{$project->name()}/{$profile->name()}: {$plan['error_message']}");
                        $failed = true;
                        continue;
                    }
                    \assert($plan['provider'] !== null && $plan['project'] !== null && $plan['profile'] !== null);
                    $result = $flow->execute($plan['provider'], $plan['project'], $plan['profile']);
                    if (! $result['success']) {
                        $this->error("{$project->name()}/{$profile->name()}: {$result['error_message']}");
                        $failed = true;
                    } else {
                        $this->info("{$project->name()}/{$profile->name()}: expired deployment and managed server destroyed.");
                    }
                } catch (\Throwable $exception) {
                    $this->error("{$project->name()}/{$profile->name()}: {$exception->getMessage()}");
                    $failed = true;
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

}
