<?php

declare(strict_types=1);

use App\Actions\GetOpenPullRequestsAction;
use App\Deployment\ProviderRegistry;
use App\Flows\CleanupOrphanedSitesFlow;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use Tests\Fixtures\TestContractProvider;
use Tests\Fixtures\TestShipperPlugin;
use Tests\TestCase;

\test('cleanup command stops before deletion when the second inventory fetch fails', function (): void {
    /** @var TestCase $this */
    $token = \getenv('GITHUB_TOKEN');
    $repo = \getenv('GITHUB_REPOSITORY');
    \putenv('GITHUB_TOKEN=test-token');
    \putenv('GITHUB_REPOSITORY=owner/repo');
    ProviderRegistry::registerPlugin(new TestShipperPlugin);
    TestContractProvider::$cleanupListings = 0;
    TestContractProvider::$cleanupDeletions = 0;
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, [], '[]'),
        new Response(200, [], '{"message":"invalid inventory"}'),
    ]))]);
    \app()->instance(CleanupOrphanedSitesFlow::class, new CleanupOrphanedSitesFlow(new GetOpenPullRequestsAction(client: $client)));
    $config = \tempnam(\sys_get_temp_dir(), 'shipper-cleanup-');
    \assert(\is_string($config));
    \file_put_contents($config, <<<'YAML'
providers:
  contract-test: {}
projects:
  api:
    provider: contract-test
    path: "."
    profiles:
      production:
        branch: main
        domain: production.example.com
      preview:
        branch: main
        domain: ${GITHUB_PR_NUMBER}.preview.example.com
YAML);
    try {
        $exit = Artisan::call('cleanup-orphaned', ['--config' => $config, '--force' => true]);
        \expect($exit)->toBe(1)
            ->and(Artisan::output())->toContain('Failed to fetch open PRs')
            ->and(\str_contains(Artisan::output(), 'Cleanup complete:'))->toBeFalse()
            ->and(TestContractProvider::$cleanupListings)->toBe(1)
            ->and(TestContractProvider::$cleanupDeletions)->toBe(0);
    } finally {
        @\unlink($config);
        \putenv($token === false ? 'GITHUB_TOKEN' : 'GITHUB_TOKEN='.$token);
        \putenv($repo === false ? 'GITHUB_REPOSITORY' : 'GITHUB_REPOSITORY='.$repo);
    }
});

\test('cleanup-orphaned command requires GITHUB_TOKEN', function (): void {
    /** @var TestCase $this */
    \putenv('GITHUB_TOKEN=');

    $command = $this->artisan('cleanup-orphaned', ['--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('GITHUB_TOKEN environment variable is required')
        ->assertExitCode(1);
});

\test('cleanup-orphaned command requires GITHUB_REPOSITORY', function (): void {
    /** @var TestCase $this */
    \putenv('GITHUB_TOKEN=test-token');
    \putenv('GITHUB_REPOSITORY=');

    $command = $this->artisan('cleanup-orphaned', ['--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('GITHUB_REPOSITORY environment variable is required (format: owner/repo)')
        ->assertExitCode(1);
    unset($command);

    \putenv('GITHUB_TOKEN=');
});

\test('cleanup-orphaned command supports dry-run flag', function (): void {
    /** @var TestCase $this */
    \putenv('GITHUB_TOKEN=');

    $command = $this->artisan('cleanup-orphaned', ['--dry-run' => true, '--force' => true]);
    \assert($command instanceof PendingCommand);
    $command->expectsOutput('GITHUB_TOKEN environment variable is required')
        ->assertExitCode(1);
});
