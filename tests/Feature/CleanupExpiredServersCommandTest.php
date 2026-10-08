<?php

declare(strict_types=1);

use App\Deployment\ProviderRegistry;
use Illuminate\Testing\PendingCommand;
use ShipperCli\Contracts\DeploymentProviderInterface;
use ShipperCli\Contracts\ServerLifecycleProviderInterface;
use Tests\TestCase;

final class CleanupExpiryTestProvider implements DeploymentProviderInterface, ServerLifecycleProviderInterface
{
    /** @var array<string, mixed> */
    public static array $resolvedServer = [];
    public static int $destroyCalls = 0;
    public static int $deleteCalls = 0;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = []) {}

    public function validate(object $project, object $profile): array { return []; }
    public function plan(object $project, object $profile): array { return []; }
    public function apply(object $project, object $profile): bool { return true; }
    public function destroy(object $project, object $profile): bool { ++self::$destroyCalls; return true; }
    public function getName(): string { return 'cleanup-expiry-test'; }
    public function getLastError(): string { return ''; }
    public function resolveServer(array $server): ?array { return self::$resolvedServer; }
    public function createServer(array $spec): array { return []; }
    public function server(string $serverId): array { return ['uuid' => $serverId]; }
    public function deleteServer(string $serverId, string $ownershipToken): bool { ++self::$deleteCalls; return true; }
}

\test('expired managed server is destroyed by cleanup command', function (): void {
    /** @var TestCase $this */
    ProviderRegistry::register('cleanup-expiry-test', CleanupExpiryTestProvider::class);
    CleanupExpiryTestProvider::$resolvedServer = [
        'uuid' => 'managed-1', 'created_at' => '2020-01-01T00:00:00Z', 'ownership_token' => 'owned',
    ];
    CleanupExpiryTestProvider::$destroyCalls = 0;
    CleanupExpiryTestProvider::$deleteCalls = 0;
    $path = \tempnam(\sys_get_temp_dir(), 'shipper-expiry-');
    \assert(\is_string($path));
    \file_put_contents($path, "projects:\n  api:\n    provider: cleanup-expiry-test\n    path: ./api\n    profiles:\n      preview:\n        branch: main\n        infrastructure:\n          server:\n            mode: create\n            cleanup: destroy\n            ttl: 1s\n            spec:\n              name: preview\n");

    try {
        $command = $this->artisan('servers:cleanup-expired', ['--config' => $path]);
        \assert($command instanceof PendingCommand);
        $command->assertExitCode(0);
        \expect(CleanupExpiryTestProvider::$destroyCalls)->toBe(1)
            ->and(CleanupExpiryTestProvider::$deleteCalls)->toBe(1);
    } finally {
        @\unlink($path);
    }
});

\test('unexpired managed server is left intact by cleanup command', function (): void {
    /** @var TestCase $this */
    ProviderRegistry::register('cleanup-expiry-test', CleanupExpiryTestProvider::class);
    CleanupExpiryTestProvider::$resolvedServer = [
        'uuid' => 'managed-1', 'created_at' => '2999-01-01T00:00:00Z', 'ownership_token' => 'owned',
    ];
    CleanupExpiryTestProvider::$destroyCalls = 0;
    CleanupExpiryTestProvider::$deleteCalls = 0;
    $path = \tempnam(\sys_get_temp_dir(), 'shipper-expiry-');
    \assert(\is_string($path));
    \file_put_contents($path, "projects:\n  api:\n    provider: cleanup-expiry-test\n    path: ./api\n    profiles:\n      preview:\n        branch: main\n        infrastructure:\n          server:\n            mode: create\n            cleanup: destroy\n            ttl: 72h\n            spec:\n              name: preview\n");

    try {
        $command = $this->artisan('servers:cleanup-expired', ['--config' => $path]);
        \assert($command instanceof PendingCommand);
        $command->assertExitCode(0);
        \expect(CleanupExpiryTestProvider::$destroyCalls)->toBe(0)
            ->and(CleanupExpiryTestProvider::$deleteCalls)->toBe(0);
    } finally {
        @\unlink($path);
    }
});

\test('cloud-linked server expiry leaves deployment intact', function (): void {
    /** @var TestCase $this */
    ProviderRegistry::register('cleanup-expiry-test', CleanupExpiryTestProvider::class);
    CleanupExpiryTestProvider::$resolvedServer = [
        'uuid' => 'managed-1', 'created_at' => '2020-01-01T00:00:00Z', 'ownership_token' => 'owned', 'server_record_only' => true,
    ];
    CleanupExpiryTestProvider::$destroyCalls = 0;
    CleanupExpiryTestProvider::$deleteCalls = 0;
    $path = \tempnam(\sys_get_temp_dir(), 'shipper-expiry-');
    \assert(\is_string($path));
    \file_put_contents($path, "projects:\n  api:\n    provider: cleanup-expiry-test\n    path: ./api\n    profiles:\n      preview:\n        branch: main\n        infrastructure:\n          server:\n            mode: create\n            cleanup: destroy\n            ttl: 1s\n            spec:\n              name: preview\n");

    try {
        $command = $this->artisan('servers:cleanup-expired', ['--config' => $path]);
        \assert($command instanceof PendingCommand);
        $command->assertExitCode(0);
        \expect(CleanupExpiryTestProvider::$destroyCalls)->toBe(0)
            ->and(CleanupExpiryTestProvider::$deleteCalls)->toBe(0);
    } finally {
        @\unlink($path);
    }
});
