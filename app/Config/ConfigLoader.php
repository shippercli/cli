<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    public function __construct(
        private readonly string $configPath = 'deployer.yml',
    ) {}

    public function load(): DeployerConfig
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
     * @param array<string, mixed> $data
     */
    private function parseConfig(array $data): DeployerConfig
    {
        $projects = [];
        $providers = $data['providers'] ?? [];

        if (! \is_array($providers)) {
            $providers = [];
        }

        if (isset($data['projects']) && \is_array($data['projects'])) {
            foreach ($data['projects'] as $projectName => $projectData) {
                if (\is_string($projectName) && \is_array($projectData)) {
                    /** @var array<string, mixed> $projectData */
                    $projects[$projectName] = $this->parseProject($projectName, $projectData);
                }
            }
        }

        return new DeployerConfig($projects, $providers);
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

        $provider = $data['provider'] ?? '';
        $path = $data['path'] ?? '';

        return new ProjectConfig(
            $name,
            \is_string($provider) ? $provider : '',
            \is_string($path) ? $path : '',
            $profiles,
        );
    }
}
