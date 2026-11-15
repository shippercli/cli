<?php

declare(strict_types=1);

use App\Actions\ResolveDeploymentContextAction;
use App\Deployment\ContractDeploymentProviderAdapter;
use App\Deployment\ProviderRegistry;
use Tests\Fixtures\TestContractProvider;
use Tests\Fixtures\TestShipperPlugin;

\beforeEach(function (): void {
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    ProviderRegistry::register('invalid-context-test', TestContractProvider::class);
});

\test('resolves the configured project profile and provider', function (): void {
    $context = (new ResolveDeploymentContextAction)->handle(
        __DIR__.'/../../Fixtures/provider-operations-config.yml',
        'app',
        'production',
    );

    \expect($context['project']->name())->toBe('app')
        ->and($context['profile']->name())->toBe('production')
        ->and($context['profile']->branch())->toBe('main')
        ->and($context['provider'])->toBeInstanceOf(ContractDeploymentProviderAdapter::class)
        ->and($context['provider']->getName())->toBe('contract-test');
});

\test('rejects invalid deployment contexts with an actionable message', function (string $project, string $profile, string $message): void {
    $action = new ResolveDeploymentContextAction;

    \expect(fn () => $action->handle(__DIR__.'/../../Fixtures/provider-operations-config.yml', $project, $profile))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    ['missing', 'production', 'Project not found: missing'],
    ['app', 'missing', 'Profile not found: missing'],
    ['invalid', 'production', "Configuration validation failed:\n  - Server is required.\n  - Domain is required."],
]);
