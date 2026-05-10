<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Ploi;

use App\Config\SslConfig;
use App\Deployment\Contracts\SslManagerInterface;
use Ploi\Ploi;

final class PloiSslManager implements SslManagerInterface
{
    private ?Ploi $client = null;

    public function __construct(
        private readonly string $apiKey,
    ) {}

    /**
     * @return array<string>
     */
    public function plan(string $domain, SslConfig $ssl): array
    {
        if (! $ssl->enabled()) {
            return [];
        }

        $type = $ssl->type();

        return ["Create SSL certificate ({$type}) for domain: {$domain}"];
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(int $serverId, int $siteId, string $domain, SslConfig $ssl): array
    {
        if (! $ssl->enabled()) {
            return ['success' => true, 'message' => 'SSL not enabled'];
        }

        try {
            $client = $this->getClient();
            $server = $client->server($serverId);
            $site = $server->sites($siteId);

            $type = $ssl->type();
            $response = $site->certificates()->create($domain, $type, false);

            return [
                'success' => true,
                'message' => 'SSL certificate created successfully',
                'response' => $response->getJson(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to create SSL certificate: {$e->getMessage()}",
            ];
        }
    }

    private function getClient(): Ploi
    {
        if ($this->client === null) {
            $this->client = new Ploi($this->apiKey);
        }

        return $this->client;
    }
}
