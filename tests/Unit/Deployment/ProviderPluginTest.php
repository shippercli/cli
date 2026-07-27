<?php

declare(strict_types=1);

namespace Tests\Unit\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\DeploymentProviderInterface;
use App\Deployment\ProviderFactory;
use App\Deployment\ProviderRegistry;
use Tests\Fixtures\TestShipperPlugin;

\test('contract provider plugins register and run through core flows', function (): void {
    ProviderRegistry::registerPlugin(new TestShipperPlugin);

    $provider = (new ProviderFactory([
        'contract-test' => ['token' => 'configured'],
    ]))->create('contract-test');

    $project = new ProjectConfig('api', 'contract-test', '.', []);
    $profile = new ProfileConfig('production', 'main', []);

    \expect($provider)
        ->toBeInstanceOf(DeploymentProviderInterface::class)
        ->and($provider->getName())->toBe('contract-test')
        ->and($provider->validate($project, $profile))->toBe([])
        ->and($provider->plan($project, $profile))->toBe([
            'provider' => 'contract-test',
            'token' => 'configured',
        ])
        ->and($provider->apply($project, $profile))->toBeTrue()
        ->and($provider->destroy($project, $profile))->toBeTrue();
});

\test('composer discovers the installed cpanel provider package', function (): void {
    $provider = (new ProviderFactory([
        'cpanel' => [
            'host' => 'cpanel.hosting.com',
            'username' => 'shippercli',
            'api_token' => 'test-token',
        ],
    ]))->create('cpanel');

    \expect($provider)
        ->toBeInstanceOf(DeploymentProviderInterface::class)
        ->and($provider->getName())->toBe('cpanel');
});
