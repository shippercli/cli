<?php

declare(strict_types=1);

namespace App\Actions;

use App\Providers\Deployment\PloiProvider;

final class GetAllSitesAction
{
    /**
     * Get all sites from Ploi server.
     *
     * @return array<int, array{site_id: int, domain: string}>
     */
    public function handle(PloiProvider $provider): array
    {
        $client = $provider->getClient();
        $serverId = (int) $provider->getServerId();

        $server = $client->server($serverId);
        $sitesResponse = $server->sites()->get();

        $siteData = $sitesResponse->getJson()->data ?? null;
        if ($siteData === null || ! \is_array($siteData)) {
            return [];
        }

        $sites = [];
        foreach ($siteData as $site) {
            if (\is_object($site) && \property_exists($site, 'id') && \property_exists($site, 'domain')) {
                $sites[] = [
                    'site_id' => (int) $site->id,
                    'domain' => (string) $site->domain,
                ];
            }
        }

        return $sites;
    }
}
