<?php

declare(strict_types=1);

use App\Config\DatabaseConfig;
use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Config\ServerLifecycleConfig;
use App\Deployment\PloiProvider;
use Mockery as m;
use Mockery\Expectation;
use Mockery\MockInterface;
use Ploi\Http\Response;
use Ploi\Ploi;
use Ploi\Resources\Server;

function setPloiClient(PloiProvider $provider, Ploi&MockInterface $client): void
{
    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('client');
    $property->setValue($provider, $client);
}

function mockShouldReceive(MockInterface $mock, string $method): mixed
{
    return $mock->shouldReceive($method);
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
            'name' => 'shippercli/cli',
        ],
    );
}

/**
 * @param array<string, DatabaseConfig> $databases
 */
function makePloiProjectWithDatabases(array $databases): ProjectConfig
{
    return new ProjectConfig(
        name: 'api',
        provider: 'ploi',
        path: './api',
        profiles: [],
        repository: [
            'provider' => 'github',
            'name' => 'shippercli/cli',
        ],
        databases: $databases,
    );
}

/**
 * @param array<string, mixed> $config
 */
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

\test('ploi validate rejects an unresolved API key placeholder', function (): void {
    $provider = new PloiProvider([
        'api_key' => '${PLOI_API_KEY}',
        'server_id' => '123',
    ]);

    $errors = $provider->validate(\makePloiProject(), \makePloiProfile());

    \expect($errors)->toContain('Ploi API key contains an unresolved environment variable');
});

\test('ploi database interpolation fails instead of colliding when a preview variable is missing', function (): void {
    \putenv('GITHUB_PR_NUMBER');

    $provider = new PloiProvider([
        'api_key' => 'token',
        'server_id' => '123',
    ]);
    $project = \makePloiProjectWithDatabases([
        'preview' => new DatabaseConfig('shipper_${PROJECT_NAME}_${GITHUB_PR_NUMBER}', 'shipper_${PROJECT_NAME}_${GITHUB_PR_NUMBER}'),
        'base' => new DatabaseConfig('shipper_${PROJECT_NAME}', 'shipper_${PROJECT_NAME}'),
    ]);

    \expect(fn (): array => $provider->plan($project, \makePloiProfile()))
        ->toThrow(\RuntimeException::class, 'Database identifier contains unresolved environment variable(s): GITHUB_PR_NUMBER');
});

