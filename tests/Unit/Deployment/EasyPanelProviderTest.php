<?php

declare(strict_types=1);

use App\Config\DatabaseConfig;
use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\Providers\EasyPanel\EasyPanelApiClient;
use App\Deployment\Providers\EasyPanel\EasyPanelProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * @param array<string, mixed> $repository
 * @param array<string, DatabaseConfig> $databases
 */
function easyPanelProject(array $repository, array $databases = []): ProjectConfig
{
    return new ProjectConfig(
        name: 'api',
        provider: 'easypanel',
        path: '.',
        profiles: [],
        repository: $repository,
        databases: $databases,
    );
}

function easyPanelProfile(): ProfileConfig
{
    return new ProfileConfig('production', 'main', ['domain' => 'api.example.com']);
}

\test('apply derives the git URL from documented repository provider and name', function (): void {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], '[{"name":"api"}]'),
        new Response(200, [], '{"name":"api"}'),
        new Response(200, [], '{}'),
        new Response(200, [], '{}'),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));
    $client = new EasyPanelApiClient(
        'https://easypanel.example.com',
        'secret',
        new Client(['handler' => $stack]),
    );
    $provider = new EasyPanelProvider([
        'url' => 'https://easypanel.example.com',
        'auth_token' => 'secret',
    ]);
    $apiClient = new ReflectionProperty($provider, 'apiClient');
    $apiClient->setValue($provider, $client);

    \expect($provider->apply(
        \easyPanelProject(['provider' => 'github', 'name' => 'shippercli/cli']),
        \easyPanelProfile(),
    ))->toBeTrue();

    $serviceRequest = $history[1]['request'];
    $payload = \json_decode((string) $serviceRequest->getBody(), true);
    \assert(\is_array($payload));

    \expect($payload['git_url'])->toBe('https://github.com/shippercli/cli.git')
        ->and($payload['git_branch'])->toBe('main');
});

\test('apply rejects an unusable repository before making API requests', function (): void {
    $provider = new EasyPanelProvider([
        'url' => 'https://easypanel.example.com',
        'auth_token' => 'secret',
    ]);

    \expect($provider->apply(
        \easyPanelProject(['provider' => 'custom', 'name' => 'shippercli/cli']),
        \easyPanelProfile(),
    ))->toBeFalse()
        ->and($provider->getLastError())->toContain('usable repository');
});

\test('plan does not promise unimplemented database creation', function (): void {
    $provider = new EasyPanelProvider([
        'url' => 'https://easypanel.example.com',
        'auth_token' => 'secret',
    ]);
    $project = \easyPanelProject(
        ['provider' => 'github', 'name' => 'shippercli/cli'],
        ['main' => new DatabaseConfig('api', 'api')],
    );

    $plan = $provider->plan($project, \easyPanelProfile());

    \expect($plan['actions'])->not->toContain('Create database: api (mysql)');
});
