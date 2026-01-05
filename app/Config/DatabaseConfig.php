<?php

declare(strict_types=1);

namespace App\Config;

final class DatabaseConfig
{
    public function __construct(
        private readonly string $name,
        private readonly string $user,
        private readonly string $type = 'mysql',
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function user(): string
    {
        return $this->user;
    }

    public function type(): string
    {
        return $this->type;
    }
}
