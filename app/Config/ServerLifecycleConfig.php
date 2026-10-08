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

    public function ttlSeconds(): ?int
    {
        if ($this->ttl === null) {
            return null;
        }
        if (\ctype_digit($this->ttl)) {
            $seconds = \filter_var($this->ttl, FILTER_VALIDATE_INT);

            return \is_int($seconds) && $seconds > 0 ? $seconds : null;
        }
        if (! \preg_match('/^([1-9][0-9]*)([smhd])$/i', $this->ttl, $matches)) {
            return null;
        }
        $value = \filter_var($matches[1], FILTER_VALIDATE_INT);
        if (! \is_int($value)) {
            return null;
        }
        $factor = match (\strtolower($matches[2])) {
            's' => 1,
            'm' => 60,
            'h' => 3600,
            'd' => 86400,
        };

        return $value <= \intdiv(PHP_INT_MAX, $factor) ? $value * $factor : null;
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
