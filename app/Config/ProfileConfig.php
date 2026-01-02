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
}
