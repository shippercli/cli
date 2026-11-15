<?php

declare(strict_types=1);

namespace Tests\Unit\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\ContractDeploymentProviderAdapter;
use PHPUnit\Framework\TestCase;
use ShipperCli\Contracts\CapabilityManifest;
use ShipperCli\Contracts\DeploymentProviderInterface as ContractProvider;
use ShipperCli\Contracts\ProviderCapabilitiesInterface;

/** @return array<string, array{state: 'partial'|'supported'|'unsupported', notes?: string, requirements?: list<string>, limitations?: list<string>}> */
function validContractCapabilityManifest(): array
{
    return \array_fill_keys(CapabilityManifest::CAPABILITIES, ['state' => 'supported']);
}

\test('accepts a valid contract provider capability manifest', function (): void {
    $manifest = validContractCapabilityManifest();
    $provider = new class($manifest) implements ContractProvider, ProviderCapabilitiesInterface
    {
        /** @var array<string, array{state: 'partial'|'supported'|'unsupported', notes?: string, requirements?: list<string>, limitations?: list<string>}> */
        private readonly array $manifest;

        /** @param array<string, array{state: 'partial'|'supported'|'unsupported', notes?: string, requirements?: list<string>, limitations?: list<string>}> $manifest */
        public function __construct(array $manifest)
        {
            $this->manifest = $manifest;
        }

        public function capabilities(): array
        {
            return $this->manifest;
        }

        public function validate(object $project, object $profile): array
        {
            return [];
        }

        public function plan(object $project, object $profile): array
        {
            return [];
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
            return 'valid';
        }

        public function getLastError(): string
        {
            return '';
        }
    };

    \expect(new ContractDeploymentProviderAdapter($provider))->toBeInstanceOf(ContractDeploymentProviderAdapter::class);
});

\test('rejects an invalid contract provider capability manifest', function (): void {
    $manifest = validContractCapabilityManifest();
    unset($manifest['rollback']);
    $provider = new class($manifest) implements ContractProvider, ProviderCapabilitiesInterface
    {
        /** @var array<string, array{state: 'partial'|'supported'|'unsupported', notes?: string, requirements?: list<string>, limitations?: list<string>}> */
        private readonly array $manifest;

        /** @param array<string, array{state: 'partial'|'supported'|'unsupported', notes?: string, requirements?: list<string>, limitations?: list<string>}> $manifest */
        public function __construct(array $manifest)
        {
            $this->manifest = $manifest;
        }

        public function capabilities(): array
        {
            return $this->manifest;
        }

        public function validate(object $project, object $profile): array
        {
            return [];
        }

        public function plan(object $project, object $profile): array
        {
            return [];
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
            return 'invalid';
        }

        public function getLastError(): string
        {
            return '';
        }
    };

    \expect(fn () => new ContractDeploymentProviderAdapter($provider))
        ->toThrow(\UnexpectedValueException::class, 'Provider invalid has an invalid capability manifest: Missing capabilities: rollback');
});

\test('keeps legacy contract providers compatible', function (): void {
    $provider = new class implements ContractProvider
    {
        public function validate(object $project, object $profile): array
        {
            return [];
        }

        public function plan(object $project, object $profile): array
        {
            return [];
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
            return 'legacy';
        }

        public function getLastError(): string
        {
            return '';
        }
    };

    \expect(new ContractDeploymentProviderAdapter($provider))->toBeInstanceOf(ContractDeploymentProviderAdapter::class);
});

\test('forwards the deployment context and preserves contract provider results', function (): void {
    /** @var TestCase $this */
    $provider = $this->createMock(ContractProvider::class);
    $profile = new ProfileConfig('production', 'main', []);
    $project = new ProjectConfig('api', 'test-provider', '.', ['production' => $profile]);
    $errors = ['Invalid profile'];
    $plan = ['actions' => ['Publish release']];

    $provider->expects($this->once())->method('validate')
        ->with($this->identicalTo($project), $this->identicalTo($profile))->willReturn($errors);
    $provider->expects($this->once())->method('plan')
        ->with($this->identicalTo($project), $this->identicalTo($profile))->willReturn($plan);
    $provider->expects($this->once())->method('apply')
        ->with($this->identicalTo($project), $this->identicalTo($profile))->willReturn(false);
    $provider->expects($this->once())->method('destroy')
        ->with($this->identicalTo($project), $this->identicalTo($profile))->willReturn(true);
    $provider->expects($this->once())->method('getName')->willReturn('test-provider');
    $provider->expects($this->once())->method('getLastError')->willReturn('Deployment failed');

    $adapter = new ContractDeploymentProviderAdapter($provider);

    \expect($adapter->validate($project, $profile))->toBe($errors)
        ->and($adapter->plan($project, $profile))->toBe($plan)
        ->and($adapter->apply($project, $profile))->toBeFalse()
        ->and($adapter->destroy($project, $profile))->toBeTrue()
        ->and($adapter->getName())->toBe('test-provider')
        ->and($adapter->getLastError())->toBe('Deployment failed')
        ->and($adapter->contractProvider())->toBe($provider);
});
