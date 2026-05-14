<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Hostinger;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class HostingerApiClient
{
    private ?Client $httpClient = null;

    private const API_BASE = 'https://api.hostinger.com/api/v1';

    private ?string $authToken = null;

    public function __construct(
        private readonly string $apiToken,
        private readonly string $email,
        private readonly string $password,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function authenticate(): array
    {
        /** @var array<string, mixed> $response */
        $response = $this->request('POST', '/auth', [
            'api_token' => $this->apiToken,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        /** @var bool $success */
        $success = $response['success'] ?? false;
        /** @var array<string, mixed> $responseData */
        $responseData = $response['data'] ?? [];
        if ($success && ($responseData['token'] ?? false)) {
            $tokenValue = $responseData['token'];
            if (\is_string($tokenValue)) {
                $this->authToken = $tokenValue;
            } elseif (\is_numeric($tokenValue)) {
                $this->authToken = (string) $tokenValue;
            }
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrCreateWebsite(string $domain): array
    {
        /** @var array<string, mixed> $websiteList */
        $websiteList = $this->request('GET', '/websites');

        /** @var bool $success */
        $success = $websiteList['success'] ?? false;
        if (! $success) {
            return $websiteList;
        }

        /** @var array<array<string, mixed>> $websites */
        $websites = $websiteList['data'] ?? [];
        foreach ($websites as $website) {
            /** @var string $websiteDomain */
            $websiteDomain = $website['domain'] ?? '';
            if ($websiteDomain === $domain) {
                return [
                    'success' => true,
                    'message' => 'Website found',
                    'data' => $website,
                ];
            }
        }

        return $this->request('POST', '/websites', [
            'domain' => $domain,
            'type' => 'php',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function configureGitDeployment(string $websiteId, string $repoUrl, string $branch): array
    {
        return $this->request('PUT', "/websites/{$websiteId}/git", [
            'repository' => $repoUrl,
            'branch' => $branch,
            'auto_deploy' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listWebsites(): array
    {
        return $this->request('GET', '/websites');
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebsite(string $websiteId): array
    {
        return $this->request('GET', "/websites/{$websiteId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteWebsite(string $websiteId): array
    {
        return $this->request('DELETE', "/websites/{$websiteId}");
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $client = $this->getHttpClient();
        $url = self::API_BASE.$path;

        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Shipper-CLI/1.0',
            ],
        ];

        if ($this->authToken !== null) {
            $options['headers']['Authorization'] = 'Bearer '.$this->authToken;
        }

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
                'success' => ($data['status'] ?? false) === true,
                'message' => $data['message'] ?? 'OK',
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
