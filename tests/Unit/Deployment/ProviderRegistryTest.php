<?php

declare(strict_types=1);

use App\Deployment\ProviderRegistry;
use ShipperCli\Contracts\ShipperPluginInterface;
use Tests\Fixtures\TestBlockedPlanProvider;
use Tests\Fixtures\TestContractProvider;
use Tests\Fixtures\TestShipperPlugin;

\test('registry discovers installed Composer providers and lists explicitly registered plugins', function (): void {
    ProviderRegistry::registerPlugin(new TestShipperPlugin);

    \expect(ProviderRegistry::all())->toHaveKeys(['contract-test', 'cpanel'])
        ->and(ProviderRegistry::get('contract-test'))->toBe(TestContractProvider::class)
        ->and(ProviderRegistry::get('unregistered-provider-for-registry-test'))->toBeNull();
});

\test('registry uses the most recently registered class for a provider name', function (): void {
    ProviderRegistry::register('registry-duplicate-test', TestContractProvider::class);
    \expect(ProviderRegistry::get('registry-duplicate-test'))->toBe(TestContractProvider::class);

    ProviderRegistry::register('registry-duplicate-test', TestBlockedPlanProvider::class);
    \expect(ProviderRegistry::get('registry-duplicate-test'))->toBe(TestBlockedPlanProvider::class);
});

\test('registry rejects invalid provider classes declared by plugins', function (): void {
    $plugin = new class implements ShipperPluginInterface
    {
        public function providers(): array
        {
            // @phpstan-ignore return.type (Exercise runtime validation of a plugin that violates its contract.)
            return ['registry-invalid-test' => stdClass::class];
        }
    };

    \expect(fn () => ProviderRegistry::registerPlugin($plugin))
        ->toThrow(UnexpectedValueException::class, 'Shipper plugin provider "registry-invalid-test" must map to a class implementing')
        ->and(ProviderRegistry::get('registry-invalid-test'))->toBeNull();
});
