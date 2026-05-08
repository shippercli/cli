<?php

declare(strict_types=1);

namespace App\Config;

final class NetworkRuleConfig
{
    public function __construct(
        private readonly string $name,
        private readonly int $port,
        private readonly string $type = 'tcp',
        private readonly string $ruleType = 'allow',
        private readonly ?string $fromIp = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function ruleType(): string
    {
        return $this->ruleType;
    }

    public function fromIp(): ?string
    {
        return $this->fromIp;
    }
}