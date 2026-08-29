<?php

declare(strict_types=1);

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\ContractDeploymentProviderAdapter;
use App\Flows\ApplyDeploymentFlow;
use ShipperCli\Contracts\DeploymentProviderInterface;

\test('apply flow runs post-apply capability on a contract provider', function (): void {
    $contractProvider = new class implements DeploymentProviderInterface
    {
        public bool $postApplyCalled = false;

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

        /** @return array{success: bool, message: string, logs: array<int, string>} */
        public function postApply(object $project, object $profile): array
        {
            $this->postApplyCalled = true;

            return ['success' => true, 'message' => 'configured', 'logs' => ['ready']];
        }
    };
    $provider = new ContractDeploymentProviderAdapter($contractProvider);
    $project = new ProjectConfig('api', 'stand-in', '.', []);
    $profile = new ProfileConfig('production', 'main', []);

    $result = (new ApplyDeploymentFlow)->execute($provider, $project, $profile, []);

    \expect($contractProvider->postApplyCalled)->toBeTrue()
        ->and($result)->toBe(['success' => true, 'logs' => ['ready'], 'error_message' => '']);
});