\test('ploi database interpolation resolves project profile and environment placeholders', function (): void {
    \putenv('GITHUB_PR_NUMBER=42');

    try {
        $provider = new PloiProvider([
            'api_key' => 'token',
            'server_id' => '123',
        ]);
        $project = \makePloiProjectWithDatabases([
            'preview' => new DatabaseConfig(
                'shipper_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}',
                'shipper_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}',
            ),
        ]);

        $plan = $provider->plan($project, \makePloiProfile());

        \expect($plan['databases'])->toBe([
            'preview' => ['name' => 'shipper_api_preview_42', 'user' => 'shipper_api_preview_42', 'type' => 'mysql'],
        ]);
    } finally {
        \putenv('GITHUB_PR_NUMBER');
    }
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
    /** @var Ploi&MockInterface $client */
    $client = m::mock(Ploi::class);
    /** @var Server&MockInterface $serverResource */
    $serverResource = m::mock(Server::class);
    /** @var Response&MockInterface $response */
    $response = m::mock(Response::class);

    /** @var Expectation $responseGetJson */
    $responseGetJson = \mockShouldReceive($response, 'getJson');
    $responseGetJson->andReturn((object) [
        'data' => [
            (object) ['id' => 321, 'name' => 'shipper-api-preview-api-pr-123'],
        ],
    ]);

    /** @var Expectation $serverGet */
    $serverGet = \mockShouldReceive($serverResource, 'get');
    $serverGet->once()->andReturn($response);
    /** @var Expectation $clientServer */
    $clientServer = \mockShouldReceive($client, 'server');
    $clientServer->withNoArgs()->once()->andReturn($serverResource);

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
    /** @var Ploi&MockInterface $client */
    $client = m::mock(Ploi::class);
    /** @var Server&MockInterface $serverResource */
    $serverResource = m::mock(Server::class);
    /** @var Response&MockInterface $listResponse */
    $listResponse = m::mock(Response::class);
    /** @var Response&MockInterface $createResponse */
    $createResponse = m::mock(Response::class);

    /** @var Expectation $listResponseGetJson */
    $listResponseGetJson = \mockShouldReceive($listResponse, 'getJson');
    $listResponseGetJson->andReturn((object) ['data' => []]);
    /** @var Expectation $createResponseGetJson */
    $createResponseGetJson = \mockShouldReceive($createResponse, 'getJson');
    $createResponseGetJson->andReturn((object) [
        'data' => (object) ['id' => 654],
    ]);

    /** @var Expectation $serverGet */
    $serverGet = \mockShouldReceive($serverResource, 'get');
    $serverGet->once()->andReturn($listResponse);
    /** @var Expectation $serverCreate */
    $serverCreate = \mockShouldReceive($serverResource, 'create');
    $serverCreate->once()
        ->with('shipper-api-preview-api-pr-456', 42, 'eu-west', 'small', m::type('array'))
        ->andReturn($createResponse);

    /** @var Expectation $clientServer */
    $clientServer = \mockShouldReceive($client, 'server');
    $clientServer->withNoArgs()->twice()->andReturn($serverResource);

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
    /** @var Ploi&MockInterface $client */
    $client = m::mock(Ploi::class);
    /** @var Server&MockInterface $listResource */
    $listResource = m::mock(Server::class);
    /** @var Server&MockInterface $serverResource */
    $serverResource = m::mock(Server::class);
    /** @var Response&MockInterface $listResponse */
    $listResponse = m::mock(Response::class);
    /** @var Response&MockInterface $deleteResponse */
    $deleteResponse = m::mock(Response::class);

    /** @var Expectation $listResponseGetJson */
    $listResponseGetJson = \mockShouldReceive($listResponse, 'getJson');
    $listResponseGetJson->andReturn((object) [
        'data' => [
            (object) ['id' => 999, 'name' => 'shipper-api-preview-api-pr-999'],
        ],
    ]);
    /** @var Expectation $deleteResponseGetJson */
    $deleteResponseGetJson = \mockShouldReceive($deleteResponse, 'getJson');
    $deleteResponseGetJson->andReturn((object) [
        'message' => 'Server deleted successfully',
    ]);

    /** @var Expectation $listResourceGet */
    $listResourceGet = \mockShouldReceive($listResource, 'get');
    $listResourceGet->once()->andReturn($listResponse);
    /** @var Expectation $serverDelete */
    $serverDelete = \mockShouldReceive($serverResource, 'delete');
    $serverDelete->once()->andReturn($deleteResponse);

    /** @var Expectation $clientServerNoArgs */
    $clientServerNoArgs = \mockShouldReceive($client, 'server');
    $clientServerNoArgs->withNoArgs()->once()->andReturn($listResource);
    /** @var Expectation $clientServerWithId */
    $clientServerWithId = \mockShouldReceive($client, 'server');
    $clientServerWithId->with(999)->once()->andReturn($serverResource);

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
    /** @var Ploi&MockInterface $client */
    $client = m::mock(Ploi::class);
    /** @var Server&MockInterface $listResource */
    $listResource = m::mock(Server::class);
    /** @var Response&MockInterface $listResponse */
    $listResponse = m::mock(Response::class);

    /** @var Expectation $listResponseGetJson */
    $listResponseGetJson = \mockShouldReceive($listResponse, 'getJson');
    $listResponseGetJson->twice()->andReturn((object) [
        'data' => [
            (object) ['id' => 777, 'name' => 'api-pr-777'],
        ],
    ]);

    /** @var Expectation $listResourceGet */
    $listResourceGet = \mockShouldReceive($listResource, 'get');
    $listResourceGet->twice()->andReturn($listResponse);

    /** @var Expectation $clientServer */
    $clientServer = \mockShouldReceive($client, 'server');
    $clientServer->withNoArgs()->twice()->andReturn($listResource);

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
