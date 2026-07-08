<?php

declare(strict_types=1);

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Config\ServerLifecycleConfig;
use App\Deployment\PloiProvider;
use Mockery as m;
use Ploi\Http\Response;
use Ploi\Ploi;
use Ploi\Resources\Server;

function setPloiClient(PloiProvider $provider, Ploi $client): void
{
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('client');
    $property->setValue($provider, $client);
}

function resolvePloiServerId(PloiProvider $provider, ProjectConfig $project, ProfileConfig $profile, bool $createIfMissing = false): int
{
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('resolveServerIdForProfile');

    /** @var int */
    return $method->invoke($provider, $project, $profile, $createIfMissing);
}

function makePloiProject(): ProjectConfig
{
    return new ProjectConfig(
        'api',
        'ploi',
        './api',
        [],
        [
            'provider' => 'github',
            'name' => 'ulties/shipper',
        ],
    );
}

function makePloiProfile(array $config = [], ?ServerLifecycleConfig $server = null): ProfileConfig
{
    return new ProfileConfig(
        'preview',
        'feature/test',
        \array_merge([
            'domain' => 'preview.example.com',
        ], $config),
        null,
        $server,
    );
}

\test('ploi validate requires create server lifecycle fields', function (): void {
    $provider = new PloiProvider([
        'api_key' => 'token',
    ]);

    $errors = $provider->validate(
        \makePloiProject(),
        \makePloiProfile([], new ServerLifecycleConfig('create', null, 'destroy', null, [
            'name' => 'api-preview',
        ])),
    );

    \expect($errors)->toContain('Ploi create server mode requires infrastructure.server.spec.credential (or provider_id/provider) as digits');
    \expect($errors)->toContain('Ploi create server mode requires infrastructure.server.spec.region');
    \expect($errors)->toContain('Ploi create server mode requires infrastructure.server.spec.plan (or size)');
});

\test('ploi plan shows create server lifecycle actions', function (): void {
    $provider = new PloiProvider([
        'api_key' => 'token',
    ]);

    $plan = $provider->plan(
        \makePloiProject(),
        \makePloiProfile([], new ServerLifecycleConfig('create', null, 'destroy', '72h', [
            'name' => 'api-pr-123',
            'credential' => '42',
            'region' => 'eu-west',
            'plan' => 'small',
        ])),
    );

    \expect($plan['server_mode'])->toBe('create');
    \expect($plan['server_id'])->toBeNull();
    \expect($plan['server_cleanup'])->toBe('destroy');
    \expect($plan['actions'])->toContain('Create server: api-pr-123');
    \expect($plan['actions'])->toContain('Mark created server as managed: shipper-api-preview-api-pr-123');
    \expect($plan['actions'])->toContain('Cleanup policy for created server: destroy');
});

\test('ploi can resolve existing created-mode server by name without creating', function (): void {
    $client = m::mock(Ploi::class);
    $serverResource = m::mock(Server::class);
    $response = m::mock(Response::class);

    $response->shouldReceive('getJson')->andReturn((object) [
        'data' => [
            (object) ['id' => 321, 'name' => 'shipper-api-preview-api-pr-123'],
        ],
    ]);

    $serverResource->shouldReceive('get')->once()->andReturn($response);
    $client->shouldReceive('server')->withNoArgs()->once()->andReturn($serverResource);

    $provider = new PloiProvider([
        'api_key' => 'token',
    ]);
    \setPloiClient($provider, $client);

    $profile = \makePloiProfile([], new ServerLifecycleConfig('create', null, 'destroy', null, [
        'name' => 'api-pr-123',
        'credential' => '42',
        'region' => 'eu-west',
        'plan' => 'small',
    ]));

    \expect(\resolvePloiServerId($provider, \makePloiProject(), $profile, true))->toBe(321);
});

