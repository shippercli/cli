<?php

declare(strict_types=1);

namespace App\Flows;

use App\Actions\DeleteSiteAction;
use App\Actions\FindOrphanedSitesAction;
use App\Actions\GetAllSitesAction;
use App\Actions\GetOpenPullRequestsAction;
use App\Actions\LoadConfigurationAction;
use App\Deployment\PloiProvider;
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
        $getAllSitesAction = new GetAllSitesAction;
        $getOpenPRsAction = new GetOpenPullRequestsAction;
        $findOrphanedAction = new FindOrphanedSitesAction;
        $deleteSiteAction = new DeleteSiteAction;

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

        $ploiProvider = null;
        foreach ($projects as $project) {
            $provider = $providerFactory->create($project->provider());
            if ($provider instanceof PloiProvider) {
                $ploiProvider = $provider;
                break;
            }
        }

        if ($ploiProvider === null) {
            return [
                'success' => false,
                'orphaned_sites' => [],
                'deleted' => 0,
                'failed' => 0,
                'error_message' => 'No projects using Ploi provider found',
            ];
        }

        $allSites = $getAllSitesAction->handle($ploiProvider);
        if ($allSites === []) {
            return [
                'success' => true,
                'orphaned_sites' => [],
                'deleted' => 0,
                'failed' => 0,
                'error_message' => '',
            ];
        }

        $prResult = $getOpenPRsAction->handle($githubRepo, $githubToken);

        // Check if GitHub API call failed
        if (! $prResult['success']) {
            return [
                'success' => false,
                'orphaned_sites' => [],
                'deleted' => 0,
                'failed' => 0,
                'error_message' => "Failed to fetch open PRs: {$prResult['error']}",
            ];
        }

        $openPRs = $prResult['prs'];
        $orphanedSites = $findOrphanedAction->handle($allSites, $openPRs, $projects);

        if ($orphanedSites === [] || $dryRun) {
            return [
                'success' => true,
                'orphaned_sites' => $orphanedSites,
                'deleted' => 0,
                'failed' => 0,
                'error_message' => '',
            ];
        }

        $deleted = 0;
        $failed = 0;

        foreach ($orphanedSites as $site) {
            try {
                if ($deleteSiteAction->handle($ploiProvider, $site['site_id'])) {
                    $deleted++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return [
            'success' => $failed === 0,
            'orphaned_sites' => $orphanedSites,
            'deleted' => $deleted,
            'failed' => $failed,
            'error_message' => '',
        ];
    }
}
