<?php

declare(strict_types=1);

namespace App\Actions\Cleanup;

use App\Config\ProjectConfig;

final class FindOrphanedSitesAction
{
    /**
     * Find orphaned preview sites.
     *
     * @param array<int, array{site_id: int, domain: string}> $sites
     * @param array<int> $openPRs
     * @param array<string, ProjectConfig> $projects
     *
     * @return array<int, array{site_id: int, domain: string, pr_number: int}>
     */
    public function handle(array $sites, array $openPRs, array $projects): array
    {
        $orphaned = [];

        foreach ($sites as $site) {
            $domain = $site['domain'];

            $prNumber = $this->extractPRNumber($domain, $projects);

            if ($prNumber === null) {
                continue;
            }

            if (! \in_array($prNumber, $openPRs, true)) {
                $orphaned[] = [
                    'site_id' => $site['site_id'],
                    'domain' => $domain,
                    'pr_number' => $prNumber,
                ];
            }
        }

        return $orphaned;
    }

    /**
     * Extract PR number from domain.
     *
     * @param array<string, ProjectConfig> $projects
     */
    private function extractPRNumber(string $domain, array $projects): ?int
    {
        foreach ($projects as $project) {
            $profiles = $project->profiles();

            foreach ($profiles as $profile) {
                if ($profile->name() !== 'preview') {
                    continue;
                }

                $previewDomain = $profile->get('domain');
                if (! \is_string($previewDomain)) {
                    continue;
                }

                $pattern = \preg_quote($previewDomain, '/');
                $pattern = \str_replace(\preg_quote('${GITHUB_PR_NUMBER}', '/'), '(\d+)', $pattern);
                $pattern = '/^'.$pattern.'$/';

                if (\preg_match($pattern, $domain, $matches) === 1) {
                    if (isset($matches[1]) && \is_numeric($matches[1])) {
                        return (int) $matches[1];
                    }
                }
            }
        }

        return null;
    }
}
