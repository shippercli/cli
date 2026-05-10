<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Ploi;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\Contracts\AliasManagerInterface;
use Ploi\Ploi;

final class PloiAliasManager implements AliasManagerInterface
{
    private ?Ploi $client = null;

    public function __construct(
        private readonly string $apiKey,
    ) {}

    /**
     * @return array<string>
     */
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

    /**
     * @param array<int, string> $aliases
     *
     * @return array<string, mixed>
     */
    public function apply(int $serverId, int $siteId, array $aliases): array
    {
        if ($aliases === []) {
            return ['success' => true, 'message' => 'No aliases to configure'];
        }

        try {
            $client = $this->getClient();
            $server = $client->server($serverId);
            $site = $server->sites($siteId);

            $response = $site->alias()->create($aliases);

            return [
                'success' => true,
                'message' => 'Aliases configured successfully',
                'response' => $response->getJson(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to configure domain aliases: {$e->getMessage()}",
            ];
        }
    }

    private function getClient(): Ploi
    {
        if ($this->client === null) {
            $this->client = new Ploi($this->apiKey);
        }

        return $this->client;
    }
}
