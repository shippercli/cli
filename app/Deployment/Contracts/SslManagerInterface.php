<?php

declare(strict_types=1);

namespace App\Deployment\Contracts;

use App\Config\SslConfig;

interface SslManagerInterface
{
    /**
     * Plan SSL certificate configuration.
     *
     * @return array<string>
     */
    public function plan(string $domain, SslConfig $ssl): array;

    /**
     * Apply SSL certificate configuration.
     *
     * @return array<string, mixed>
     */
    public function apply(int $serverId, int $siteId, string $domain, SslConfig $ssl): array;
}
