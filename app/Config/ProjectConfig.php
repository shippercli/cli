<?php

declare(strict_types=1);

namespace App\Config;

final class ProjectConfig
{
    /**
     * @param array<string, ProfileConfig> $profiles
     * @param array<string, mixed> $repository
     */
    public function __construct(
        private readonly string $name,
        private readonly string $provider,
        private readonly string $path,
        private readonly array $profiles,
        private readonly array $repository = [],
        private readonly string $webDirectory = '/public',
        private readonly string $projectRoot = '/',
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
}
