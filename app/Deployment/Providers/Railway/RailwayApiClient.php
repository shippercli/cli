<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Railway;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class RailwayApiClient
{
    private ?Client $httpClient = null;

    private const GRAPHQL_ENDPOINT = 'https://backboard.railway.app/graphql/v2';

    public function __construct(
        private readonly string $token,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function createServiceFromGit(string $projectId, string $name, string $gitRepo): array
    {
        $query = <<<'GRAPHQL'
        mutation CreateServiceFromGit($projectId: ID!, $name: String!, $gitRepo: String!) {
            createServiceFromGit(input: {
                projectId: $projectId,
                name: $name,
                gitRepo: $gitRepo
            }) {
                id
                name
            }
        }
GRAPHQL;

        return $this->graphql($query, [
            'projectId' => $projectId,
            'name' => $name,
            'gitRepo' => $gitRepo,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listProjects(): array
    {
        $query = <<<'GRAPHQL'
        query {
            projects {
                id
                name
            }
        }
GRAPHQL;

        return $this->graphql($query);
    }

    /**
     * @return array<string, mixed>
     */
    public function listServices(string $projectId): array
    {
        $query = <<<'GRAPHQL'
        query GetProject($projectId: ID!) {
            project(id: $projectId) {
                services {
                    id
                    name
                }
            }
        }
GRAPHQL;

        return $this->graphql($query, ['projectId' => $projectId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createDatabase(string $projectId, string $name, string $type = 'postgres'): array
    {
        $query = <<<'GRAPHQL'
        mutation CreateDatabase($projectId: ID!, $name: String!, $type: String!) {
            createDatabase(input: {
                projectId: $projectId,
                name: $name,
                type: $type
            }) {
                id
                name
            }
        }
GRAPHQL;

        return $this->graphql($query, [
            'projectId' => $projectId,
            'name' => $name,
            'type' => $type,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function addDomain(string $serviceId, string $domain): array
    {
        $query = <<<'GRAPHQL'
        mutation AddDomain($serviceId: ID!, $domain: String!) {
            addDomain(input: {
                serviceId: $serviceId,
                domain: $domain
            }) {
                id
                domain
            }
        }
GRAPHQL;

        return $this->graphql($query, [
            'serviceId' => $serviceId,
            'domain' => $domain,
        ]);
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    private function graphql(string $query, array $variables = []): array
    {
        $client = $this->getHttpClient();

        try {
            $response = $client->request('POST', self::GRAPHQL_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                    'variables' => $variables,
                ],
            ]);

            /** @var string $body */
            $body = (string) $response->getBody();
            /** @var array<string, mixed> $data */
            $data = \json_decode($body, true) ?? [];

            if (isset($data['errors']) && \is_array($data['errors'])) {
                /** @var array<string, mixed> $firstError */
                $firstError = $data['errors'][0] ?? [];
                /** @var string $message */
                $message = $firstError['message'] ?? 'GraphQL error';

                return [
                    'success' => false,
                    'message' => $message,
                    'data' => [],
                ];
            }

            return [
                'success' => true,
                'message' => 'OK',
                'data' => $data['data'] ?? [],
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    private function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'timeout' => 30,
                'verify' => true,
            ]);
        }

        return $this->httpClient;
    }
}
