<?php

declare(strict_types=1);

namespace App\Deployment\Providers\EasyPanel;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;

final class EasyPanelApiClient
{
    private ?Client $httpClient = null;

    private const API_PATH = '/api/v1';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $authToken,
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
    public function inspectProject(string $name): array
    {
        return $this->request('GET', "/projects/{$name}");
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteProject(string $name): array
    {
        return $this->request('DELETE', "/projects/{$name}");
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createAppService(string $projectName, array $data): array
    {
        return $this->request('POST', "/projects/{$projectName}/services/app", $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function listServices(string $projectName): array
    {
        return $this->request('GET', "/projects/{$projectName}/services");
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectService(string $projectName, string $serviceName): array
    {
        return $this->request('GET', "/projects/{$projectName}/services/{$serviceName}");
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteService(string $projectName, string $serviceName): array
    {
        return $this->request('DELETE', "/projects/{$projectName}/services/{$serviceName}");
    }

    /**
     * @return array<string, mixed>
     */
    public function startService(string $projectName, string $serviceName): array
    {
        return $this->request('POST', "/projects/{$projectName}/services/{$serviceName}/start");
    }

    /**
     * @return array<string, mixed>
     */
    public function stopService(string $projectName, string $serviceName): array
    {
        return $this->request('POST', "/projects/{$projectName}/services/{$serviceName}/stop");
    }

    /**
     * @return array<string, mixed>
     */
    public function restartService(string $projectName, string $serviceName): array
    {
        return $this->request('POST', "/projects/{$projectName}/services/{$serviceName}/restart");
    }

    /**
     * @return array<string, mixed>
     */
    public function redeployService(string $projectName, string $serviceName): array
    {
        return $this->request('POST', "/projects/{$projectName}/services/{$serviceName}/redeploy");
    }

    /**
     * @return array<string, mixed>
     */
    public function getServiceLogs(string $projectName, string $serviceName): array
    {
        return $this->request('GET', "/projects/{$projectName}/services/{$serviceName}/logs");
    }

    /**
     * @return array<string, mixed>
     */
    public function setServiceDomain(string $projectName, string $serviceName, string $domain): array
    {
        return $this->request('POST', "/projects/{$projectName}/services/{$serviceName}/domains", [
            'domain' => $domain,
            'scheme' => 'https',
            'redirect' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listActions(string $projectName, string $serviceName): array
    {
        return $this->request('GET', "/projects/{$projectName}/services/{$serviceName}/actions");
    }

    /**
     * @return array<string, mixed>
     */
    public function getAction(string $projectName, string $serviceName, string $actionId): array
    {
        return $this->request('GET', "/projects/{$projectName}/services/{$serviceName}/actions/{$actionId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function killAction(string $projectName, string $serviceName, string $actionId): array
    {
        return $this->request('DELETE', "/projects/{$projectName}/services/{$serviceName}/actions/{$actionId}");
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createDatabase(string $projectName, string $type, array $data): array
    {
        $endpoint = match ($type) {
            'mysql' => "/projects/{$projectName}/services/mysql",
            'postgres', 'postgresql' => "/projects/{$projectName}/services/postgres",
            'mariadb' => "/projects/{$projectName}/services/mariadb",
            'mongodb', 'mongo' => "/projects/{$projectName}/services/mongo",
            'redis' => "/projects/{$projectName}/services/redis",
            default => "/projects/{$projectName}/services/postgres",
        };

        return $this->request('POST', $endpoint, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSystemStats(): array
    {
        return $this->request('GET', '/monitoring/stats');
    }

    /**
     * @return array<string, mixed>
     */
    public function getUser(): array
    {
        return $this->request('GET', '/auth/user');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $client = $this->getHttpClient();
        $url = \rtrim($this->baseUrl, '/').self::API_PATH.$path;

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->authToken,
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
