<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ProfileConfig;

final class FindOrphanedSitesAction
{
    /**
     * Find orphaned preview sites.
     *
     * @param array<int, array{site_id: int, domain: string}> $sites
     * @param array<int> $openPRs
     *
     * @return array<int, array{site_id: int, domain: string, pr_number: int}>
     */
    public function handle(array $sites, array $openPRs, ProfileConfig $profile): array
    {
        $orphaned = [];
        $protectedSites = [];

        foreach ($sites as $site) {
            $domain = $site['domain'];

            $prNumber = $this->extractPRNumber($domain, $profile);

            if ($prNumber === null || \in_array($prNumber, $openPRs, true)) {
                $protectedSites[$site['site_id']] = true;
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

        return \array_values(\array_filter($orphaned, static fn (array $site): bool => ! isset($protectedSites[$site['site_id']])));
    }

    private function extractPRNumber(string $domain, ProfileConfig $profile): ?int
    {
        if ($profile->name() !== 'preview') {
            return null;
        }
        $previewDomain = $profile->get('domain');
        if (! \is_string($previewDomain) || \substr_count($previewDomain, '${GITHUB_PR_NUMBER}') !== 1) {
            return null;
        }
        $pattern = \preg_quote($previewDomain, '/');
        $pattern = \str_replace(\preg_quote('${GITHUB_PR_NUMBER}', '/'), '([1-9][0-9]*)', $pattern);
        if (\preg_match('/\\A'.$pattern.'\\z/', $domain, $matches) !== 1) {
            return null;
        }
        $number = \filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $number === false ? null : $number;
    }
}
