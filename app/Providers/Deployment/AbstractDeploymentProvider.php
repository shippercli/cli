<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;

abstract class AbstractDeploymentProvider implements DeploymentProviderInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected readonly array $config = [],
    ) {}

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = [];

        if ($project->provider() === '') {
            $errors[] = "Provider is required for project: {$project->name()}";
        }

        if ($project->path() === '') {
            $errors[] = "Path is required for project: {$project->name()}";
        }

        if ($profile->branch() === '') {
            $errors[] = "Branch is required for profile: {$profile->name()}";
        }

        return $errors;
    }

    abstract public function plan(ProjectConfig $project, ProfileConfig $profile): array;

    abstract public function apply(ProjectConfig $project, ProfileConfig $profile): bool;

    abstract public function getName(): string;
}
