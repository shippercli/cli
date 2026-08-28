<?php

declare(strict_types=1);

namespace Tests\Unit\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\ProviderRegistry;
use App\Deployment\Providers\CloudflarePages\CloudflarePagesProvider;
use App\Deployment\Providers\Coolify\CoolifyProvider;
use App\Deployment\Providers\EasyPanel\EasyPanelProvider;
use App\Deployment\Providers\Forge\ForgeProvider;
use App\Deployment\Providers\Hostinger\HostingerProvider;
use App\Deployment\Providers\Portainer\PortainerProvider;
use App\Deployment\Providers\Railway\RailwayProvider;

\test('incomplete providers are not registered', function (): void {
    foreach (['forge', 'railway', 'cloudflare-pages', 'hostinger', 'coolify', 'easypanel', 'portainer'] as $name) {
        \expect(ProviderRegistry::get($name))->toBeNull();
    }
});

\test('unimplemented provider operations fail explicitly', function (): void {
    $project = new ProjectConfig('api', 'test', '.', []);
    $profile = new ProfileConfig('production', 'main', []);
    $forge = new ForgeProvider([]);

    \expect($forge->apply($project, $profile))->toBeFalse()
        ->and($forge->getLastError())->toBe('Forge apply is not implemented');

    $providers = [
        $forge,
        new RailwayProvider([]),
        new CloudflarePagesProvider([]),
        new HostingerProvider([]),
        new CoolifyProvider([]),
        new EasyPanelProvider([]),
        new PortainerProvider([]),
    ];

    foreach ($providers as $provider) {
        \expect($provider->destroy($project, $profile))->toBeFalse()
            ->and($provider->getLastError())->toContain('destroy is not implemented');
    }
});
