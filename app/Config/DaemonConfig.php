<?php

declare(strict_types=1);

namespace App\Config;

final class DaemonConfig
{
    public function __construct(
        private readonly string $command,
        private readonly string $user = 'ploi',
        private readonly int $processes = 1,
        private readonly string $directory = '',
        private readonly bool $enabled = true,
        private readonly int $restartDelay = 10,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function command(): string
    {
        return $this->command;
    }

    public function user(): string
    {
        return $this->user;
    }

    public function processes(): int
    {
        return $this->processes;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function restartDelay(): int
    {
        return $this->restartDelay;
    }
}