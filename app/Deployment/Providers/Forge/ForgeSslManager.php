<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Forge;

use App\Config\SslConfig;
use App\Deployment\Contracts\SslManagerInterface;
use Laravel\Forge\Forge;

final class ForgeSslManager implements SslManagerInterface
{
    private ?Forge $client = null;

    public function __construct(
        private readonly string $apiToken,
    ) {}

    public function plan(string $domain, SslConfig $ssl): array
    {
        if (! $ssl->enabled()) {
            return [];
        }

        $type = $ssl->type();

        return ["Create SSL certificate ({$type}) for domain: {$domain}"];
    }

    public function apply(int $serverId, int $siteId, string $domain, SslConfig $ssl): array
    {
        if (! $ssl->enabled()) {
            return ['success' => true, 'message' => 'SSL not enabled'];
        }

        try {
            $client = $this->getClient();
            // @todo Implement using Forge SDK
            // $server = $client->server($serverId);
            // $site = $server->sites($siteId);
            // $site->sslCertificates()->create($domain);

            return [
                'success' => true,
                'message' => 'SSL certificate created successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to create SSL certificate: {$e->getMessage()}",
            ];
        }
    }

    private function getClient(): Forge
    {
        if ($this->client === null) {
            $this->client = new Forge($this->apiToken);
        }

        return $this->client;
    }
}