<?php

declare(strict_types=1);

namespace App\Deployment;

final class OperationResult
{
    private function __construct(
        private readonly bool $ok,
        private readonly string $error = '',
    ) {}

    public static function ok(): self
    {
        return new self(true);
    }

    public static function fail(string $error): self
    {
        return new self(false, $error);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function error(): string
    {
        return $this->error;
    }
}
