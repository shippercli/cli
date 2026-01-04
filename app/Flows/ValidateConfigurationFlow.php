<?php

declare(strict_types=1);

namespace App\Flows;

use App\Actions\LoadConfigurationAction;
use App\Actions\ValidateProjectAction;
use App\Providers\Deployment\ProviderFactory;

final class ValidateConfigurationFlow
{
    /**
     * Validate entire configuration file.
     *
     * @return array{success: bool, errors: array<string, array<string, array<int, string>>>}
     */
    public function handle(string $configPath): array
    {
        $loadAction = new LoadConfigurationAction;
        $validateAction = new ValidateProjectAction;

        $config = $loadAction->handle($configPath);
        $providerFactory = new ProviderFactory($config->providers());

        $allErrors = [];
        $hasErrors = false;

        foreach ($config->projects() as $project) {
            $projectName = $project->name();
            $projectErrors = [];

            try {
                $provider = $providerFactory->create($project->provider());

                foreach ($project->profiles() as $profile) {
                    $profileName = $profile->name();
                    $errors = $validateAction->handle($provider, $project, $profile);

                    if ($errors !== []) {
                        $hasErrors = true;
                        $projectErrors[$profileName] = $errors;
                    }
                }
            } catch (\InvalidArgumentException $e) {
                $hasErrors = true;
                $projectErrors['_provider'] = [$e->getMessage()];
            }

            if ($projectErrors !== []) {
                $allErrors[$projectName] = $projectErrors;
            }
        }

        return [
            'success' => ! $hasErrors,
            'errors' => $allErrors,
        ];
    }
}
