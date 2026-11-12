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
        $placeholder = '${GITHUB_PR_NUMBER}';
        $previewDomain = $profile->get('domain');
        if (! \is_string($previewDomain) || \substr_count($previewDomain, $placeholder) !== 1
            || ! \filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return null;
        }

        $template = \trim($previewDomain);
        $url = \str_contains($template, '://') ? $template : 'https://'.$template;
        $scheme = \parse_url($url, PHP_URL_SCHEME);
        $host = \parse_url($url, PHP_URL_HOST);
        if (! \is_string($scheme) || ! \in_array(\strtolower($scheme), ['http', 'https'], true)
            || ! \is_string($host) || ! \str_contains($host, $placeholder)
            || ! \filter_var(\str_replace($placeholder, '1', $host), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return null;
        }
        $pattern = \str_replace(\preg_quote($placeholder, '/'), '([1-9][0-9]*)', \preg_quote($host, '/'));
        if (\preg_match('/\\A'.$pattern.'\\z/i', $domain, $matches) !== 1) {
            return null;
        }
        $number = \filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $number === false ? null : $number;
    }
}
