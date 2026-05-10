<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\Concerns\ResolvesDeployScript;
use App\Deployment\Providers\Ploi\PloiDeployScriptManager;

final class ApplyDeployScriptAction
{
    use ResolvesDeployScript;

    /**
     * @return array<string, mixed>
     */
    public function handle(
        string $providerName,
        string $apiKey,
        int $serverId,
        int $siteId,
        ProjectConfig $project,
        ProfileConfig $profile,
    ): array {
        if ($providerName !== 'ploi') {
            return ['success' => false, 'message' => "Provider {$providerName} not supported for deploy scripts"];
        }

        $script = $this->resolveDeployScript($project, $profile);

        if ($script === '') {
            return ['success' => true, 'message' => 'No deploy script to configure'];
        }

        $manager = new PloiDeployScriptManager($apiKey);

        return $manager->apply($serverId, $siteId, $script);
    }
}