\test('ploi can create server when create-mode server is missing', function (): void {
    $client = m::mock(Ploi::class);
    $serverResource = m::mock(Server::class);
    $listResponse = m::mock(Response::class);
    $createResponse = m::mock(Response::class);

    $listResponse->shouldReceive('getJson')->andReturn((object) ['data' => []]);
    $createResponse->shouldReceive('getJson')->andReturn((object) [
        'data' => (object) ['id' => 654],
    ]);

    $serverResource->shouldReceive('get')->once()->andReturn($listResponse);
    $serverResource->shouldReceive('create')
        ->once()
        ->with('shipper-api-preview-api-pr-456', 42, 'eu-west', 'small', m::type('array'))
        ->andReturn($createResponse);

    $client->shouldReceive('server')->withNoArgs()->twice()->andReturn($serverResource);

    $provider = new PloiProvider([
        'api_key' => 'token',
    ]);
    \setPloiClient($provider, $client);

    $profile = \makePloiProfile([], new ServerLifecycleConfig('create', null, 'destroy', null, [
        'name' => 'api-pr-456',
        'credential' => '42',
        'region' => 'eu-west',
        'plan' => 'small',
        'php_version' => '8.3',
    ]));

    \expect(\resolvePloiServerId($provider, \makePloiProject(), $profile, true))->toBe(654);
});

\test('ploi destroy skips created server cleanup when policy is retain', function (): void {
    $provider = new PloiProvider([
        'api_key' => 'token',
    ]);

    $result = $provider->destroy(
        \makePloiProject(),
        \makePloiProfile([], new ServerLifecycleConfig('create', null, 'retain', null, [
            'name' => 'api-pr-123',
            'credential' => '42',
            'region' => 'eu-west',
            'plan' => 'small',
        ])),
    );

    \expect($result)->toBeTrue();
});

\test('ploi destroy deletes created server when cleanup policy is destroy', function (): void {
    $client = m::mock(Ploi::class);
    $listResource = m::mock(Server::class);
    $serverResource = m::mock(Server::class);
    $listResponse = m::mock(Response::class);
    $deleteResponse = m::mock(Response::class);

    $listResponse->shouldReceive('getJson')->andReturn((object) [
        'data' => [
            (object) ['id' => 999, 'name' => 'shipper-api-preview-api-pr-999'],
        ],
    ]);
    $deleteResponse->shouldReceive('getJson')->andReturn((object) [
        'message' => 'Server deleted successfully',
    ]);

    $listResource->shouldReceive('get')->once()->andReturn($listResponse);
    $serverResource->shouldReceive('delete')->once()->andReturn($deleteResponse);

    $client->shouldReceive('server')->withNoArgs()->once()->andReturn($listResource);
    $client->shouldReceive('server')->with(999)->once()->andReturn($serverResource);

    $provider = new PloiProvider([
        'api_key' => 'token',
    ]);
    \setPloiClient($provider, $client);

    $result = $provider->destroy(
        \makePloiProject(),
        \makePloiProfile([], new ServerLifecycleConfig('create', null, 'destroy', null, [
            'name' => 'api-pr-999',
            'credential' => '42',
            'region' => 'eu-west',
            'plan' => 'small',
        ])),
    );

    \expect($result)->toBeTrue();
});

\test('ploi destroy refuses deleting unmanaged created server name match', function (): void {
    $client = m::mock(Ploi::class);
    $listResource = m::mock(Server::class);
    $listResponse = m::mock(Response::class);

    $listResponse->shouldReceive('getJson')->twice()->andReturn((object) [
        'data' => [
            (object) ['id' => 777, 'name' => 'api-pr-777'],
        ],
    ]);

    $listResource->shouldReceive('get')->twice()->andReturn($listResponse);

    $client->shouldReceive('server')->withNoArgs()->twice()->andReturn($listResource);

    $provider = new PloiProvider([
        'api_key' => 'token',
    ]);
    \setPloiClient($provider, $client);

    $result = $provider->destroy(
        \makePloiProject(),
        \makePloiProfile([], new ServerLifecycleConfig('create', null, 'destroy', null, [
            'name' => 'api-pr-777',
            'credential' => '42',
            'region' => 'eu-west',
            'plan' => 'small',
        ])),
    );

    \expect($result)->toBeFalse();
    \expect($provider->getLastError())->toBe('Refusing to delete unmanaged server: api-pr-777');
});
