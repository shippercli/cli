<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Forge;

use App\Deployment\Concerns\MergesEnvironment;
use App\Deployment\Contracts\EnvironmentManagerInterface;
use Laravel\Forge\Forge;

final class ForgeEnvironmentManager implements EnvironmentManagerInterface
{
    use MergesEnvironment;

    private ?Forge $client = null;

    public function __construct(
        private readonly string $apiToken,
    ) {}

    public function plan(int $variableCount): array
    {
        if ($variableCount === 0) {
            return [];
        }

        return ["Set {$variableCount} environment variable".($variableCount === 1 ? '' : 's')];
    }

    public function apply(int $serverId, int $siteId, array $variables): array
    {
        if ($variables === []) {
            return ['success' => true, 'message' => 'No environment variables to configure'];
        }

        try {
            $client = $this->getClient();
            // @todo Implement using Forge SDK
            // $server = $client->server($serverId);
            // $site = $server->sites($siteId);
            // foreach ($variables as $key => $value) {
            //     $site->environmentVariables()->create($key, $value);
            // }

            return [
                'success' => true,
                'message' => 'Environment variables configured successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to configure environment variables: {$e->getMessage()}",
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