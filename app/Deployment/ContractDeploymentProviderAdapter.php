<?php

declare(strict_types=1);

namespace App\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use ShipperCli\Contracts\DeploymentProviderInterface as ContractProvider;

final readonly class ContractDeploymentProviderAdapter implements DeploymentProviderInterface
{
    public function __construct(
        private ContractProvider $provider,
    ) {}

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        return $this->provider->validate($project, $profile);
    }

    public function plan(ProjectConfig $project, ProfileConfig $profile): array
    {
        return $this->provider->plan($project, $profile);
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        return $this->provider->apply($project, $profile);
    }

    public function destroy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        return $this->provider->destroy($project, $profile);
    }

    public function getName(): string
    {
        return $this->provider->getName();
    }

    public function getLastError(): string
    {
        return $this->provider->getLastError();
    }

    public function contractProvider(): ContractProvider
    {
        return $this->provider;
    }
}
