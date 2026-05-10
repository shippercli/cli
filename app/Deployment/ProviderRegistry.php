<?php

declare(strict_types=1);

namespace App\Deployment;

final class ProviderRegistry
{
    /** @var array<string, string> */
    private static array $providers = [
        'ploi' => PloiProvider::class,
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
