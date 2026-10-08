<?php

declare(strict_types=1);

namespace App\Flows;

use App\Config\ProfileConfig;
use App\Deployment\ContractDeploymentProviderAdapter;
use App\Deployment\DeploymentProviderInterface;
use ShipperCli\Contracts\ServerLifecycleProviderInterface;

final class ServerLifecycleRuntime
{
    public static function provider(DeploymentProviderInterface $provider): ?ServerLifecycleProviderInterface
    {
        $contract = $provider instanceof ContractDeploymentProviderAdapter
            ? $provider->contractProvider()
            : $provider;

        return $contract instanceof ServerLifecycleProviderInterface ? $contract : null;
    }

    /** @return array{profile: ProfileConfig, server: array<string, mixed>|null, error: string} */
    public static function resolve(DeploymentProviderInterface $provider, ProfileConfig $profile): array
    {
        $config = $profile->server();
        if ($config === null) {
            return ['profile' => $profile, 'server' => null, 'error' => ''];
        }
        if ($config->isExisting()) {
            $id = $config->id();

            return $id === null || trim($id) === ''
                ? ['profile' => $profile, 'server' => null, 'error' => 'Existing server lifecycle configuration requires a non-empty id.']
                : ['profile' => $profile->withServerId($id), 'server' => null, 'error' => ''];
        }
        if (! $config->isCreate()) {
            return ['profile' => $profile, 'server' => null, 'error' => ''];
        }
        $lifecycle = self::provider($provider);
        if ($lifecycle === null) {
            return ['profile' => $profile, 'server' => null, 'error' => 'The selected provider does not support managed server lifecycle.'];
        }
        $server = $lifecycle->resolveServer($config->spec());
        if ($server === null) {
            return ['profile' => $profile, 'server' => null, 'error' => ''];
        }
        $id = self::serverId($server);
        if ($id === '') {
            return ['profile' => $profile, 'server' => null, 'error' => 'The provider resolved a managed server without an ID.'];
        }

        return ['profile' => $profile->withServerId($id), 'server' => $server, 'error' => ''];
    }

    /** @return array{profile: ProfileConfig, server: array<string, mixed>|null, error: string} */
    public static function ensure(DeploymentProviderInterface $provider, ProfileConfig $profile): array
    {
        $config = $profile->server();
        if ($config === null || $config->isExisting()) {
            return self::resolve($provider, $profile);
        }
        if (! $config->isCreate()) {
            return ['profile' => $profile, 'server' => null, 'error' => ''];
        }
        $lifecycle = self::provider($provider);
        if ($lifecycle === null) {
            return ['profile' => $profile, 'server' => null, 'error' => 'The selected provider does not support managed server lifecycle.'];
        }
        $server = $lifecycle->resolveServer($config->spec());
        if ($server === null) {
            $server = $lifecycle->createServer($config->spec());
        }
        $id = self::serverId($server);
        if ($id === '') {
            return ['profile' => $profile, 'server' => null, 'error' => 'The provider created or resolved a managed server without an ID.'];
        }

        return ['profile' => $profile->withServerId($id), 'server' => $server, 'error' => ''];
    }

    public static function serverId(array $server): string
    {
        foreach (['uuid', 'server_uuid', 'id'] as $field) {
            if (is_string($server[$field] ?? null) && trim($server[$field]) !== '') {
                return $server[$field];
            }
        }

        return '';
    }
}
