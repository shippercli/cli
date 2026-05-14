<?php

declare(strict_types=1);

namespace App\Deployment;

use App\Deployment\Providers\CloudflarePages\CloudflarePagesProvider;
use App\Deployment\Providers\Cpanel\CpanelProvider;
use App\Deployment\Providers\Forge\ForgeProvider;
use App\Deployment\Providers\Hostinger\HostingerProvider;
use App\Deployment\Providers\Railway\RailwayProvider;

final class ProviderRegistry
{
    /** @var array<string, string> */
    private static array $providers = [
        'ploi' => PloiProvider::class,
        'forge' => ForgeProvider::class,
        'cpanel' => CpanelProvider::class,
        'railway' => RailwayProvider::class,
        'cloudflare-pages' => CloudflarePagesProvider::class,
        'hostinger' => HostingerProvider::class,
    ];

    public static function register(string $name, string $className): void
    {
        self::$providers[$name] = $className;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::$providers;
    }

    public static function get(string $name): ?string
    {
        return self::$providers[$name] ?? null;
    }
}
