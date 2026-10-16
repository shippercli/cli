<?php

declare(strict_types=1);

use App\Config\ConfigLoader;
use App\Config\ProjectConfig;

function loadBooleanTypeFixture(string $block): ProjectConfig
{
    $path = \tempnam(\sys_get_temp_dir(), 'shipper-boolean-config-');
    \assert(\is_string($path));
    \file_put_contents($path, "projects:\n  app:\n    provider: fixture\n    path: .\n".$block);
    try {
        $project = (new ConfigLoader($path))->load()->getProject('app');
        \assert($project instanceof ProjectConfig);

        return $project;
    } finally {
        \unlink($path);
    }
}

/** @return array<int, array{string, string, bool}> */
function booleanTypeFields(): array
{
    return [
        ["    ssl:\n      type: letsencrypt\n      enabled: %s\n", 'ssl.enabled', false],
        ["    ssl:\n      type: letsencrypt\n      force_https: %s\n", 'ssl.force_https', false],
        ["    queues:\n      worker:\n        connection: database\n        enabled: %s\n", 'queues.enabled', false],
        ["    queues:\n      worker:\n        connection: database\n        restart_on_deploy: %s\n", 'queues.restart_on_deploy', true],
        ["    cron:\n      job:\n        command: echo-fixture\n        enabled: %s\n", 'cron.enabled', true],
        ["    daemons:\n      worker:\n        command: echo-fixture\n        enabled: %s\n", 'daemons.enabled', true],
        ["    redirects:\n      rule:\n        from: /old\n        to: https://example.com/new\n        enabled: %s\n", 'redirects.enabled', true],
    ];
}

function loadedBooleanField(ProjectConfig $project, string $field): bool
{
    return match ($field) {
        'ssl.enabled' => $project->ssl()->enabled(),
        'ssl.force_https' => $project->ssl()->forceHttps(),
        'queues.enabled' => $project->queues()['worker']->enabled(),
        'queues.restart_on_deploy' => $project->queues()['worker']->restartOnDeploy(),
        'cron.enabled' => $project->cron()['job']->enabled(),
        'daemons.enabled' => $project->daemons()['worker']->enabled(),
        'redirects.enabled' => $project->redirects()['rule']->enabled(),
        default => throw new LogicException('Unknown fixture boolean field'),
    };
}

\test('project boolean fields reject non-boolean YAML types', function (string $value, string $template, string $field, bool $default): void {
    \expect(fn () => \loadBooleanTypeFixture(\sprintf($template, $value)))
        ->toThrow(RuntimeException::class, "{$field} must be a boolean");
})->with(['"false"', '"true"', '0', '1', 'null', '[]'])->with(\booleanTypeFields());

\test('project boolean fields preserve genuine YAML booleans', function (string $value, bool $expected, string $template, string $field, bool $default): void {
    $project = \loadBooleanTypeFixture(\sprintf($template, $value));
    \expect(\loadedBooleanField($project, $field))->toBe($expected);
})->with([['true', true], ['false', false]])->with(\booleanTypeFields());

\test('omitted project boolean fields keep documented defaults', function (string $template, string $field, bool $default): void {
    $block = \preg_replace('/^.*%s.*\n/m', '', $template);
    \assert(\is_string($block));
    \expect(\loadedBooleanField(\loadBooleanTypeFixture($block), $field))->toBe($default);
})->with(\booleanTypeFields());

\test('environment interpolation does not turn strings into boolean options', function (string $template, string $field, bool $default): void {
    $previous = \getenv('SHIPPER_BOOLEAN_TYPE_FIXTURE');
    \putenv('SHIPPER_BOOLEAN_TYPE_FIXTURE=false');
    try {
        \expect(fn () => \loadBooleanTypeFixture(\sprintf($template, '"${SHIPPER_BOOLEAN_TYPE_FIXTURE}"')))
            ->toThrow(RuntimeException::class, "{$field} must be a boolean");
    } finally {
        \putenv($previous === false ? 'SHIPPER_BOOLEAN_TYPE_FIXTURE' : 'SHIPPER_BOOLEAN_TYPE_FIXTURE='.$previous);
    }
})->with(\booleanTypeFields());

\test('provider profile options retain their original types for provider validation', function (): void {
    $project = \loadBooleanTypeFixture("    profiles:\n      production:\n        cpanel:\n          clean: \"false\"\n");
    \expect($project->getProfile('production')?->get('cpanel'))->toBe(['clean' => 'false']);
});
