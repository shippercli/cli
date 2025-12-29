<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;

final class PloiProvider extends AbstractDeploymentProvider
{
    public function getName(): string
    {
        return 'ploi';
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        // Validate Ploi-specific configuration
        if (! isset($this->config['api_key']) || $this->config['api_key'] === '') {
            $errors[] = 'Ploi API key is required';
        }

        $serverId = $profile->get('server_id');
        if ($serverId === null || $serverId === '') {
            $errors[] = "Server ID is required for profile: {$profile->name()}";
        }

        $siteId = $profile->get('site_id');
        if ($siteId === null || $siteId === '') {
            $errors[] = "Site ID is required for profile: {$profile->name()}";
        }

        return $errors;
    }

    public function plan(ProjectConfig $project, ProfileConfig $profile): array
    {
        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $profile->branch(),
            'path' => $project->path(),
            'server_id' => $profile->get('server_id'),
            'site_id' => $profile->get('site_id'),
            'actions' => [
                'Deploy site via Ploi API',
                'Run deployment script',
                'Refresh OPcache if configured',
            ],
            'note' => 'This is a dry-run. No actual deployment will occur.',
        ];
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        // Stub implementation - would make actual Ploi API calls here
        // Example: POST https://ploi.io/api/servers/{server_id}/sites/{site_id}/deploy

        return true; // Stub: assume success
    }
}
