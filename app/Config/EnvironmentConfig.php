<?php

declare(strict_types=1);

namespace App\Config;

final class EnvironmentConfig
{
    /**
     * @param array<string, string> $variables
     */
    public function __construct(
        private readonly array $variables = [],
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->variables);
    }

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        return $this->variables;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->variables[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->variables[$key]);
    }

    public function mergeWith(EnvironmentConfig $other): EnvironmentConfig
    {
        return new EnvironmentConfig(
            array_merge($this->variables, $other->variables),
        );
    }
}