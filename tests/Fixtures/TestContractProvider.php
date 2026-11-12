<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use ShipperCli\Contracts\DeploymentLogsProviderInterface;
use ShipperCli\Contracts\DeploymentProviderInterface;
use ShipperCli\Contracts\DeploymentRollbackProviderInterface;
use ShipperCli\Contracts\DeploymentStatusProviderInterface;

final class TestContractProvider implements DeploymentLogsProviderInterface, DeploymentProviderInterface, DeploymentRollbackProviderInterface, DeploymentStatusProviderInterface
{
    public static int $cleanupListings = 0;

    public static int $cleanupDeletions = 0;

    public function listSites(object $project, object $profile): mixed
    {
        self::$cleanupListings++;

        return $this->config['cleanup_sites'] ?? [['site_id' => 42, 'domain' => '42.preview.example.com']];
    }

    public function deleteSiteWithDatabases(object $project, object $profile, int $siteId): bool
    {
        self::$cleanupDeletions++;

        return true;
    }

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

    public function status(object $project, object $profile): array
    {
        return [
            'state' => 'healthy',
            'release' => 'release-20260727-001',
        ];
    }

    public function logs(object $project, object $profile, int $lines = 100): array
    {
        return \array_slice([
            'application booted',
            'request completed',
        ], 0, $lines);
    }

    public function rollback(object $project, object $profile, ?string $release = null): bool
    {
        return true;
    }
}
