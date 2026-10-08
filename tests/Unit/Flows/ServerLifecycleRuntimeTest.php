<?php

declare(strict_types=1);

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Config\ServerLifecycleConfig;
use App\Deployment\ContractDeploymentProviderAdapter;
use App\Flows\ApplyDeploymentFlow;
use App\Flows\DestroyDeploymentFlow;
use ShipperCli\Contracts\DeploymentProviderInterface;
use ShipperCli\Contracts\ServerLifecycleProviderInterface;

\test('apply provisions a missing create-mode server once and passes its id to the provider', function (): void {
    $contract = new class implements DeploymentProviderInterface, ServerLifecycleProviderInterface
    {
        public int $createCalls = 0;
        public ?string $appliedServerId = null;

        public function validate(object $project, object $profile): array { return []; }
        public function plan(object $project, object $profile): array { return []; }
        public function apply(object $project, object $profile): bool { $this->appliedServerId = $profile->get('server_uuid'); return true; }
        public function destroy(object $project, object $profile): bool { return true; }
        public function getName(): string { return 'lifecycle-test'; }
        public function getLastError(): string { return ''; }
        public function resolveServer(array $server): ?array { return null; }
        public function createServer(array $spec): array { ++$this->createCalls; return ['uuid' => 'server-123', 'ownership_token' => 'owner-token']; }
        public function server(string $serverId): array { return ['uuid' => $serverId]; }
        public function deleteServer(string $serverId, string $ownershipToken): bool { return true; }
    };
    $provider = new ContractDeploymentProviderAdapter($contract);
    $project = new ProjectConfig('api', 'lifecycle-test', '.', []);
    $profile = new ProfileConfig('preview', 'main', [], server: new ServerLifecycleConfig('create', cleanup: 'retain', spec: ['name' => 'api-preview']));

    $result = (new ApplyDeploymentFlow)->execute($provider, $project, $profile, []);

    \expect($result['success'])->toBeTrue()
        ->and($contract->createCalls)->toBe(1)
        ->and($contract->appliedServerId)->toBe('server-123');
});

\test('destroy refuses cloud record-only cleanup before destroying the deployment', function (): void {
    $contract = new class implements DeploymentProviderInterface, ServerLifecycleProviderInterface
    {
        public bool $destroyCalled = false;
        public bool $deleteCalled = false;

        public function validate(object $project, object $profile): array { return []; }
        public function plan(object $project, object $profile): array { return []; }
        public function apply(object $project, object $profile): bool { return true; }
        public function destroy(object $project, object $profile): bool { $this->destroyCalled = true; return true; }
        public function getName(): string { return 'lifecycle-test'; }
        public function getLastError(): string { return ''; }
        public function resolveServer(array $server): ?array { return ['uuid' => 'server-123', 'ownership_token' => 'owner-token', 'server_record_only' => true]; }
        public function createServer(array $spec): array { return []; }
        public function server(string $serverId): array { return ['uuid' => $serverId]; }
        public function deleteServer(string $serverId, string $ownershipToken): bool { $this->deleteCalled = true; return false; }
    };
    $provider = new ContractDeploymentProviderAdapter($contract);
    $project = new ProjectConfig('api', 'lifecycle-test', '.', []);
    $profile = new ProfileConfig('preview', 'main', [], server: new ServerLifecycleConfig('create', cleanup: 'destroy', spec: ['name' => 'api-preview']));

    $result = (new DestroyDeploymentFlow)->execute($provider, $project, $profile);

    \expect($result['success'])->toBeFalse()
        ->and($result['error_message'])->toContain('left intact')
        ->and($contract->destroyCalled)->toBeFalse()
        ->and($contract->deleteCalled)->toBeFalse();
});

\test('existing server lifecycle id becomes the provider server uuid', function (): void {
    $profile = new ProfileConfig('production', 'main', [], server: new ServerLifecycleConfig('existing', id: 'server-existing'));
    $contract = new class implements DeploymentProviderInterface, ServerLifecycleProviderInterface
    {
        public function validate(object $project, object $profile): array { return []; }
        public function plan(object $project, object $profile): array { return []; }
        public function apply(object $project, object $profile): bool { return true; }
        public function destroy(object $project, object $profile): bool { return true; }
        public function getName(): string { return 'lifecycle-test'; }
        public function getLastError(): string { return ''; }
        public function resolveServer(array $server): ?array { return null; }
        public function createServer(array $spec): array { return []; }
        public function server(string $serverId): array { return []; }
        public function deleteServer(string $serverId, string $ownershipToken): bool { return true; }
    };

    $resolved = \App\Flows\ServerLifecycleRuntime::resolve(new ContractDeploymentProviderAdapter($contract), $profile);

    \expect($resolved['profile']->get('server_uuid'))->toBe('server-existing')
        ->and($resolved['error'])->toBe('');
});

\test('server lifecycle ttl parses supported units and rejects invalid values', function (): void {
    \expect((new ServerLifecycleConfig('create', ttl: '72h'))->ttlSeconds())->toBe(259200)
        ->and((new ServerLifecycleConfig('create', ttl: '30m'))->ttlSeconds())->toBe(1800)
        ->and((new ServerLifecycleConfig('create', ttl: '90'))->ttlSeconds())->toBe(90)
        ->and((new ServerLifecycleConfig('create', ttl: '0s'))->ttlSeconds())->toBeNull()
        ->and((new ServerLifecycleConfig('create', ttl: '1w'))->ttlSeconds())->toBeNull();
});
