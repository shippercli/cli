<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

final class ProviderFactory
{
    /**
     * @param array<string, mixed> $providersConfig
     */
    public function __construct(
        private readonly array $providersConfig = [],
    ) {}

    public function create(string $providerName): DeploymentProviderInterface
    {
        $config = $this->providersConfig[$providerName] ?? [];
        
        if (! \is_array($config)) {
            $config = [];
        }

        return match ($providerName) {
            'ploi' => new PloiProvider($config),
            default => throw new \InvalidArgumentException("Unknown provider: {$providerName}"),
        };
    }
}
