<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Portainer;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;

final class PortainerApiClient
{
    private ?Client $httpClient = null;

    private const API_PATH = '/api';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listContainers(int $endpointId): array
    {
        return $this->request('GET', "/endpoints/{$endpointId}/docker/containers/json");
    }

    /**
     * @return array<string, mixed>
     */
    public function getContainer(int $endpointId, string $name): array
    {
        $containers = $this->listContainers($endpointId);

        if (! $containers['success']) {
            return $containers;
        }

        /** @var array<array<string, mixed>> $containerList */
        $containerList = $containers['data'] ?? [];
        foreach ($containerList as $container) {
            /** @var array<string> $names */
            $names = $container['Names'] ?? [];
            if (\in_array("/{$name}", $names, true)) {
                return [
                    'success' => true,
                    'message' => 'Container found',
                    'data' => $container,
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Container not found',
            'data' => [],
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function createContainer(int $endpointId, array $config): array
    {
        /** @var string $containerName */
        $containerName = $config['name'] ?? '';

        return $this->request('POST', "/endpoints/{$endpointId}/docker/containers/create", $config, [
            'name' => $containerName,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function startContainer(int $endpointId, string $name): array
    {
        return $this->request('POST', "/endpoints/{$endpointId}/docker/containers/{$name}/start");
    }

    /**
     * @return array<string, mixed>
     */
    public function stopContainer(int $endpointId, string $name): array
    {
        return $this->request('POST', "/endpoints/{$endpointId}/docker/containers/{$name}/stop");
    }

    /**
     * @return array<string, mixed>
     */
    public function restartContainer(int $endpointId, string $name): array
    {
        return $this->request('POST', "/endpoints/{$endpointId}/docker/containers/{$name}/restart");
    }

    /**
     * @return array<string, mixed>
     */
    public function removeContainer(int $endpointId, string $name, bool $force = true): array
    {
        $queryParams = [];
        if ($force) {
            $queryParams = ['force' => 'true'];
        }

        return $this->request('DELETE', "/endpoints/{$endpointId}/docker/containers/{$name}", null, $queryParams);
    }

    /**
     * @return array<string, mixed>
     */
    public function getContainerLogs(int $endpointId, string $name): array
    {
        $queryParams = ['stdout' => 'true', 'stderr' => 'true'];

        return $this->request('GET', "/endpoints/{$endpointId}/docker/containers/{$name}/logs", null, $queryParams);
    }

    /**
     * @return array<string, mixed>
     */
    public function listImages(int $endpointId): array
    {
        return $this->request('GET', "/endpoints/{$endpointId}/docker/images/json");
    }

    /**
     * @return array<string, mixed>
     */
    public function buildImage(int $endpointId, string $dockerfilePath, string $tag): array
    {
        $dockerfileContent = $this->generateDockerfile($dockerfilePath);

        /** @var array<string, mixed> $body */
        $body = [
            't' => $tag,
            'dockerfile' => 'Dockerfile',
        ];

        /** @var array<string, string> $queryParams */
        $queryParams = [];

        return $this->buildImageRequest(
            'POST',
            "/endpoints/{$endpointId}/docker/build",
            $body,
            $queryParams,
            ['dockerfile' => $dockerfileContent],
        );
    }

    /**
     * @param array<string, string|int> $queryParams
     * @param array<string, mixed>|null $body
     * @param array<string, string> $files
     *
     * @return array<string, mixed>
     */
    private function buildImageRequest(string $method, string $path, ?array $body, array $queryParams, array $files): array
    {
        $client = $this->getHttpClient();
        $url = \rtrim($this->baseUrl, '/').self::API_PATH.$path;

        if ($queryParams !== []) {
            $url .= '?'.\http_build_query($queryParams);
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Accept' => 'application/json',
            ],
        ];

        $multipart = [];
        foreach ($files as $name => $content) {
            $multipart[] = [
                'name' => $name,
                'contents' => $content,
            ];
        }
        if ($body !== null) {
            foreach ($body as $key => $value) {
                /** @var string $content */
                if (\is_array($value)) {
                    $content = \json_encode($value);
                } elseif (\is_scalar($value)) {
                    $content = (string) $value;
                } else {
                    $content = \json_encode($value);
                }
                $multipart[] = [
                    'name' => (string) $key,
                    'contents' => $content,
                ];
            }
        }
        $options['multipart'] = $multipart;

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
            return $this->handleError($e);
        }
    }

    /**
     * @param array<string, string|int> $queryParams
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, array $queryParams = []): array
    {
        $client = $this->getHttpClient();
        $url = \rtrim($this->baseUrl, '/').self::API_PATH.$path;

        if ($queryParams !== []) {
            $url .= '?'.\http_build_query($queryParams);
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
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
            return $this->handleError($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function handleError(GuzzleException $e): array
    {
        $message = $e->getMessage();

        if ($e instanceof BadResponseException && $e->hasResponse()) {
            /** @var string $errorBody */
            $errorBody = (string) $e->getResponse()->getBody();
            /** @var array<string, mixed> $errorData */
            $errorData = \json_decode($errorBody, true) ?? [];
            if (isset($errorData['message']) && \is_string($errorData['message'])) {
                $message = $errorData['message'];
            } elseif (isset($errorData['err']) && \is_string($errorData['err'])) {
                $message = $errorData['err'];
            }
        }

        return [
            'success' => false,
            'message' => $message,
            'data' => [],
        ];
    }

    private function generateDockerfile(string $contextPath): string
    {
        $dockerfile = "FROM php:8.3-cli\n";
        $dockerfile .= "WORKDIR /var/www/html\n";
        $dockerfile .= "COPY . .\n";
        $dockerfile .= "RUN composer install --no-dev --optimize-autoloader\n";
        $dockerfile .= "EXPOSE 3000\n";
        $dockerfile .= "CMD [\"php\", \"artisan\", \"serve\", \"--host=0.0.0.0\", \"--port=3000\"]\n";

        return $dockerfile;
    }

    private function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'timeout' => 60,
                'verify' => true,
            ]);
        }

        return $this->httpClient;
    }
}
