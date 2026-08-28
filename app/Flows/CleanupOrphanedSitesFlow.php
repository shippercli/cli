<?php

declare(strict_types=1);

namespace App\Flows;

use App\Actions\FindOrphanedSitesAction;
use App\Actions\GetOpenPullRequestsAction;
use App\Actions\LoadConfigurationAction;
use App\Deployment\ContractDeploymentProviderAdapter;
use App\Deployment\ProviderFactory;

final class CleanupOrphanedSitesFlow
{
    /**
     * Cleanup orphaned preview sites.
     *
     * @return array{success: bool, orphaned_sites: array<int, array{site_id: int, domain: string, pr_number: int}>, deleted: int, failed: int, error_message: string}
     */
    public function handle(string $configPath, string $githubRepo, string $githubToken, bool $dryRun = false): array
    {
        $loadAction = new LoadConfigurationAction;
        $getOpenPRsAction = new GetOpenPullRequestsAction;
        $findOrphanedAction = new FindOrphanedSitesAction;

        $config = $loadAction->handle($configPath);

        $providerFactory = new ProviderFactory($config->providers());
        $projects = $config->projects();

        if ($projects === []) {
            return [
                'success' => true,
                'orphaned_sites' => [],
                'deleted' => 0,
                'failed' => 0,
                'error_message' => 'No projects configured',
            ];
        }

        $prResult = $getOpenPRsAction->handle($githubRepo, $githubToken);
        if (! $prResult['success']) {
            return [
                'success' => false,
                'orphaned_sites' => [],
                'deleted' => 0,
                'failed' => 0,
                'error_message' => "Failed to fetch open PRs: {$prResult['error']}",
            ];
        }

        $targets = [];
        $orphanedSites = [];
        foreach ($projects as $project) {
            $provider = $providerFactory->create($project->provider());
            $capabilityProvider = $provider instanceof ContractDeploymentProviderAdapter
                ? $provider->contractProvider()
                : $provider;
            if (! \method_exists($capabilityProvider, 'listSites') || ! \method_exists($capabilityProvider, 'deleteSiteWithDatabases')) {
                continue;
            }

            foreach ($project->profiles() as $profile) {
                $sites = $capabilityProvider->listSites($project, $profile);
                if (! \is_array($sites)) {
                    continue;
                }

                $normalizedSites = [];
                foreach ($sites as $site) {
                    if (! \is_array($site)) {
                        continue;
                    }

                    $siteId = $site['site_id'] ?? null;
                    $domain = $site['domain'] ?? null;
                    if (! \is_int($siteId) || ! \is_string($domain)) {
                        continue;
                    }

                    $normalizedSites[] = ['site_id' => $siteId, 'domain' => $domain];
                }

                $orphans = $findOrphanedAction->handle($normalizedSites, $prResult['prs'], $projects);
                foreach ($orphans as $orphan) {
                    $key = $project->name().'|'.$profile->name().'|'.$orphan['site_id'];
                    $targets[$key] = [$capabilityProvider, $project, $profile, $orphan['site_id']];
                    $orphanedSites[$key] = $orphan;
                }
            }
        }

        if ($targets === []) {
            return [
                'success' => true,
                'orphaned_sites' => [],
                'deleted' => 0,
                'failed' => 0,
                'error_message' => '',
            ];
        }

        if ($dryRun) {
            return [
                'success' => true,
                'orphaned_sites' => \array_values($orphanedSites),
                'deleted' => 0,
                'failed' => 0,
                'error_message' => '',
            ];
        }

        $deleted = 0;
        $failed = 0;

        foreach ($targets as [$capabilityProvider, $project, $profile, $siteId]) {
            try {
                if ($capabilityProvider->deleteSiteWithDatabases($project, $profile, $siteId)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        return [
            'success' => $failed === 0,
            'orphaned_sites' => \array_values($orphanedSites),
            'deleted' => $deleted,
            'failed' => $failed,
            'error_message' => '',
        ];
    }
}
