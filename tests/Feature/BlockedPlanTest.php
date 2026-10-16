<?php

declare(strict_types=1);

use App\Actions\CreateDeploymentPlanAction;
use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\ContractDeploymentProviderAdapter;
use App\Deployment\ProviderRegistry;
use App\Flows\ApplyDeploymentFlow;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\TestContractProvider;
use Tests\Fixtures\TestShipperPlugin;

\test('blocked provider plans fail plan and forced apply without deployment', function (mixed $blocked): void {
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    TestContractProvider::$applyCalls = 0;
    $config = \tempnam(\sys_get_temp_dir(), 'shipper-blocked-plan-');
    \assert(\is_string($config));
    \file_put_contents($config, \json_encode([
        'providers' => ['contract-test' => ['plan_blocked' => $blocked]],
        'projects' => ['api' => ['provider' => 'contract-test', 'path' => '.', 'profiles' => ['production' => ['branch' => 'main']]]],
    ], JSON_THROW_ON_ERROR));
    try {
        foreach (['plan', 'apply'] as $command) {
            $arguments = ['project' => 'api', '--config' => $config];
            if ($command === 'apply') {
                $arguments['--force'] = true;
            }
            \expect(Artisan::call($command, $arguments))->toBe(1)
                ->and(Artisan::output())->toContain('Deployment plan is blocked.', 'Ownership collision requires review.');
        }
        \expect(TestContractProvider::$applyCalls)->toBe(0);
    } finally {
        @\unlink($config);
    }
})->with([true, 'true', 'false', null, 1]);

\test('direct execution of a blocked plan refuses deployment', function (): void {
    TestContractProvider::$applyCalls = 0;
    $provider = new ContractDeploymentProviderAdapter(new TestContractProvider);
    $result = (new ApplyDeploymentFlow)->execute($provider, new ProjectConfig('api', 'contract-test', '.', []), new ProfileConfig('production', 'main', []), ['blocked' => true]);
    \expect($result['success'])->toBeFalse()->and(TestContractProvider::$applyCalls)->toBe(0);
});

\test('legacy plans and explicit false blocked flags remain executable', function (): void {
    foreach ([[], ['plan_blocked' => false]] as $config) {
        TestContractProvider::$applyCalls = 0;
        $provider = new ContractDeploymentProviderAdapter(new TestContractProvider($config));
        $project = new ProjectConfig('api', 'contract-test', '.', []);
        $profile = new ProfileConfig('production', 'main', []);
        $plan = (new CreateDeploymentPlanAction)->handle($provider, $project, $profile);
        $result = (new ApplyDeploymentFlow)->execute($provider, $project, $profile, $plan);
        \expect($result['success'])->toBeTrue()->and(TestContractProvider::$applyCalls)->toBe(1);
    }
});
