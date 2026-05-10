<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\Providers\Ploi\PloiSslManager;

final class ApplySslAction
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
            return ['success' => false, 'message' => "Provider {$providerName} not supported for SSL"];
        }

        $ssl = $project->ssl();

        if (! $ssl->enabled()) {
            return ['success' => true, 'message' => 'SSL not enabled'];
        }

        $domainValue = $profile->get('domain');
        $domain = \is_string($domainValue) ? $domainValue : '';

        $manager = new PloiSslManager($apiKey);

        return $manager->apply($serverId, $siteId, $domain, $ssl);
    }
}
