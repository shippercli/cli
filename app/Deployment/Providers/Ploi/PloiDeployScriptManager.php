<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Ploi;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\Contracts\DeployScriptManagerInterface;
use Ploi\Ploi;

final class PloiDeployScriptManager implements DeployScriptManagerInterface
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
        $profileScript = $profile->deployScript();
        $projectScript = $project->deployScript();

        if (($profileScript === null || $profileScript === '') && $projectScript === '') {
            return [];
        }

        return ['Update deployment script'];
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(int $serverId, int $siteId, string $script): array
    {
        if ($script === '') {
            return ['success' => true, 'message' => 'No deploy script to configure'];
        }

        try {
            $client = $this->getClient();
            $server = $client->server($serverId);
            $site = $server->sites($siteId);

            $response = $site->deployment()->updateDeployScript($script);

            return [
                'success' => true,
                'message' => 'Deploy script configured successfully',
                'response' => $response->getJson(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to configure deploy script: {$e->getMessage()}",
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
