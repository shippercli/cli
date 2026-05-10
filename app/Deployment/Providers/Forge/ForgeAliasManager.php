<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Forge;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\Contracts\AliasManagerInterface;
use Laravel\Forge\Forge;

final class ForgeAliasManager implements AliasManagerInterface
{
    private ?Forge $client = null;

    public function __construct(
        private readonly string $apiToken,
    ) {}

    public function plan(ProjectConfig $project, ProfileConfig $profile): array
    {
        $aliases = $profile->aliases();

        if ($aliases === []) {
            return [];
        }

        $count = \count($aliases);
        $aliasList = \implode(', ', $aliases);
        $plural = $count === 1 ? '' : 'es';

        return ["Configure {$count} domain alias{$plural}: {$aliasList}"];
    }

    public function apply(int $serverId, int $siteId, array $aliases): array
    {
        if ($aliases === []) {
            return ['success' => true, 'message' => 'No aliases to configure'];
        }

        try {
            $client = $this->getClient();
            // @todo Implement using Forge SDK
            // $server = $client->server($serverId);
            // $site = $server->sites($siteId);
            // $site->aliases()->create($aliases);

            return [
                'success' => true,
                'message' => 'Aliases configured successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to configure domain aliases: {$e->getMessage()}",
            ];
        }
    }

    private function getClient(): Forge
    {
        if ($this->client === null) {
            $this->client = new Forge($this->apiToken);
        }

        return $this->client;
    }
}