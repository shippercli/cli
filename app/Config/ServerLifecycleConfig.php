<?php

declare(strict_types=1);

namespace App\Config;

final class ServerLifecycleConfig
{
    /**
     * @param array<string, mixed> $spec
     */
    public function __construct(
        private readonly string $mode,
        private readonly ?string $id = null,
        private readonly ?string $cleanup = null,
        private readonly ?string $ttl = null,
        private readonly array $spec = [],
    ) {}

    public function mode(): string
    {
        return $this->mode;
    }

    public function isExisting(): bool
    {
        return $this->mode === 'existing';
    }

    public function isCreate(): bool
    {
        return $this->mode === 'create';
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function cleanup(): ?string
    {
        return $this->cleanup;
    }

    public function ttl(): ?string
    {
        return $this->ttl;
    }

    /**
     * @return array<string, mixed>
     */
    public function spec(): array
    {
        return $this->spec;
    }

    public function specValue(string $key, mixed $default = null): mixed
    {
        return $this->spec[$key] ?? $default;
    }
}
