<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use ShipperCli\Contracts\DeploymentProviderInterface;

final class TestContractProvider implements DeploymentProviderInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config = [],
    ) {}

    public function validate(object $project, object $profile): array
    {
        return [];
    }

    public function plan(object $project, object $profile): array
    {
        return [
            'provider' => $this->getName(),
            'token' => $this->config['token'] ?? null,
        ];
    }

    public function apply(object $project, object $profile): bool
    {
        return true;
    }

    public function destroy(object $project, object $profile): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'contract-test';
    }

    public function getLastError(): string
    {
        return '';
    }
}
