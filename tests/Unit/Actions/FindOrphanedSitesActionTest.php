<?php

declare(strict_types=1);

use App\Actions\FindOrphanedSitesAction;
use App\Config\ProfileConfig;

\test('an open or unrecognized alias protects the whole site regardless of ordering', function (): void {
    $action = new FindOrphanedSitesAction;
    $profile = new ProfileConfig('preview', 'main', ['domain' => '${GITHUB_PR_NUMBER}.api.example.com']);
    foreach (['42.api.example.com', 'production.example.com'] as $protectedDomain) {
        $sites = [
            ['site_id' => 12, 'domain' => '43.api.example.com'],
            ['site_id' => 12, 'domain' => $protectedDomain],
            ['site_id' => 13, 'domain' => '44.api.example.com'],
        ];
        $expected = [['site_id' => 13, 'domain' => '44.api.example.com', 'pr_number' => 44]];
        \expect($action->handle($sites, [42], $profile))->toBe($expected);
        \expect($action->handle(\array_reverse($sites), [42], $profile))->toBe($expected);
    }
});

\test('orphan classification only matches the current preview profile', function (): void {
    $action = new FindOrphanedSitesAction;
    $profile = new ProfileConfig('preview', 'main', ['domain' => '${GITHUB_PR_NUMBER}.api.example.com']);
    $sites = [
        ['site_id' => 1, 'domain' => '42.api.example.com'],
        ['site_id' => 2, 'domain' => '43.api.example.com'],
        ['site_id' => 3, 'domain' => '44.other.example.com'],
        ['site_id' => 4, 'domain' => 'production.example.com'],
        ['site_id' => 5, 'domain' => '0.api.example.com'],
        ['site_id' => 6, 'domain' => \str_repeat('9', 30).'.api.example.com'],
        ['site_id' => 7, 'domain' => "45.api.example.com\n"],
    ];
    \expect($action->handle($sites, [42], $profile))->toBe([
        ['site_id' => 2, 'domain' => '43.api.example.com', 'pr_number' => 43],
    ]);
    \expect($action->handle($sites, [], new ProfileConfig('production', 'main', $profile->config())))->toBe([]);
});

\test('ambiguous preview domain placeholders are not cleanup evidence', function (): void {
    $profile = new ProfileConfig('preview', 'main', ['domain' => '${GITHUB_PR_NUMBER}.${GITHUB_PR_NUMBER}.example.com']);
    \expect((new FindOrphanedSitesAction)->handle([['site_id' => 1, 'domain' => '42.43.example.com']], [], $profile))->toBe([]);
});

\test('preview URL templates classify inventory by exact hostname regardless of case', function (): void {
    $profile = new ProfileConfig('preview', 'main', ['domain' => 'https://PR-${GITHUB_PR_NUMBER}.API.example.com:443/status']);
    $sites = [
        ['site_id' => 1, 'domain' => 'pr-42.api.example.com'],
        ['site_id' => 2, 'domain' => 'pr-43.api.example.com.evil.example'],
    ];
    \expect((new FindOrphanedSitesAction)->handle($sites, [], $profile))->toBe([
        ['site_id' => 1, 'domain' => 'pr-42.api.example.com', 'pr_number' => 42],
    ]);
});

\test('a placeholder outside the preview hostname is not cleanup evidence', function (): void {
    $profile = new ProfileConfig('preview', 'main', ['domain' => 'https://pr-42.example.com/${GITHUB_PR_NUMBER}']);
    \expect((new FindOrphanedSitesAction)->handle([['site_id' => 1, 'domain' => 'pr-42.example.com']], [], $profile))->toBe([]);
});
