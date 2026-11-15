<?php

declare(strict_types=1);

namespace Tests\Unit\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\ContractDeploymentProviderAdapter;
use Mockery;
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

\afterEach(function (): void {
    Mockery::close();
});

\test('forwards the deployment context and preserves contract provider results', function (): void {
    $provider = Mockery::mock(ContractProvider::class);
    $profile = new ProfileConfig('production', 'main', []);
    $project = new ProjectConfig('api', 'test-provider', '.', ['production' => $profile]);
    $sameProject = Mockery::on(static fn (object $argument): bool => $argument === $project);
    $sameProfile = Mockery::on(static fn (object $argument): bool => $argument === $profile);
    $errors = ['Invalid profile'];
    $plan = ['actions' => ['Publish release']];

    $provider->shouldReceive('validate')->once()
        ->with($sameProject, $sameProfile)->andReturn($errors);
    $provider->shouldReceive('plan')->once()
        ->with($sameProject, $sameProfile)->andReturn($plan);
    $provider->shouldReceive('apply')->once()
        ->with($sameProject, $sameProfile)->andReturn(false);
    $provider->shouldReceive('destroy')->once()
        ->with($sameProject, $sameProfile)->andReturn(true);
    $provider->shouldReceive('getName')->once()->andReturn('test-provider');
    $provider->shouldReceive('getLastError')->once()->andReturn('Deployment failed');

    $adapter = new ContractDeploymentProviderAdapter($provider);

    \expect($adapter->validate($project, $profile))->toBe($errors)
        ->and($adapter->plan($project, $profile))->toBe($plan)
        ->and($adapter->apply($project, $profile))->toBeFalse()
        ->and($adapter->destroy($project, $profile))->toBeTrue()
        ->and($adapter->getName())->toBe('test-provider')
        ->and($adapter->getLastError())->toBe('Deployment failed')
        ->and($adapter->contractProvider())->toBe($provider);
});
