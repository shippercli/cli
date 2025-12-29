<?php

declare(strict_types=1);

namespace App\Config;

final class ProjectConfig
{
    /**
     * @param array<string, ProfileConfig> $profiles
     */
    public function __construct(
        private readonly string $name,
        private readonly string $provider,
        private readonly string $path,
        private readonly array $profiles,
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
}
