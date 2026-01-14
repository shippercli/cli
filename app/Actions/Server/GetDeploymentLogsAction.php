<?php

declare(strict_types=1);

namespace App\Actions\Server;

use App\Deployment\PloiProvider;

final class GetDeploymentLogsAction
{
    /**
     * Get deployment logs from Ploi provider.
     *
     * @return array<int, string>
     */
    public function handle(PloiProvider $provider, int $serverId, int $siteId): array
    {
        if ($siteId === 0) {
            return [];
        }

        return $provider->getDeploymentLogs($serverId, $siteId);
    }
}
