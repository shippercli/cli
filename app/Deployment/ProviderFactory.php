<?php

declare(strict_types=1);

namespace App\Deployment;

use ShipperCli\Contracts\DeploymentProviderInterface as ContractProvider;

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

        $provider = new $className($config);

        if ($provider instanceof DeploymentProviderInterface) {
            return $provider;
        }

        if ($provider instanceof ContractProvider) {
            return new ContractDeploymentProviderAdapter($provider);
        }

        throw new \UnexpectedValueException(
            "Provider {$className} must implement ".DeploymentProviderInterface::class
            .' or '.ContractProvider::class.'.',
        );
    }
}
