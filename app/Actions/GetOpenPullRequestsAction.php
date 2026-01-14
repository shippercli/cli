<?php

declare(strict_types=1);

namespace App\Actions;

use App\Clients\GitHubHttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

final class GetOpenPullRequestsAction
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Get open pull requests from GitHub.
     *
     * @return array{success: bool, prs: array<int>, error: string}
     */
    public function handle(string $repo, string $token): array
    {
        try {
            $gitHubClient = new GitHubHttpClient($token);
            $client = $gitHubClient->getClient();

            $prNumbers = [];
            $page = 1;
            $perPage = 100;

            while (true) {
                $response = $client->get("/repos/{$repo}/pulls", [
                    'query' => [
                        'state' => 'open',
                        'per_page' => $perPage,
                        'page' => $page,
                    ],
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 401) {
                    return ['success' => false, 'prs' => [], 'error' => 'GitHub API authentication failed'];
                }

                if ($statusCode === 403) {
                    return ['success' => false, 'prs' => [], 'error' => 'GitHub API access forbidden or rate limited'];
                }

                if ($statusCode === 429) {
                    return ['success' => false, 'prs' => [], 'error' => 'GitHub API rate limit exceeded'];
                }

                if ($statusCode !== 200) {
                    return ['success' => false, 'prs' => [], 'error' => "GitHub API returned status {$statusCode}"];
                }

                $body = (string) $response->getBody();
                $prs = \json_decode($body, true);

                if (! \is_array($prs) || $prs === []) {
                    break;
                }

                foreach ($prs as $pr) {
                    if (\is_array($pr) && isset($pr['number']) && \is_int($pr['number'])) {
                        $prNumbers[] = $pr['number'];
                    }
                }

                if (\count($prs) < $perPage) {
                    break;
                }

                $page++;
            }

            return ['success' => true, 'prs' => $prNumbers, 'error' => ''];
        } catch (ClientException|RequestException $e) {
            $response = $e->hasResponse() ? $e->getResponse() : null;
            $statusCode = $response !== null ? $response->getStatusCode() : 0;
            $error = "GitHub API request failed (status {$statusCode}): {$e->getMessage()}";

            if ($this->logger !== null) {
                $this->logger->error('GitHub API request failed', [
                    'repo' => $repo,
                    'status_code' => $statusCode,
                    'exception' => $e->getMessage(),
                ]);
            }

            return ['success' => false, 'prs' => [], 'error' => $error];
        } catch (\Exception $e) {
            if ($this->logger !== null) {
                $this->logger->error('Unexpected error fetching GitHub PRs', [
                    'repo' => $repo,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return ['success' => false, 'prs' => [], 'error' => "Unexpected error: {$e->getMessage()}"];
        }
    }
}
