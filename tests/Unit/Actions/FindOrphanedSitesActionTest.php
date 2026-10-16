<?php

declare(strict_types=1);

use App\Actions\FindOrphanedSitesAction;
use App\Config\ProfileConfig;

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
