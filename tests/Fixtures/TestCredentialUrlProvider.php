<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use ShipperCli\Contracts\DeploymentLogsProviderInterface;
use ShipperCli\Contracts\DeploymentProviderInterface;
use ShipperCli\Contracts\DeploymentRollbackProviderInterface;
use ShipperCli\Contracts\DeploymentStatusProviderInterface;

final class TestCredentialUrlProvider implements DeploymentLogsProviderInterface, DeploymentProviderInterface, DeploymentRollbackProviderInterface, DeploymentStatusProviderInterface
{
    public const URL = 'https://fixture-user:fixture-secret@example.test/repo.git';

    public static string $receivedUrl = '';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    public function validate(object $project, object $profile): array
    {
        return ($this->config['validation_error'] ?? false) === true ? ['Invalid source: '.self::URL] : [];
    }

    public function plan(object $project, object $profile): array
    {
        return ['repository' => self::URL, 'actions' => ['Clone '.self::URL], 'note' => 'Source: '.self::URL];
    }

    public function apply(object $project, object $profile): bool
    {
        self::$receivedUrl = \is_string($this->config['url'] ?? null) ? $this->config['url'] : '';

        return false;
    }

    public function destroy(object $project, object $profile): bool
    {
        return false;
    }

    public function rollback(object $project, object $profile, ?string $release = null): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'credential-url-test';
    }

    public function getLastError(): string
    {
        return 'Request failed for '.self::URL;
    }

    public function logs(object $project, object $profile, int $lines = 100): array
    {
        return ['Request to '.self::URL];
    }

    public function status(object $project, object $profile): array
    {
        return ['repository' => self::URL];
    }
}
