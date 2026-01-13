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
                    $profiles[$profileName] = new ProfileConfig(
                        $profileName,
                        \is_string($branch) ? $branch : '',
                        $profileData,
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

        $provider = $data['provider'] ?? '';
        $path = $data['path'] ?? '';
        $repository = $data['repository'] ?? [];
        $webDirectory = $data['web_directory'] ?? '/public';
        $projectRoot = $data['project_root'] ?? '/';

        return new ProjectConfig(
            $name,
            \is_string($provider) ? $provider : '',
            \is_string($path) ? $path : '',
            $profiles,
            \is_array($repository) ? $repository : [],
            \is_string($webDirectory) ? $webDirectory : '/public',
            \is_string($projectRoot) ? $projectRoot : '/',
            $databases,
        );
    }
}
