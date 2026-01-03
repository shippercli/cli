<?php

declare(strict_types=1);

namespace App\Actions;

use GuzzleHttp\Client;

final class GetOpenPullRequestsAction
{
    /**
     * Get open pull requests from GitHub.
     *
     * @return array<int>
     */
    public function handle(string $repo, string $token): array
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

                if ($statusCode === 401 || $statusCode === 403 || $statusCode === 429) {
                    return [];
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

            return $prNumbers;
        } catch (\Exception $e) {
            return [];
        }
    }
}
