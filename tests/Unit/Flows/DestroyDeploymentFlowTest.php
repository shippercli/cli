<?php

declare(strict_types=1);

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\DeploymentProviderInterface;
use App\Deployment\ProviderRegistry;
use App\Flows\DestroyDeploymentFlow;
use Mockery\MockInterface;
use Tests\Fixtures\TestShipperPlugin;

\afterEach(function (): void {
    Mockery::close();
});

\test('destroy flow rejects a missing project or profile before resolving a provider', function (string $project, string $profile, string $message): void {
    $flow = new DestroyDeploymentFlow(fn () => throw new LogicException('Unexpected provider resolution'));

    $result = $flow->handle(__DIR__.'/../../Fixtures/provider-operations-config.yml', $project, $profile);

    \expect($result)->toMatchArray([
        'success' => false,
        'profile' => null,
        'plan' => [],
        'errors' => [],
        'error_message' => $message,
        'provider' => null,
    ])->and($result['project']?->name())->toBe($project === 'app' ? 'app' : null);
})->with([
    ['missing', 'production', 'Project not found: missing'],
    ['app', 'missing', 'Profile not found: missing'],
]);

\test('destroy flow plans with the configured provider by default', function (): void {
    ProviderRegistry::registerPlugin(new TestShipperPlugin);

    $result = (new DestroyDeploymentFlow)->handle(__DIR__.'/../../Fixtures/provider-operations-config.yml', 'app', 'production');

    \expect($result)->toMatchArray([
        'success' => true,
        'plan' => ['provider' => 'contract-test', 'token' => 'configured'],
        'errors' => [],
        'error_message' => '',
    ])->and($result['project']?->name())->toBe('app')
        ->and($result['profile']?->name())->toBe('production')
        ->and($result['provider']?->getName())->toBe('contract-test');
});

\test('destroy flow validates before planning and waits for explicit execution', function (array $errors): void {
    /** @var DeploymentProviderInterface&MockInterface $provider */
    $provider = Mockery::mock(DeploymentProviderInterface::class);
    $provider->shouldReceive('validate')->once()->andReturn($errors);
    $provider->shouldNotReceive('destroy');
    if ($errors === []) {
        $provider->shouldReceive('plan')->once()->andReturn(['resource' => 'app.example.com']);
    } else {
        $provider->shouldNotReceive('plan');
    }
    $flow = new DestroyDeploymentFlow(function (string $name, array $config) use ($provider): DeploymentProviderInterface {
        \expect($name)->toBe('contract-test')
            ->and($config['contract-test']['token'])->toBe('configured');

        return $provider;
    });

    $result = $flow->handle(__DIR__.'/../../Fixtures/provider-operations-config.yml', 'app', 'production');

    \expect($result)->toMatchArray([
        'success' => $errors === [],
        'plan' => $errors === [] ? ['resource' => 'app.example.com'] : [],
        'errors' => $errors,
        'error_message' => $errors === [] ? '' : 'Configuration validation failed',
    ])->and($result['provider'])->toBe($provider);
})->with([
    'valid configuration' => [[]],
    'invalid configuration' => [['Invalid site']],
]);

\test('destroy execution reports the provider outcome', function (bool $success, string $message): void {
    /** @var DeploymentProviderInterface&MockInterface $provider */
    $provider = Mockery::mock(DeploymentProviderInterface::class);
    $project = new ProjectConfig('app', 'contract-test', '.', []);
    $profile = new ProfileConfig('production', 'main', []);
    $provider->shouldReceive('destroy')->once()->with($project, $profile)->andReturn($success);
    if ($success) {
        $provider->shouldNotReceive('getLastError');
    } else {
        $provider->shouldReceive('getLastError')->once()->andReturn($message);
    }

    \expect((new DestroyDeploymentFlow)->execute($provider, $project, $profile))
        ->toBe(['success' => $success, 'error_message' => $message]);
})->with([
    [true, ''],
    [false, 'Provider refused destruction'],
]);
