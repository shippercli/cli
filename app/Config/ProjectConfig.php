<?php

declare(strict_types=1);

namespace App\Config;

final class ProjectConfig
{
    /**
     * @param array<string, ProfileConfig> $profiles
     * @param array<string, mixed> $repository
     * @param array<string, DatabaseConfig> $databases
     * @param array<string, QueueConfig> $queues
     * @param array<string, CronConfig> $cron
     * @param array<string, DaemonConfig> $daemons
     * @param array<string, NetworkRuleConfig> $networkRules
     * @param array<string, RedirectConfig> $redirects
     */
    public function __construct(
        private readonly string $name,
        private readonly string $provider,
        private readonly string $path,
        private readonly array $profiles,
        private readonly array $repository = [],
        private readonly string $webDirectory = '/public',
        private readonly string $projectRoot = '/',
        private readonly array $databases = [],
        private readonly ?SslConfig $ssl = null,
        private readonly ?EnvironmentConfig $environment = null,
        private readonly string $deployScript = '',
        private readonly array $queues = [],
        private readonly array $cron = [],
        private readonly array $daemons = [],
        private readonly array $networkRules = [],
        private readonly array $redirects = [],
        private readonly string $phpVersion = '8.3',
        private readonly ?string $nginxConfig = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, ProfileConfig>
     */
    public function profiles(): array
    {
        return $this->profiles;
    }

    public function getProfile(string $name): ?ProfileConfig
    {
        return $this->profiles[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function repository(): array
    {
        return $this->repository;
    }

    public function webDirectory(): string
    {
        return $this->webDirectory;
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    /**
     * @return array<string, DatabaseConfig>
     */
    public function databases(): array
    {
        return $this->databases;
    }

    public function getDatabase(string $name): ?DatabaseConfig
    {
        return $this->databases[$name] ?? null;
    }

    public function ssl(): SslConfig
    {
        return $this->ssl ?? new SslConfig();
    }

    public function environment(): EnvironmentConfig
    {
        return $this->environment ?? new EnvironmentConfig();
    }

    public function deployScript(): string
    {
        return $this->deployScript;
    }

    /**
     * @return array<string, QueueConfig>
     */
    public function queues(): array
    {
        return $this->queues;
    }

    public function getQueue(string $name): ?QueueConfig
    {
        return $this->queues[$name] ?? null;
    }

    /**
     * @return array<string, CronConfig>
     */
    public function cron(): array
    {
        return $this->cron;
    }

    public function getCron(string $name): ?CronConfig
    {
        return $this->cron[$name] ?? null;
    }

    /**
     * @return array<string, DaemonConfig>
     */
    public function daemons(): array
    {
        return $this->daemons;
    }

    public function getDaemon(string $name): ?DaemonConfig
    {
        return $this->daemons[$name] ?? null;
    }

    /**
     * @return array<string, NetworkRuleConfig>
     */
    public function networkRules(): array
    {
        return $this->networkRules;
    }

    public function getNetworkRule(string $name): ?NetworkRuleConfig
    {
        return $this->networkRules[$name] ?? null;
    }

    /**
     * @return array<string, RedirectConfig>
     */
    public function redirects(): array
    {
        return $this->redirects;
    }

    public function getRedirect(string $name): ?RedirectConfig
    {
        return $this->redirects[$name] ?? null;
    }

    public function phpVersion(): string
    {
        return $this->phpVersion;
    }

    public function nginxConfig(): ?string
    {
        return $this->nginxConfig;
    }
}