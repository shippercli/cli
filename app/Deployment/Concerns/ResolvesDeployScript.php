<?php

declare(strict_types=1);

namespace App\Deployment\Concerns;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;

trait ResolvesDeployScript
{
    private function resolveDeployScript(ProjectConfig $project, ProfileConfig $profile): string
    {
        $profileScript = $profile->deployScript();

        if ($profileScript !== null && $profileScript !== '') {
            $script = $profileScript;
        } else {
            $script = $project->deployScript();
        }

        if ($script === '') {
            return '';
        }

        $domainValue = $profile->get('domain');
        $domain = \is_string($domainValue) ? $domainValue : '';
        $branch = $profile->branch();

        $script = \str_replace('{site}', $domain, $script);
        $script = \str_replace('{branch}', $branch, $script);

        return $script;
    }
}
