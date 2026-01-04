<?php

declare(strict_types=1);

namespace App\Actions;

use App\Providers\Deployment\PloiProvider;
use Psr\Log\LoggerInterface;

final class DeleteSiteAction
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Delete a site from Ploi.
     *
     * @throws \InvalidArgumentException If server ID is invalid
     * @throws \RuntimeException If deletion fails unexpectedly
     */
    public function handle(PloiProvider $provider, int $siteId): bool
    {
        $serverId = $provider->getServerId();

        if ($serverId === '' || ! \ctype_digit($serverId)) {
            throw new \InvalidArgumentException('Invalid server ID: must be numeric');
        }

        try {
            $client = $provider->getClient();
            $serverIdInt = (int) $serverId;

            $server = $client->server($serverIdInt);
            $site = $server->sites($siteId);
            $site->delete();

            return true;
        } catch (\Ploi\Exceptions\Http\NotFound $e) {
            // Site already deleted - this is acceptable
            return true;
        } catch (\Exception $e) {
            if ($this->logger !== null) {
                $this->logger->error('Failed to delete site', [
                    'site_id' => $siteId,
                    'server_id' => $serverId,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw new \RuntimeException(
                "Failed to delete site {$siteId}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }
}
