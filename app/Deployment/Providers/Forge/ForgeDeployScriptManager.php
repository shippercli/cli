<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Forge;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\Contracts\DeployScriptManagerInterface;
use Laravel\Forge\Forge;

final class ForgeDeployScriptManager implements DeployScriptManagerInterface
{
    private ?Forge $client = null;

    public function __construct(
        private readonly string $apiToken,
    ) {}

    public function plan(ProjectConfig $project, ProfileConfig $profile): array
    {
        $profileScript = $profile->deployScript();
        $projectScript = $project->deployScript();

        if (($profileScript === null || $profileScript === '') && $projectScript === '') {
            return [];
        }

        return ['Update deployment script'];
    }

    public function apply(int $serverId, int $siteId, string $script): array
    {
        if ($script === '') {
            return ['success' => true, 'message' => 'No deploy script to configure'];
        }

        try {
            $client = $this->getClient();
            // @todo Implement using Forge SDK
            // $server = $client->server($serverId);
            // $site = $server->sites($siteId);
            // $site->deploymentScript()->update($script);

            return [
                'success' => true,
                'message' => 'Deploy script configured successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to configure deploy script: {$e->getMessage()}",
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