<?php

declare(strict_types=1);

namespace App\Config;

final class ShipperConfig
{
    /**
     * @param array<string, ProjectConfig> $projects
     * @param array<string, mixed> $providers
     */
    public function __construct(
        private readonly array $projects,
        private readonly array $providers,
    ) {}

    /**
     * @return array<string, ProjectConfig>
     */
    public function projects(): array
    {
        return $this->projects;
    }

    /**
     * @return array<string, mixed>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    public function getProject(string $name): ?ProjectConfig
    {
        return $this->projects[$name] ?? null;
    }
}
