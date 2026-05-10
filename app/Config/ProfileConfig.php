<?php

declare(strict_types=1);

namespace App\Config;

final class ProfileConfig
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly string $name,
        private readonly string $branch,
        private readonly array $config,
        private readonly ?EnvironmentConfig $environment = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function branch(): string
    {
        return $this->branch;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function environment(): EnvironmentConfig
    {
        return $this->environment ?? new EnvironmentConfig;
    }

    /**
     * @return array<string>
     */
    public function aliases(): array
    {
        $aliases = $this->config['aliases'] ?? null;

        if (! \is_array($aliases)) {
            return [];
        }

        /** @var array<string> */
        return \array_filter($aliases, '\is_string');
    }

    public function deployScript(): ?string
    {
        $script = $this->config['deploy_script'] ?? null;

        return \is_string($script) ? $script : null;
    }
}
