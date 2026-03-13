<?php

declare(strict_types=1);

use App\Clients\GitHubHttpClient;

\test('GitHubHttpClient creates client with correct configuration', function (): void {
    $token = 'test-token-123';
    $gitHubClient = new GitHubHttpClient($token);
    $client = $gitHubClient->getClient();

    \expect($client)->toBeInstanceOf(\GuzzleHttp\Client::class);

    // Verify client has the correct base URI
    $config = $client->getConfig();
    \expect($config)->toHaveKey('base_uri');
    \expect($config)->toHaveKey('headers');
    \expect($config)->toHaveKey('http_errors');

    // Now assert types for safe array access
    \assert(\is_array($config));
    $baseUri = $config['base_uri'];
    \assert(\is_string($baseUri) || (\is_object($baseUri) && \method_exists($baseUri, '__toString')));
    \expect((string) $baseUri)->toBe('https://api.github.com');

    // Verify headers are set correctly
    \assert(\is_array($config['headers']));
    \expect($config['headers'])->toHaveKey('Authorization');
    \expect($config['headers']['Authorization'])->toBe("Bearer {$token}");
    \expect($config['headers']['Accept'])->toBe('application/vnd.github+json');
    \expect($config['headers']['User-Agent'])->toBe('shippercli.com');

    // Verify http_errors is set to false
    \expect($config['http_errors'])->toBe(false);
});

\test('GitHubHttpClient uses token parameter for authorization', function (): void {
    $token = 'my-custom-token';
    $gitHubClient = new GitHubHttpClient($token);
    $client = $gitHubClient->getClient();

    $config = $client->getConfig();
    \assert(\is_array($config) && isset($config['headers']) && \is_array($config['headers']));
    \expect($config['headers']['Authorization'])->toBe("Bearer {$token}");
});
