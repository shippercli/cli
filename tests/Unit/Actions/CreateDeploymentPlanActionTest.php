<?php

declare(strict_types=1);

use App\Actions\CreateDeploymentPlanAction;
use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\ContractDeploymentProviderAdapter;
use Tests\Fixtures\TestContractProvider;

\test('create plan action returns the provider plan', function (): void {
    $provider = new ContractDeploymentProviderAdapter(new TestContractProvider(['token' => 'configured']));
    $project = new ProjectConfig('app', 'contract-test', '.', []);
    $profile = new ProfileConfig('production', 'main', []);

    \expect((new CreateDeploymentPlanAction)->handle($provider, $project, $profile))
        ->toBe(['provider' => 'contract-test', 'token' => 'configured']);
});

\test('create plan action accepts plans with no block or an explicit false flag', function (array $plan): void {
    \expect(fn () => CreateDeploymentPlanAction::assertNotBlocked($plan))->not->toThrow(Throwable::class);
})->with([
    'no flag' => [[]],
    'false flag' => [['blocked' => false]],
]);

\test('create plan action keeps string reasons in blocked-plan errors', function (): void {
    \expect(fn () => CreateDeploymentPlanAction::assertNotBlocked([
        'blocked' => true,
        'actions' => ['Ownership collision.', 42, 'Review the target.'],
    ]))->toThrow(RuntimeException::class, 'Deployment plan is blocked. Ownership collision. Review the target.');
});

\test('create plan action rejects a malformed block flag even without usable reasons', function (): void {
    \expect(fn () => CreateDeploymentPlanAction::assertNotBlocked(['blocked' => 'false', 'actions' => 'invalid']))
        ->toThrow(RuntimeException::class, 'Deployment plan is blocked.');
});
