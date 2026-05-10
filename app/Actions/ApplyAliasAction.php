<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\Providers\Ploi\PloiAliasManager;

final class ApplyAliasAction
{
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
            return ['success' => false, 'message' => "Provider {$providerName} not supported for aliases"];
        }

        $aliases = $profile->aliases();
        if ($aliases === []) {
            return ['success' => true, 'message' => 'No aliases to configure'];
        }

        $manager = new PloiAliasManager($apiKey);

        return $manager->apply($serverId, $siteId, $aliases);
    }
}
