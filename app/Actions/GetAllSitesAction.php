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
     *
     * @throws \InvalidArgumentException If server ID is invalid
     */
    public function handle(PloiProvider $provider): array
    {
        $serverId = $provider->getServerId();

        if ($serverId === '' || ! \ctype_digit($serverId)) {
            throw new \InvalidArgumentException('Invalid server ID: must be numeric');
        }

        $client = $provider->getClient();
        $serverIdInt = (int) $serverId;

        $server = $client->server($serverIdInt);
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
