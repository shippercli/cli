<?php

declare(strict_types=1);

namespace App\Commands;

use App\Flows\Maintenance\CleanupOrphanedSitesFlow;
use Illuminate\Console\Command;

final class CleanupOrphanedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup-orphaned 
                            {--config=shipper.yml : Path to config file}
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
            $githubToken = \getenv('GITHUB_TOKEN');
            if ($githubToken === false || $githubToken === '') {
                $this->error('GITHUB_TOKEN environment variable is required');

                return self::FAILURE;
            }

            $githubRepo = \getenv('GITHUB_REPOSITORY');
            if ($githubRepo === false || $githubRepo === '') {
                $this->error('GITHUB_REPOSITORY environment variable is required (format: owner/repo)');

                return self::FAILURE;
            }

            $this->info('Starting cleanup of orphaned preview sites...');
            $this->line('');

            $flow = new CleanupOrphanedSitesFlow;

            // First, run in dry-run mode to find orphaned sites
            $result = $flow->handle($configPath, $githubRepo, $githubToken, true);

            if (! $result['success'] && $result['error_message'] !== '') {
                $this->error($result['error_message']);

                return self::FAILURE;
            }

            $orphanedSites = $result['orphaned_sites'];

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

            if (! $force && ! $this->confirm('Do you want to delete these sites?', false)) {
                $this->warn('Cleanup cancelled.');

                return self::SUCCESS;
            }

            // Actually delete the sites
            $this->line('');
            $this->info('Deleting sites...');

            $result = $flow->handle($configPath, $githubRepo, $githubToken, false);

            $deleted = $result['deleted'];
            $failed = $result['failed'];

            $this->line('');
            $this->info("Cleanup complete: {$deleted} deleted, {$failed} failed");

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
