<?php

declare(strict_types=1);

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\ContractDeploymentProviderAdapter;
use App\Deployment\DeploymentProviderInterface;
use App\Deployment\ProviderRegistry;
use App\Flows\ApplyDeploymentFlow;
use Mockery\MockInterface;
use ShipperCli\Contracts\DeploymentProviderInterface as ContractProvider;
use Tests\Fixtures\TestBlockedPlanProvider;

\afterEach(function (): void {
    Mockery::close();
});

\test('apply flow interprets the contract provider post-apply result', function (mixed $postApply, mixed $expected): void {
    $contractProvider = new class($postApply) implements ContractProvider
    {
        public bool $postApplyCalled = false;

        public function __construct(private readonly mixed $postApply) {}

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
            return 'stand-in';
        }

        public function getLastError(): string
        {
            return '';
        }

        public function postApply(object $project, object $profile): mixed
        {
            $this->postApplyCalled = true;

            return $this->postApply;
        }
    };
    $provider = new ContractDeploymentProviderAdapter($contractProvider);
    $project = new ProjectConfig('api', 'stand-in', '.', []);
    $profile = new ProfileConfig('production', 'main', []);

    $result = (new ApplyDeploymentFlow)->execute($provider, $project, $profile, []);

    \expect($contractProvider->postApplyCalled)->toBeTrue()
        ->and($result)->toBe($expected);
})->with([
    'success filters non-string logs and clears the message' => [
        ['success' => true, 'message' => 'configured', 'logs' => [4 => 'ready', 9 => false, 12 => 'verified']],
        ['success' => true, 'logs' => ['ready', 'verified'], 'error_message' => ''],
    ],
    'failure preserves a useful message and logs' => [
        ['success' => false, 'message' => 'Configuration rejected', 'logs' => ['deploy completed']],
        ['success' => false, 'logs' => ['deploy completed'], 'error_message' => 'Configuration rejected'],
    ],
    'malformed result fails closed' => [
        false,
        ['success' => false, 'logs' => [], 'error_message' => 'Provider returned an invalid post-apply result'],
    ],
    'malformed fields fail closed' => [
        ['success' => 'true', 'message' => 123, 'logs' => 'ready'],
        ['success' => false, 'logs' => [], 'error_message' => 'Provider post-apply configuration failed'],
    ],
]);

\test('planning reports unknown project and profile before resolving a provider', function (string $project, string $profile, string $message): void {
    $flow = new ApplyDeploymentFlow(
        providerResolver: static fn (): never => throw new LogicException('Provider must not be resolved'),
    );

    $result = $flow->handle('shipper.yml.example', $project, $profile);

    \expect($result['success'])->toBeFalse()
        ->and($result['project']?->name())->toBe($project === 'api' ? 'api' : null)
        ->and($result['profile'])->toBeNull()
        ->and($result['plan'])->toBe([])
        ->and($result['errors'])->toBe([])
        ->and($result['error_message'])->toBe($message)
        ->and($result['provider'])->toBeNull();
})->with([
    'unknown project' => ['absent', 'production', 'Project not found: absent'],
    'unknown profile' => ['api', 'absent', 'Profile not found: absent'],
]);

\test('planning returns provider validation errors without planning or executing', function (): void {
    /** @var DeploymentProviderInterface&MockInterface $provider */
    $provider = Mockery::mock(DeploymentProviderInterface::class);
    $provider->shouldReceive('validate')->once()->andReturn(['Domain is required']);
    $provider->shouldNotReceive('plan');
    $provider->shouldNotReceive('apply');
    $flow = new ApplyDeploymentFlow(providerResolver: static fn (): DeploymentProviderInterface => $provider);

    $result = $flow->handle('shipper.yml.example', 'api', 'production');

    \expect($result['success'])->toBeFalse()
        ->and($result['plan'])->toBe([])
        ->and($result['errors'])->toBe(['Domain is required'])
        ->and($result['error_message'])->toBe('Configuration validation failed')
        ->and($result['provider'])->toBe($provider);
});

