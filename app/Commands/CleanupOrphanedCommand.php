<?php

declare(strict_types=1);

namespace App\Commands;

use App\Config\ConfigLoader;
use App\Config\ProjectConfig;
use App\Providers\Deployment\PloiProvider;
use App\Providers\Deployment\ProviderFactory;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

final class CleanupOrphanedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup-orphaned 
                            {--config=deployer.yml : Path to config file}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup orphaned preview sites that no longer have an open PR';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configPath = $this->option('config');
        \assert(\is_string($configPath));

        $dryRun = $this->option('dry-run');
        \assert(\is_bool($dryRun));

        $force = $this->option('force');
        \assert(\is_bool($force));

        try {
            $loader = new ConfigLoader($configPath);
            $config = $loader->load();

            // Get GitHub token from environment
            $githubToken = \getenv('GITHUB_TOKEN');
            if ($githubToken === false || $githubToken === '') {
                $this->error('GITHUB_TOKEN environment variable is required');

                return self::FAILURE;
            }

            // Get GitHub repository from environment
            $githubRepo = \getenv('GITHUB_REPOSITORY');
            if ($githubRepo === false || $githubRepo === '') {
                $this->error('GITHUB_REPOSITORY environment variable is required (format: owner/repo)');

                return self::FAILURE;
            }

            $this->info('Starting cleanup of orphaned preview sites...');
            $this->line('');

            // Get Ploi provider
            $providerFactory = new ProviderFactory($config->providers());
            $projects = $config->projects();

            if ($projects === []) {
                $this->warn('No projects configured');

                return self::SUCCESS;
            }

            // Assume first project uses Ploi (we can enhance this later)
            $firstProject = null;
            foreach ($projects as $project) {
                $firstProject = $project;
                break;
            }

            if ($firstProject === null) {
                $this->warn('No projects found');

                return self::SUCCESS;
            }

            $provider = $providerFactory->create($firstProject->provider());

            if (! $provider instanceof PloiProvider) {
                $this->error('Only Ploi provider is supported for cleanup');

                return self::FAILURE;
            }

            // Get all sites from Ploi
            $allSites = $this->getAllSites($provider);

            if ($allSites === []) {
                $this->info('No sites found on server');

                return self::SUCCESS;
            }

            $this->info('Found '.\count($allSites).' total sites on server');
            $this->line('');

            // Get open PRs from GitHub
            $openPRs = $this->getOpenPullRequests($githubRepo, $githubToken);
            $this->info('Found '.\count($openPRs).' open PRs');
            $this->line('');

            // Find orphaned preview sites
            $orphanedSites = $this->findOrphanedSites($allSites, $openPRs, $projects);

            if ($orphanedSites === []) {
                $this->info('✓ No orphaned preview sites found!');

                return self::SUCCESS;
            }

            $this->warn('Found '.\count($orphanedSites).' orphaned preview sites:');
            foreach ($orphanedSites as $site) {
                $this->line('  - '.$site['domain'].' (PR #'.$site['pr_number'].')');
            }
            $this->line('');

            if ($dryRun) {
                $this->info('[DRY RUN] Would delete '.\count($orphanedSites).' sites');

                return self::SUCCESS;
            }

            // Confirm deletion
            if (! $force && ! $this->confirm('Do you want to delete these sites?', false)) {
                $this->warn('Cleanup cancelled.');

                return self::SUCCESS;
            }

            // Delete orphaned sites
            $deleted = 0;
            $failed = 0;

            foreach ($orphanedSites as $site) {
                $this->line('Deleting site: '.$site['domain'].'...');

                if ($this->deleteSite($provider, $site['site_id'])) {
                    $deleted++;
                    $this->info('  ✓ Deleted successfully');
                } else {
                    $failed++;
                    $this->error('  ✗ Failed to delete');
                }
            }

            $this->line('');
            $this->info("Cleanup complete: {$deleted} deleted, {$failed} failed");

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Get all sites from Ploi server.
     *
     * @return array<int, array{site_id: int, domain: string}>
     */
    private function getAllSites(PloiProvider $provider): array
    {
        $client = $provider->getClient();
        $serverId = (int) $provider->getServerId();

        $server = $client->server($serverId);
        $sitesResponse = $server->sites()->get();

        $siteData = $sitesResponse->getJson()->data ?? null;
        if ($siteData === null || ! \is_array($siteData)) {
            return [];
        }

        $sites = [];
        foreach ($siteData as $site) {
            if (\is_object($site) && \property_exists($site, 'id') && \property_exists($site, 'domain')) {
                $sites[] = [
                    'site_id' => (int) $site->id,
                    'domain' => (string) $site->domain,
                ];
            }
        }

        return $sites;
    }

    /**
     * Get open pull requests from GitHub.
     *
     * @return array<int>
     */
    private function getOpenPullRequests(string $repo, string $token): array
    {
        try {
            $client = new Client([
                'base_uri' => 'https://api.github.com',
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'Deployer-Cleanup',
                ],
            ]);

            $response = $client->get("/repos/{$repo}/pulls", [
                'query' => [
                    'state' => 'open',
                    'per_page' => 100,
                ],
            ]);

            $body = (string) $response->getBody();
            $prs = \json_decode($body, true);

            if (! \is_array($prs)) {
                return [];
            }

            $prNumbers = [];
            foreach ($prs as $pr) {
                if (\is_array($pr) && isset($pr['number']) && \is_int($pr['number'])) {
                    $prNumbers[] = $pr['number'];
                }
            }

            return $prNumbers;
        } catch (\Exception $e) {
            $this->error("Failed to fetch open PRs: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Find orphaned preview sites.
     *
     * @param array<int, array{site_id: int, domain: string}> $sites
     * @param array<int> $openPRs
     * @param array<string, ProjectConfig> $projects
     *
     * @return array<int, array{site_id: int, domain: string, pr_number: int}>
     */
    private function findOrphanedSites(array $sites, array $openPRs, array $projects): array
    {
        $orphaned = [];

        foreach ($sites as $site) {
            $domain = $site['domain'];

            // Extract PR number from domain using preview patterns
            $prNumber = $this->extractPRNumber($domain, $projects);

            if ($prNumber === null) {
                // Not a preview site, skip
                continue;
            }

            // Check if PR is still open
            if (! \in_array($prNumber, $openPRs, true)) {
                $orphaned[] = [
                    'site_id' => $site['site_id'],
                    'domain' => $domain,
                    'pr_number' => $prNumber,
                ];
            }
        }

        return $orphaned;
    }

    /**
     * Extract PR number from domain.
     *
     * @param array<string, ProjectConfig> $projects
     */
    private function extractPRNumber(string $domain, array $projects): ?int
    {
        // Try to match preview domain patterns from projects
        foreach ($projects as $project) {
            $profiles = $project->profiles();

            foreach ($profiles as $profile) {
                if ($profile->name() !== 'preview') {
                    continue;
                }

                $previewDomain = $profile->get('domain');
                if (! \is_string($previewDomain)) {
                    continue;
                }

                // Convert preview domain pattern to regex
                // Example: "api-preview-${GITHUB_PR_NUMBER}.ulties.dev" -> "api-preview-(\d+)\.ulties\.dev"
                $pattern = $previewDomain;
                $pattern = \str_replace('${GITHUB_PR_NUMBER}', '(\d+)', $pattern);
                $pattern = \str_replace('.', '\.', $pattern);
                $pattern = '/^'.$pattern.'$/';

                if (\preg_match($pattern, $domain, $matches) === 1) {
                    if (isset($matches[1]) && \is_numeric($matches[1])) {
                        return (int) $matches[1];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Delete a site from Ploi.
     */
    private function deleteSite(PloiProvider $provider, int $siteId): bool
    {
        try {
            $client = $provider->getClient();
            $serverId = (int) $provider->getServerId();

            $server = $client->server($serverId);
            $site = $server->sites($siteId);
            $deleteResponse = $site->delete();

            $deleteData = $deleteResponse->getJson();
            if (isset($deleteData->message) && \is_string($deleteData->message)) {
                $messageLower = \strtolower($deleteData->message);
                if (\str_contains($messageLower, 'error') || \str_contains($messageLower, 'failed')) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
