<?php

declare(strict_types=1);

namespace App\Actions;

use App\Providers\Deployment\PloiProvider;

final class DeleteSiteAction
{
    /**
     * Delete a site from Ploi.
     */
    public function handle(PloiProvider $provider, int $siteId): bool
    {
        try {
            $client = $provider->getClient();
            $serverId = (int) $provider->getServerId();

            $server = $client->server($serverId);
            $site = $server->sites($siteId);
            $site->delete();

            return true;
        } catch (\Ploi\Exceptions\Http\NotFound $e) {
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
