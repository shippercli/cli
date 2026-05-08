<?php

declare(strict_types=1);

namespace App\Config;

final class CronConfig
{
    public function __construct(
        private readonly string $command,
        private readonly string $frequency = 'daily',
        private readonly string $user = 'ploi',
        private readonly bool $enabled = true,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function command(): string
    {
        return $this->command;
    }

    public function frequency(): string
    {
        return $this->frequency;
    }

    public function user(): string
    {
        return $this->user;
    }
}