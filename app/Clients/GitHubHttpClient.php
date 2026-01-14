<?php

declare(strict_types=1);

namespace App\Clients;

use GuzzleHttp\Client;

final class GitHubHttpClient
{
    private readonly Client $client;

    public function __construct(string $token)
    {
        $this->client = new Client([
            'base_uri' => 'https://api.github.com',
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'shippercli.com',
            ],
            'http_errors' => false,
        ]);
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
