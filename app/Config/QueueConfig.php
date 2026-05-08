<?php

declare(strict_types=1);

namespace App\Config;

final class QueueConfig
{
    public function __construct(
        private readonly bool $enabled = false,
        private readonly string $connection = 'redis',
        private readonly string $queue = 'default',
        private readonly int $processes = 1,
        private readonly int $maxTries = 3,
        private readonly int $timeout = 60,
        private readonly bool $restartOnDeploy = true,
        private readonly int $maxSeconds = 60,
        private readonly int $sleep = 30,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function connection(): string
    {
        return $this->connection;
    }

    public function queue(): string
    {
        return $this->queue;
    }

    public function processes(): int
    {
        return $this->processes;
    }

    public function maxTries(): int
    {
        return $this->maxTries;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function restartOnDeploy(): bool
    {
        return $this->restartOnDeploy;
    }

    public function maxSeconds(): int
    {
        return $this->maxSeconds;
    }

    public function sleep(): int
    {
        return $this->sleep;
    }
}