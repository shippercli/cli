<?php

declare(strict_types=1);

namespace App\Config;

final class SslConfig
{
    public function __construct(
        private readonly bool $enabled = false,
        private readonly string $type = 'letsencrypt',
        private readonly bool $forceHttps = false,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function forceHttps(): bool
    {
        return $this->forceHttps;
    }
}