\test('planning returns the resolved context and refuses a blocked plan', function (bool $blocked): void {
    $plan = ['blocked' => $blocked, 'actions' => ['Ownership collision requires review.']];
    /** @var DeploymentProviderInterface&MockInterface $provider */
    $provider = Mockery::mock(DeploymentProviderInterface::class);
    $provider->shouldReceive('validate')->once()->andReturn([]);
    $provider->shouldReceive('plan')->once()->andReturn($plan);
    $provider->shouldNotReceive('apply');
    $flow = new ApplyDeploymentFlow(providerResolver: static fn (): DeploymentProviderInterface => $provider);

    if ($blocked) {
        \expect(fn () => $flow->handle('shipper.yml.example', 'api', 'production'))
            ->toThrow(RuntimeException::class, 'Deployment plan is blocked. Ownership collision requires review.');

        return;
    }

    $result = $flow->handle('shipper.yml.example', 'api', 'production');

    \expect($result['success'])->toBeTrue()
        ->and($result['project']?->name())->toBe('api')
        ->and($result['profile']?->name())->toBe('production')
        ->and($result['plan'])->toBe($plan)
        ->and($result['errors'])->toBe([])
        ->and($result['error_message'])->toBe('')
        ->and($result['provider'])->toBe($provider);
})->with([false, true]);

\test('execution refuses blocked plans before calling the provider', function (): void {
    /** @var DeploymentProviderInterface&MockInterface $provider */
    $provider = Mockery::mock(DeploymentProviderInterface::class);
    $provider->shouldNotReceive('apply');
    $result = (new ApplyDeploymentFlow)->execute(
        $provider,
        new ProjectConfig('api', 'stand-in', '.', []),
        new ProfileConfig('production', 'main', []),
        ['blocked' => true, 'actions' => ['Ownership collision requires review.']],
    );

    \expect($result)->toBe([
        'success' => false,
        'logs' => [],
        'error_message' => 'Deployment plan is blocked. Ownership collision requires review.',
    ]);
});

\test('execution returns the original deployment error or succeeds without a post-apply hook', function (bool $success, string $message): void {
    $project = new ProjectConfig('api', 'stand-in', '.', []);
    $profile = new ProfileConfig('production', 'main', []);
    /** @var DeploymentProviderInterface&MockInterface $provider */
    $provider = Mockery::mock(DeploymentProviderInterface::class);
    $provider->shouldReceive('apply')->once()->with($project, $profile)->andReturn($success);
    if ($success) {
        $provider->shouldNotReceive('getLastError');
    } else {
        $provider->shouldReceive('getLastError')->once()->andReturn($message);
    }

    \expect((new ApplyDeploymentFlow)->execute($provider, $project, $profile, []))
        ->toBe(['success' => $success, 'logs' => [], 'error_message' => $message]);
})->with([
    'provider failure' => [false, 'Upload failed after creating the site'],
    'provider success' => [true, ''],
]);

\test('planning resolves the registered provider and applies its configuration by default', function (): void {
    ProviderRegistry::register('apply-flow-test', TestBlockedPlanProvider::class);
    $config = \tempnam(\sys_get_temp_dir(), 'shipper-apply-flow-');
    \assert(\is_string($config));

    try {
        \file_put_contents($config, \json_encode([
            'providers' => ['apply-flow-test' => ['plan_blocked' => false]],
            'projects' => [
                'api' => [
                    'provider' => 'apply-flow-test',
                    'path' => '.',
                    'profiles' => ['production' => ['branch' => 'main']],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $result = (new ApplyDeploymentFlow)->handle($config, 'api', 'production');

        \expect($result['success'])->toBeTrue()
            ->and($result['provider'])->toBeInstanceOf(ContractDeploymentProviderAdapter::class)
            ->and($result['plan'])->toBe(['blocked' => false, 'actions' => ['Ownership collision requires review.']]);
    } finally {
        \unlink($config);
    }
});
