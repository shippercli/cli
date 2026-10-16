<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use ShipperCli\Contracts\DeploymentProviderInterface;

final class TestBlockedPlanProvider implements DeploymentProviderInterface
{
    public static int $applyCalls = 0;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    public function validate(object $project, object $profile): array
    {
        return [];
    }

    public function plan(object $project, object $profile): array
    {
        return \array_key_exists('plan_blocked', $this->config)
            ? ['blocked' => $this->config['plan_blocked'], 'actions' => ['Ownership collision requires review.']]
            : ['provider' => $this->getName()];
    }

    public function apply(object $project, object $profile): bool
    {
        self::$applyCalls++;

        return true;
    }

    public function destroy(object $project, object $profile): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'blocked-test';
    }

    public function getLastError(): string
    {
        return '';
    }
}
