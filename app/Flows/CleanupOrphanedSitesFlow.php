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
    public function __construct(
        private readonly GetOpenPullRequestsAction $getOpenPRsAction = new GetOpenPullRequestsAction,
    ) {}

    /**
     * Cleanup orphaned preview sites.
     *
     * @return array{success: bool, orphaned_sites: array<int, array{site_id: int, domain: string, pr_number: int}>, deleted: int, failed: int, error_message: string}
     */
    public function handle(string $configPath, string $githubRepo, string $githubToken, bool $dryRun = false): array
    {
        $loadAction = new LoadConfigurationAction;
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

        $prResult = $this->getOpenPRsAction->handle($githubRepo, $githubToken);
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
            if (! $this->matchesRepository($project->repository(), $githubRepo)) {
                continue;
            }
            $provider = $providerFactory->create($project->provider());
            $capabilityProvider = $provider instanceof ContractDeploymentProviderAdapter
                ? $provider->contractProvider()
                : $provider;
            if (! \method_exists($capabilityProvider, 'listSites') || ! \method_exists($capabilityProvider, 'deleteSiteWithDatabases')) {
                continue;
            }

            foreach ($project->profiles() as $profile) {
                if ($profile->name() !== 'preview') {
                    continue;
                }
                $sites = $capabilityProvider->listSites($project, $profile);
                if (! \is_array($sites)) {
                    throw new \RuntimeException('Provider returned invalid preview site inventory.');
                }

                $normalizedSites = [];
                foreach ($sites as $site) {
                    if (! \is_array($site)) {
                        throw new \RuntimeException('Provider returned an invalid preview site inventory entry.');
                    }

                    $siteId = $site['site_id'] ?? null;
                    $domain = $site['domain'] ?? null;
                    if (! \is_int($siteId) || $siteId <= 0 || ! \is_string($domain) || \trim($domain) === '') {
                        throw new \RuntimeException('Provider returned an invalid preview site identity.');
                    }

                    $normalizedSites[] = ['site_id' => $siteId, 'domain' => $domain];
                }

                $orphans = $findOrphanedAction->handle($normalizedSites, $prResult['prs'], $profile);
                foreach ($orphans as $orphan) {
                    $key = \json_encode([$project->name(), $profile->name(), $orphan['site_id']], JSON_THROW_ON_ERROR);
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

    /** @param array<string, mixed> $repository */
    private function matchesRepository(array $repository, string $githubRepo): bool
    {
        $name = $repository['name'] ?? null;
        if (! \is_string($name) || ($repository['provider'] ?? null) !== 'github') {
            return false;
        }
        if (\preg_match('#\A(?:https://github\.com/|git@github\.com:)([^/]+/[^/]+?)(?:\.git)?\z#i', $name, $matches) === 1) {
            $name = $matches[1];
        }

        return \strcasecmp($name, $githubRepo) === 0;
    }
}
