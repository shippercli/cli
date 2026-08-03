<?php

declare(strict_types=1);

namespace App\Deployment;

use App\Deployment\Providers\CloudflarePages\CloudflarePagesProvider;
use App\Deployment\Providers\Coolify\CoolifyProvider;
use App\Deployment\Providers\EasyPanel\EasyPanelProvider;
use App\Deployment\Providers\Forge\ForgeProvider;
use App\Deployment\Providers\Hostinger\HostingerProvider;
use App\Deployment\Providers\Portainer\PortainerProvider;
use App\Deployment\Providers\Railway\RailwayProvider;
use Composer\InstalledVersions;
use ShipperCli\Contracts\DeploymentProviderInterface as ContractProvider;
use ShipperCli\Contracts\ShipperPluginInterface;

final class ProviderRegistry
{
    /** @var array<string, string> */
    private static array $providers = [
        'ploi' => PloiProvider::class,
        'forge' => ForgeProvider::class,
        'railway' => RailwayProvider::class,
        'cloudflare-pages' => CloudflarePagesProvider::class,
        'hostinger' => HostingerProvider::class,
        'coolify' => CoolifyProvider::class,
        'easypanel' => EasyPanelProvider::class,
        'portainer' => PortainerProvider::class,
    ];

    private static bool $pluginsDiscovered = false;

    public static function register(string $name, string $className): void
    {
        self::$providers[$name] = $className;
    }

    public static function registerPlugin(ShipperPluginInterface $plugin): void
    {
        foreach ($plugin->providers() as $name => $className) {
            $providerClass = self::validatedProviderClass($name, $className);

            self::register($name, $providerClass);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        self::discoverPlugins();

        return self::$providers;
    }

    public static function get(string $name): ?string
    {
        self::discoverPlugins();

        return self::$providers[$name] ?? null;
    }

    private static function discoverPlugins(): void
    {
        if (self::$pluginsDiscovered) {
            return;
        }

        self::$pluginsDiscovered = true;

        foreach (InstalledVersions::getInstalledPackagesByType('shipper-plugin') as $packageName) {
            $installPath = InstalledVersions::getInstallPath($packageName);

            if ($installPath === null) {
                continue;
            }

            $manifestPath = $installPath.'/composer.json';
            $manifestContents = @\file_get_contents($manifestPath);

            if ($manifestContents === false) {
                throw new \RuntimeException("Unable to read Shipper plugin manifest: {$manifestPath}");
            }

            try {
                $manifest = \json_decode($manifestContents, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new \RuntimeException(
                    "Invalid Shipper plugin manifest: {$manifestPath}",
                    previous: $exception,
                );
            }

            if (! \is_array($manifest)) {
                throw new \UnexpectedValueException(
                    "Shipper plugin manifest must contain a JSON object: {$manifestPath}",
                );
            }

            $extra = $manifest['extra'] ?? null;
            $pluginClass = \is_array($extra) ? ($extra['shipper-plugin'] ?? null) : null;

            if (! \is_string($pluginClass) || ! \is_a($pluginClass, ShipperPluginInterface::class, true)) {
                throw new \UnexpectedValueException(
                    "Package {$packageName} must declare extra.shipper-plugin as a class implementing "
                    .ShipperPluginInterface::class.'.',
                );
            }

            self::registerPlugin(new $pluginClass);
        }
    }

    private static function validatedProviderClass(string $name, mixed $className): string
    {
        if ($name === '' || ! \is_string($className) || ! \is_a($className, ContractProvider::class, true)) {
            throw new \UnexpectedValueException(
                \sprintf(
                    'Shipper plugin provider "%s" must map to a class implementing %s.',
                    $name,
                    ContractProvider::class,
                ),
            );
        }

        return $className;
    }
}
