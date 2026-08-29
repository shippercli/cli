<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    public function __construct(
        private readonly string $configPath = 'shipper.yml',
    ) {}

    public function load(): ShipperConfig
    {
        if (! \file_exists($this->configPath)) {
            throw new \RuntimeException("Config file not found: {$this->configPath}");
        }

        $content = \file_get_contents($this->configPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read config file: {$this->configPath}");
        }

        /** @var array<string, mixed> $data */
        $data = Yaml::parse($content);

        return $this->parseConfig($data);
    }

    /**
     * Interpolate environment variables in a string value.
     * Supports ${VAR_NAME} syntax.
     */
    private function interpolateEnvVars(string $value): string
    {
        return \preg_replace_callback(
            '/\$\{([A-Z_][A-Z0-9_]*)\}/',
            function (array $matches): string {
                $envVar = $matches[1];
                $envValue = \getenv($envVar);

                return $envValue !== false ? $envValue : $matches[0];
            },
            $value,
        ) ?? $value;
    }

    /**
     * @param array<mixed, mixed> $arr
     */
    private function isStringArray(array $arr): bool
    {
        foreach ($arr as $key => $value) {
            if (! \is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Recursively interpolate environment variables in array values.
     *
     * @param array<mixed, mixed> $data
     *
     * @return array<mixed, mixed>
     */
    private function interpolateArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($value)) {
                $data[$key] = $this->interpolateEnvVars($value);
            } elseif (\is_array($value)) {
                $data[$key] = $this->interpolateArray($value);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseConfig(array $data): ShipperConfig
    {
        $projects = [];
        $providers = $data['providers'] ?? [];

        if (! \is_array($providers)) {
            $providers = [];
        } else {
            $providers = $this->interpolateArray($providers);
        }

        if (isset($data['projects']) && \is_array($data['projects'])) {
            foreach ($data['projects'] as $projectName => $projectData) {
                if (\is_string($projectName) && \is_array($projectData)) {
                    /** @var array<string, mixed> $projectData */
                    $projectData = $this->interpolateArray($projectData);
                    $projects[$projectName] = $this->parseProject($projectName, $projectData);
                }
            }
        }

        return new ShipperConfig($projects, $providers);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseProject(string $name, array $data): ProjectConfig
    {
        $profiles = [];

        if (isset($data['profiles']) && \is_array($data['profiles'])) {
            foreach ($data['profiles'] as $profileName => $profileData) {
                if (\is_string($profileName) && \is_array($profileData)) {
                    $branch = $profileData['branch'] ?? '';
                    $environmentData = $profileData['environment'] ?? null;
                    $infrastructureData = $profileData['infrastructure'] ?? null;
                    $serverData = \is_array($infrastructureData) ? ($infrastructureData['server'] ?? null) : null;
                    $environment = $this->parseEnvironment(\is_array($environmentData) ? $environmentData : null);
                    $server = $this->parseServerLifecycle(\is_array($serverData) ? $serverData : null);
                    $profiles[$profileName] = new ProfileConfig(
                        $profileName,
                        \is_string($branch) ? $branch : '',
                        $this->interpolateArray($profileData),
                        $environment,
                        $server,
                    );
                }
            }
        }

        $databases = [];

        if (isset($data['databases']) && \is_array($data['databases'])) {
            foreach ($data['databases'] as $databaseName => $databaseData) {
                if (\is_string($databaseName) && \is_array($databaseData)) {
                    $dbName = $databaseData['name'] ?? $databaseName;
                    $dbUser = $databaseData['user'] ?? $databaseName;
                    $dbType = $databaseData['type'] ?? 'mysql';
                    $databases[$databaseName] = new DatabaseConfig(
                        \is_string($dbName) ? $dbName : $databaseName,
                        \is_string($dbUser) ? $dbUser : $databaseName,
                        \is_string($dbType) ? $dbType : 'mysql',
                    );
                }
            }
        }

        $ssl = isset($data['ssl']) && \is_array($data['ssl']) ? $this->parseSsl($data['ssl']) : null;
        $environment = isset($data['environment']) && \is_array($data['environment']) ? $this->parseEnvironment($data['environment']) : null;

        $queues = [];

        if (isset($data['queues']) && \is_array($data['queues'])) {
            foreach ($data['queues'] as $queueName => $queueData) {
                if (\is_string($queueName) && \is_array($queueData) && $this->isStringArray($queueData)) {
                    $queues[$queueName] = $this->parseQueue($queueData);
                }
            }
        }

        $cron = [];

        if (isset($data['cron']) && \is_array($data['cron'])) {
            foreach ($data['cron'] as $cronName => $cronData) {
                if (\is_string($cronName) && \is_array($cronData) && $this->isStringArray($cronData)) {
                    $cron[$cronName] = $this->parseCron($cronData);
                }
            }
        }

        $daemons = [];

        if (isset($data['daemons']) && \is_array($data['daemons'])) {
            foreach ($data['daemons'] as $daemonName => $daemonData) {
                if (\is_string($daemonName) && \is_array($daemonData) && $this->isStringArray($daemonData)) {
                    $daemons[$daemonName] = $this->parseDaemon($daemonData);
                }
            }
        }

        $networkRules = [];

        if (isset($data['network_rules']) && \is_array($data['network_rules'])) {
            foreach ($data['network_rules'] as $ruleName => $ruleData) {
                if (\is_string($ruleName) && \is_array($ruleData) && $this->isStringArray($ruleData)) {
                    $networkRules[$ruleName] = $this->parseNetworkRule($ruleData);
                }
            }
        }

        $redirects = [];

        if (isset($data['redirects']) && \is_array($data['redirects'])) {
            foreach ($data['redirects'] as $redirectName => $redirectData) {
                if (\is_string($redirectName) && \is_array($redirectData) && $this->isStringArray($redirectData)) {
                    $redirects[$redirectName] = $this->parseRedirect($redirectData);
                }
            }
        }

        $provider = $data['provider'] ?? '';
        $path = $data['path'] ?? '';
        $repository = $data['repository'] ?? [];
        $webDirectory = $data['web_directory'] ?? '/public';
        $projectRoot = $data['project_root'] ?? '/';
        $deployScript = $data['deploy_script'] ?? '';
        $phpVersionRaw = $data['php_version'] ?? null;
        $nginxConfigRaw = $data['nginx_config'] ?? null;
        $phpVersion = \is_string($phpVersionRaw) ? $phpVersionRaw : '';
        $nginxConfig = \is_string($nginxConfigRaw) ? $nginxConfigRaw : '';

        return new ProjectConfig(
            $name,
            \is_string($provider) ? $provider : '',
            \is_string($path) ? $path : '',
            $profiles,
            \is_array($repository) ? $repository : [],
            \is_string($webDirectory) ? $webDirectory : '/public',
            \is_string($projectRoot) ? $projectRoot : '/',
            $databases,
            $ssl,
            $environment,
            \is_string($deployScript) ? $deployScript : '',
            $queues,
            $cron,
            $daemons,
            $networkRules,
            $redirects,
            $phpVersion,
            $nginxConfig,
        );
    }

    /**
     * @param array<mixed, mixed>|null $data
     */
    private function parseSsl(?array $data): ?SslConfig
    {
        if ($data === null) {
            return null;
        }

        return new SslConfig(
            isset($data['enabled']) ? (bool) $data['enabled'] : false,
            isset($data['type']) && \is_string($data['type']) ? $data['type'] : 'letsencrypt',
            isset($data['force_https']) ? (bool) $data['force_https'] : false,
        );
    }

    /**
     * @param array<mixed, mixed>|null $data
     */
    private function parseEnvironment(?array $data): ?EnvironmentConfig
    {
        if ($data === null) {
            return null;
        }

        if (! isset($data['variables']) || ! \is_array($data['variables'])) {
            return null;
        }

        $variables = [];

        foreach ($data['variables'] as $key => $value) {
            if (\is_string($key) && (\is_string($value) || \is_numeric($value))) {
                $variables[$key] = (string) $value;
            }
        }

        return new EnvironmentConfig($variables);
    }

    /**
     * @param array<mixed, mixed>|null $data
     */
    private function parseServerLifecycle(?array $data): ?ServerLifecycleConfig
    {
        if ($data === null) {
            return null;
        }

        $modeRaw = $data['mode'] ?? null;
        $mode = \is_string($modeRaw) ? $modeRaw : '';

        $idRaw = $data['id'] ?? null;
        $id = \is_string($idRaw) || \is_int($idRaw) ? (string) $idRaw : null;

        $cleanupRaw = $data['cleanup'] ?? null;
        $cleanup = \is_string($cleanupRaw) ? $cleanupRaw : null;

        $ttlRaw = $data['ttl'] ?? null;
        $ttl = \is_string($ttlRaw) || \is_int($ttlRaw) ? (string) $ttlRaw : null;

        $specRaw = $data['spec'] ?? [];
        $spec = \is_array($specRaw) ? $specRaw : [];

        return new ServerLifecycleConfig($mode, $id, $cleanup, $ttl, $spec);
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function parseQueue(array $data): QueueConfig
    {
        return new QueueConfig(
            isset($data['enabled']) ? (bool) $data['enabled'] : false,
            isset($data['connection']) && \is_string($data['connection']) ? $data['connection'] : 'database',
            isset($data['queue']) && \is_string($data['queue']) ? $data['queue'] : 'default',
            isset($data['processes']) && \is_int($data['processes']) ? $data['processes'] : (isset($data['processes']) && \is_numeric($data['processes']) ? (int) $data['processes'] : 1),
            isset($data['max_tries']) && \is_int($data['max_tries']) ? $data['max_tries'] : (isset($data['max_tries']) && \is_numeric($data['max_tries']) ? (int) $data['max_tries'] : 1),
            isset($data['timeout']) && \is_int($data['timeout']) ? $data['timeout'] : (isset($data['timeout']) && \is_numeric($data['timeout']) ? (int) $data['timeout'] : 60),
            isset($data['restart_on_deploy']) ? (bool) $data['restart_on_deploy'] : true,
            isset($data['max_seconds']) && \is_int($data['max_seconds']) ? $data['max_seconds'] : (isset($data['max_seconds']) && \is_numeric($data['max_seconds']) ? (int) $data['max_seconds'] : 60),
            isset($data['sleep']) && \is_int($data['sleep']) ? $data['sleep'] : (isset($data['sleep']) && \is_numeric($data['sleep']) ? (int) $data['sleep'] : 30),
        );
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function parseCron(array $data): CronConfig
    {
        $command = isset($data['command']) && \is_string($data['command']) ? $data['command'] : '';
        $frequency = isset($data['frequency']) && \is_string($data['frequency']) ? $data['frequency'] : 'daily';
        $user = isset($data['user']) && \is_string($data['user']) ? $data['user'] : 'ploi';
        $enabled = isset($data['enabled']) ? (bool) $data['enabled'] : true;

        return new CronConfig($command, $frequency, $user, $enabled);
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function parseDaemon(array $data): DaemonConfig
    {
        $command = isset($data['command']) && \is_string($data['command']) ? $data['command'] : '';
        $user = isset($data['user']) && \is_string($data['user']) ? $data['user'] : 'ploi';
        $processes = isset($data['processes']) && \is_int($data['processes']) ? $data['processes'] : (isset($data['processes']) && \is_numeric($data['processes']) ? (int) $data['processes'] : 1);
        $directory = isset($data['directory']) && \is_string($data['directory']) ? $data['directory'] : '';
        $enabled = isset($data['enabled']) ? (bool) $data['enabled'] : true;
        $restartDelay = isset($data['restart_delay']) && \is_int($data['restart_delay']) ? $data['restart_delay'] : (isset($data['restart_delay']) && \is_numeric($data['restart_delay']) ? (int) $data['restart_delay'] : 10);

        return new DaemonConfig($command, $user, $processes, $directory, $enabled, $restartDelay);
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function parseNetworkRule(array $data): NetworkRuleConfig
    {
        $name = isset($data['name']) && \is_string($data['name']) ? $data['name'] : 'Unnamed Rule';
        $port = isset($data['port']) && \is_int($data['port']) ? $data['port'] : (isset($data['port']) && \is_numeric($data['port']) ? (int) $data['port'] : 0);
        $type = isset($data['type']) && \is_string($data['type']) ? $data['type'] : 'tcp';
        $ruleType = isset($data['rule_type']) && \is_string($data['rule_type']) ? $data['rule_type'] : 'allow';
        $fromIp = isset($data['from_ip']) && \is_string($data['from_ip']) ? $data['from_ip'] : null;

        return new NetworkRuleConfig($name, $port, $type, $ruleType, $fromIp);
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function parseRedirect(array $data): RedirectConfig
    {
        $from = isset($data['from']) && \is_string($data['from']) ? $data['from'] : '';
        $to = isset($data['to']) && \is_string($data['to']) ? $data['to'] : '';
        $typeRaw = $data['type'] ?? null;
        $type = \is_int($typeRaw) || \is_string($typeRaw) ? $typeRaw : 301;
        $enabled = isset($data['enabled']) ? (bool) $data['enabled'] : true;

        return new RedirectConfig($from, $to, $type, $enabled);
    }
}
