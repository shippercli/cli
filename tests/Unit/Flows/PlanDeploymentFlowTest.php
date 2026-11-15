<?php

declare(strict_types=1);

use App\Deployment\ProviderRegistry;
use App\Flows\PlanDeploymentFlow;
use Tests\Fixtures\TestBlockedPlanProvider;
use Tests\Fixtures\TestContractProvider;
use Tests\Fixtures\TestShipperPlugin;

\beforeEach(function (): void {
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    ProviderRegistry::register('blocked-test', TestBlockedPlanProvider::class);
    ProviderRegistry::register('invalid-context-test', TestContractProvider::class);
});

\test('plan flow returns the plan and deployment context', function (): void {
    $result = (new PlanDeploymentFlow)->handle(__DIR__.'/../../Fixtures/provider-operations-config.yml', 'app', 'production');

    \expect($result)->toMatchArray([
        'success' => true,
        'plan' => ['provider' => 'contract-test', 'token' => 'configured'],
        'errors' => [],
        'error_message' => '',
    ])->and($result['project']?->name())->toBe('app')
        ->and($result['profile']?->name())->toBe('production');
});

\test('plan flow reports missing projects and profiles', function (string $project, string $profile, string $message): void {
    $result = (new PlanDeploymentFlow)->handle(__DIR__.'/../../Fixtures/provider-operations-config.yml', $project, $profile);

    \expect($result)->toMatchArray([
        'success' => false,
        'profile' => null,
        'plan' => [],
        'errors' => [],
        'error_message' => $message,
    ])->and($result['project']?->name())->toBe($project === 'app' ? 'app' : null);
})->with([
    ['missing', 'production', 'Project not found: missing'],
    ['app', 'missing', 'Profile not found: missing'],
]);

\test('plan flow returns the provider validation errors and an empty plan', function (): void {
    $result = (new PlanDeploymentFlow)->handle(__DIR__.'/../../Fixtures/provider-operations-config.yml', 'invalid', 'production');

    \expect($result)->toMatchArray([
        'success' => false,
        'plan' => [],
        'errors' => ['Server is required.', 'Domain is required.'],
        'error_message' => 'Configuration validation failed',
    ])->and($result['project']?->name())->toBe('invalid')
        ->and($result['profile']?->name())->toBe('production');
});

\test('plan flow rejects a provider plan blocked by an ownership collision', function (): void {
    $flow = new PlanDeploymentFlow;

    \expect(fn () => $flow->handle(__DIR__.'/../../Fixtures/provider-operations-config.yml', 'unsupported', 'production'))
        ->toThrow(RuntimeException::class, 'Deployment plan is blocked. Ownership collision requires review.');
});
