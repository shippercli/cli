<?php

declare(strict_types=1);

namespace App\Deployment\Providers\CloudflarePages;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class CloudflarePagesApiClient
{
    private ?Client $httpClient = null;

    private const PAGES_API_BASE = 'https://api.cloudflare.com/client/v4/accounts';

    public function __construct(
        private readonly string $accountId,
        private readonly string $apiToken,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function createProject(string $name, string $repoUrl, string $productionBranch = 'main'): array
    {
        $payload = [
            'name' => $name,
            'source' => [
                'type' => 'github',
                'config' => [
                    'repo_url' => $repoUrl,
                    'production_branch' => $productionBranch,
                ],
            ],
            'build_config' => [
                'build_command' => '',
                'destination_dir' => 'public',
            ],
            'deployment_configs' => [
                'production' => [
                    'env_vars' => new \stdClass,
                ],
            ],
        ];

        return $this->request('POST', '/pages/projects', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function listProjects(): array
    {
        return $this->request('GET', '/pages/projects');
    }

    /**
     * @return array<string, mixed>
     */
    public function getProject(string $name): array
    {
        return $this->request('GET', "/pages/projects/{$name}");
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteProject(string $name): array
    {
        return $this->request('DELETE', "/pages/projects/{$name}");
    }

    /**
     * @return array<string, mixed>
     */
    public function addDomain(string $projectName, string $domain): array
    {
        return $this->request('POST', "/pages/projects/{$projectName}/domains", [
            'hostname' => $domain,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $client = $this->getHttpClient();
        $url = self::PAGES_API_BASE.'/'.$this->accountId.$path;

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiToken,
                'Content-Type' => 'application/json',
            ],
        ];

        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $client->request($method, $url, $options);

            /** @var string $responseBody */
            $responseBody = (string) $response->getBody();
            /** @var array<string, mixed> $data */
            $data = \json_decode($responseBody, true) ?? [];

            return [
                'success' => ($data['success'] ?? false),
                'message' => $this->extractMessage($data),
                'data' => $data['result'] ?? [],
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractMessage(array $data): string
    {
        $errors = $data['errors'] ?? null;
        if (\is_array($errors) && \count($errors) > 0) {
            /** @var array<string, mixed> $firstError */
            $firstError = $errors[0];
            if (isset($firstError['message']) && \is_string($firstError['message'])) {
                return $firstError['message'];
            }
        }

        $messages = $data['messages'] ?? null;
        if (\is_array($messages) && \count($messages) > 0) {
            /** @var array<string, mixed> $firstMessage */
            $firstMessage = $messages[0];
            if (isset($firstMessage['message']) && \is_string($firstMessage['message'])) {
                return $firstMessage['message'];
            }
        }

        return 'OK';
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
