<?php

declare(strict_types=1);

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\ContractDeploymentProviderAdapter;
use App\Deployment\ProviderFactory;
use App\Deployment\ProviderRegistry;
use App\Deployment\Providers\EasyPanel\EasyPanelProvider;
use Tests\Fixtures\TestShipperPlugin;

\test('factory creates a native provider without wrapping it', function (): void {
    ProviderRegistry::register('factory-native-easypanel-test', EasyPanelProvider::class);

    $provider = (new ProviderFactory)->create('factory-native-easypanel-test');

    \expect($provider)->toBeInstanceOf(EasyPanelProvider::class)
        ->and($provider->getName())->toBe('easypanel');
});

\test('factory wraps the plugin provider and passes only its settings', function (): void {
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    $factory = new ProviderFactory([
        'another-provider' => ['token' => 'unrelated'],
        'contract-test' => ['token' => 'configured'],
    ]);

    $provider = $factory->create('contract-test');
    $plan = $provider->plan(new ProjectConfig('app', 'contract-test', '.', []), new ProfileConfig('production', 'main', []));

    \expect($provider)->toBeInstanceOf(ContractDeploymentProviderAdapter::class)
        ->and($plan)->toBe(['provider' => 'contract-test', 'token' => 'configured']);
});

\test('factory rejects an unknown provider by name', function (): void {
    \expect(fn () => (new ProviderFactory)->create('unregistered-provider-for-factory-test'))
        ->toThrow(InvalidArgumentException::class, 'Unknown provider: unregistered-provider-for-factory-test');
});

\test('factory rejects a registered class that implements neither provider interface', function (): void {
    ProviderRegistry::register('factory-invalid-test', stdClass::class);

    \expect(fn () => (new ProviderFactory)->create('factory-invalid-test'))
        ->toThrow(UnexpectedValueException::class, 'Provider stdClass must implement');
});
