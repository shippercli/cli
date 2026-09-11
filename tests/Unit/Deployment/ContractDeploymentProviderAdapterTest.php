<?php

declare(strict_types=1);

namespace Tests\Unit\Deployment;

use App\Deployment\ContractDeploymentProviderAdapter;
use ShipperCli\Contracts\CapabilityManifest;
use ShipperCli\Contracts\DeploymentProviderInterface as ContractProvider;
use ShipperCli\Contracts\ProviderCapabilitiesInterface;

test('accepts a valid contract provider capability manifest', function (): void {
    $manifest = \array_fill_keys(CapabilityManifest::CAPABILITIES, ['state' => 'supported']);
    $provider = new class($manifest) implements ContractProvider, ProviderCapabilitiesInterface
    {
        public function __construct(private readonly array $manifest) {}
        public function capabilities(): array { return $this->manifest; }
        public function validate(object $project, object $profile): array { return []; }
        public function plan(object $project, object $profile): array { return []; }
        public function apply(object $project, object $profile): bool { return true; }
        public function destroy(object $project, object $profile): bool { return true; }
        public function getName(): string { return 'valid'; }
        public function getLastError(): string { return ''; }
    };

    expect(new ContractDeploymentProviderAdapter($provider))->toBeInstanceOf(ContractDeploymentProviderAdapter::class);
});

test('rejects an invalid contract provider capability manifest', function (): void {
    $manifest = \array_fill_keys(CapabilityManifest::CAPABILITIES, ['state' => 'supported']);
    unset($manifest['rollback']);
    $provider = new class($manifest) implements ContractProvider, ProviderCapabilitiesInterface
    {
        public function __construct(private readonly array $manifest) {}
        public function capabilities(): array { return $this->manifest; }
        public function validate(object $project, object $profile): array { return []; }
        public function plan(object $project, object $profile): array { return []; }
        public function apply(object $project, object $profile): bool { return true; }
        public function destroy(object $project, object $profile): bool { return true; }
        public function getName(): string { return 'invalid'; }
        public function getLastError(): string { return ''; }
    };

    expect(fn () => new ContractDeploymentProviderAdapter($provider))
        ->toThrow(\UnexpectedValueException::class, 'Provider invalid has an invalid capability manifest: Missing capabilities: rollback');
});

test('keeps legacy contract providers compatible', function (): void {
    $provider = new class implements ContractProvider
    {
        public function validate(object $project, object $profile): array { return []; }
        public function plan(object $project, object $profile): array { return []; }
        public function apply(object $project, object $profile): bool { return true; }
        public function destroy(object $project, object $profile): bool { return true; }
        public function getName(): string { return 'legacy'; }
        public function getLastError(): string { return ''; }
    };

    expect(new ContractDeploymentProviderAdapter($provider))->toBeInstanceOf(ContractDeploymentProviderAdapter::class);
});
