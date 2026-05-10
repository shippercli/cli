<?php

declare(strict_types=1);

namespace App\Deployment;

final class ProviderFactory
{
    /** @var array<string, mixed> */
    private readonly array $providersConfig;

    /** @param array<string, mixed> $providersConfig */
    public function __construct(array $providersConfig = [])
    {
        $this->providersConfig = $providersConfig;
    }

    public function create(string $providerName): DeploymentProviderInterface
    {
        $config = $this->providersConfig[$providerName] ?? [];

        $className = ProviderRegistry::get($providerName);

        if ($className === null) {
            throw new \InvalidArgumentException("Unknown provider: {$providerName}");
        }

        /** @var DeploymentProviderInterface */
        return new $className($config);
    }
}
