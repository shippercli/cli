<?php

declare(strict_types=1);

namespace App\Config;

final class RedirectConfig
{
    public function __construct(
        private readonly string $from,
        private readonly string $to,
        private readonly int $type = 301,
        private readonly bool $enabled = true,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function from(): string
    {
        return $this->from;
    }

    public function to(): string
    {
        return $this->to;
    }

    public function type(): int
    {
        return $this->type;
    }
}