<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Coolify;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;

final class CoolifyApiClient
{
    private ?Client $httpClient = null;

    public function __construct(
        private readonly string $serverUrl,
        private readonly string $apiToken,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listProjects(): array
    {
        return $this->request('GET', '/projects');
    }

    /**
     * @return array<string, mixed>
     */
    public function createProject(string $name): array
    {
        return $this->request('POST', '/projects', [
            'name' => $name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getProject(string $uuid): array
    {
        return $this->request('GET', "/projects/{$uuid}");
    }

    /**
     * @return array<string, mixed>
     */
    public function createApplication(string $projectUuid, string $name, string $gitRepo, string $branch): array
    {
        return $this->request('POST', "/projects/{$projectUuid}/applications", [
            'name' => $name,
            'git_repository' => $gitRepo,
            'git_branch' => $branch,
            'build_pack' => 'nixpacks',
            'ports' => [],
            'environment' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listApplications(string $projectUuid): array
    {
        return $this->request('GET', "/projects/{$projectUuid}/applications");
    }

    /**
     * @return array<string, mixed>
     */
    public function getApplication(string $uuid): array
    {
        return $this->request('GET', "/applications/{$uuid}");
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteApplication(string $uuid): array
    {
        return $this->request('DELETE', "/applications/{$uuid}");
    }

    /**
     * @return array<string, mixed>
     */
    public function deploy(string $uuid): array
    {
        return $this->request('GET', "/applications/{$uuid}/deploy");
    }

    /**
     * @return array<string, mixed>
     */
    public function addDomain(string $uuid, string $domain): array
    {
        return $this->request('POST', "/applications/{$uuid}/domains", [
            'domain' => $domain,
            'scheme' => 'https',
            'wildcard' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function start(string $uuid): array
    {
        return $this->request('GET', "/applications/{$uuid}/start");
    }

    /**
     * @return array<string, mixed>
     */
    public function stop(string $uuid): array
    {
        return $this->request('GET', "/applications/{$uuid}/stop");
    }

    /**
     * @return array<string, mixed>
     */
    public function restart(string $uuid): array
    {
        return $this->request('GET', "/applications/{$uuid}/restart");
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogs(string $uuid): array
    {
        return $this->request('GET', "/applications/{$uuid}/logs");
    }

    /**
     * @return array<string, mixed>
     */
    public function listDatabases(string $projectUuid): array
    {
        return $this->request('GET', "/projects/{$projectUuid}/databases");
    }

    /**
     * @return array<string, mixed>
     */
    public function createDatabase(string $projectUuid, string $name, string $type): array
    {
        $payload = match ($type) {
            'postgres', 'postgresql' => [
                'name' => $name,
                'database' => 'postgres',
                'type' => 'postgresql',
            ],
            'mysql' => [
                'name' => $name,
                'database' => 'mysql',
                'type' => 'mysql',
            ],
            'mariadb' => [
                'name' => $name,
                'database' => 'mariadb',
                'type' => 'mariadb',
            ],
            'mongodb' => [
                'name' => $name,
                'database' => 'mongodb',
                'type' => 'mongodb',
            ],
            'redis' => [
                'name' => $name,
                'database' => 'redis',
                'type' => 'redis',
            ],
            default => [
                'name' => $name,
                'database' => 'postgres',
                'type' => 'postgresql',
            ],
        };

        return $this->request('POST', "/projects/{$projectUuid}/databases", $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getServer(string $uuid): array
    {
        return $this->request('GET', "/servers/{$uuid}");
    }

    /**
     * @return array<string, mixed>
     */
    public function listServers(): array
    {
        return $this->request('GET', '/servers');
    }

    /**
     * @return array<string, mixed>
     */
    public function version(): array
    {
        return $this->request('GET', '/version');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $client = $this->getHttpClient();
        $url = \rtrim($this->serverUrl, '/').'/api/v1'.$path;

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
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
                'success' => true,
                'message' => 'OK',
                'data' => $data,
            ];
        } catch (GuzzleException $e) {
            $message = $e->getMessage();

            if ($e instanceof BadResponseException) {
                $response = $e->getResponse();
                /** @var string $errorBody */
                $errorBody = (string) $response->getBody();
                /** @var array<string, mixed> $errorData */
                $errorData = \json_decode($errorBody, true) ?? [];
                if (isset($errorData['message']) && \is_string($errorData['message'])) {
                    $message = $errorData['message'];
                } elseif (isset($errorData['error']) && \is_string($errorData['error'])) {
                    $message = $errorData['error'];
                }
            }

            return [
                'success' => false,
                'message' => $message,
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
