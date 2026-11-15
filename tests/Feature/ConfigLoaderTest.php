<?php

declare(strict_types=1);

use App\Config\ConfigLoader;
use App\Config\CronConfig;
use App\Config\DaemonConfig;
use App\Config\EnvironmentConfig;
use App\Config\NetworkRuleConfig;
use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Config\QueueConfig;
use App\Config\RedirectConfig;
use App\Config\SslConfig;

\test('config loader loads valid config', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();

    \expect($config->projects())->toBeArray();
    \expect($config->projects())->toHaveKey('api');
    \expect($config->projects())->toHaveKey('frontend');
});

\test('config loader throws exception for missing file', function (): void {
    $loader = new ConfigLoader('nonexistent.yml');
    $loader->load();
})->throws(RuntimeException::class, 'Config file not found');

\test('loaded project has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->name())->toBe('api');
    \expect($project->provider())->toBe('ploi');
    \expect($project->path())->toBe('./examples/api');
});

\test('loaded project has profiles', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->profiles())->toBeArray();
    \expect($project->profiles())->toHaveKey('production');
    \expect($project->profiles())->toHaveKey('staging');
    \expect($project->profiles())->toHaveKey('preview');
});

\test('loaded profile has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('production');

    \expect($profile)->not->toBeNull();
    \assert($profile !== null);
    \expect($profile->name())->toBe('production');
    \expect($profile->branch())->toBe('main');
});

\test('loaded project has databases', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->databases())->toBeArray();
    \expect($project->databases())->toHaveKey('main');
    \expect($project->hasSection('databases'))->toBeTrue();

    $frontend = $config->getProject('frontend');
    \assert($frontend !== null);
    \expect($frontend->hasSection('databases'))->toBeFalse();
});

\test('loaded database has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $database = $project->getDatabase('main');

    \expect($database)->not->toBeNull();
    \assert($database !== null);
    \expect($database->name())->toBe('shipper_cli_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}');
    \expect($database->user())->toBe('shipper_cli_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}');
    \expect($database->type())->toBe('mysql');
});

\test('loaded project has ssl config', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->ssl()->enabled())->toBeTrue();
    \expect($project->ssl()->type())->toBe('letsencrypt');
});

\test('project without ssl config defaults to disabled', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->ssl()->enabled())->toBeFalse();
    \expect($project->ssl()->type())->toBe('letsencrypt');
});

\test('loaded project has environment config', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->environment()->isEmpty())->toBeFalse();
    \expect($project->environment()->variables())->toHaveKey('APP_NAME');
    \expect($project->environment()->variables()['APP_NAME'])->toBe('Shipper API');
    \expect($project->environment()->variables())->toHaveKey('LOG_CHANNEL');
    \expect($project->environment()->variables()['LOG_CHANNEL'])->toBe('stack');
});

\test('loaded profile has environment config', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('production');

    \expect($profile)->not->toBeNull();
    \assert($profile !== null);
    \expect($profile->environment()->isEmpty())->toBeFalse();
    \expect($profile->environment()->variables()['APP_ENV'])->toBe('production');
    \expect($profile->environment()->variables()['APP_DEBUG'])->toBe('false');
});

\test('project environment merges with profile environment (profile overrides)', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('staging');
    \assert($profile !== null);

    $merged = $project->environment()->mergeWith($profile->environment());

    \expect($merged->variables())->toHaveKey('APP_NAME');
    \expect($merged->variables()['APP_NAME'])->toBe('Shipper API');
    \expect($merged->variables()['APP_ENV'])->toBe('staging');
    \expect($merged->variables()['APP_DEBUG'])->toBe('true');
});

\test('project without environment config defaults to empty', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->environment()->isEmpty())->toBeTrue();
});

\test('loaded project has deploy script', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->deployScript())->not->toBe('');
    \expect($project->deployScript())->toContain('composer install --no-dev');
    \expect($project->deployScript())->toContain('php artisan migrate --force');
});

\test('project without deploy script defaults to empty string', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->deployScript())->toBe('');
});

\test('profile can override project deploy script', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('production');

    \expect($profile)->not->toBeNull();
    \assert($profile !== null);
    $profileScript = $profile->get('deploy_script');
    \expect($profileScript)->not->toBeNull();
    \expect($profileScript)->not->toBe('');
    \expect($profileScript)->toContain('php artisan config:cache');
    \expect($profileScript)->toContain('php artisan route:cache');
    \expect($profileScript)->toContain('--optimize-autoloader');
});

\test('profile without deploy script returns null for deploy_script key', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('staging');

    \expect($profile)->not->toBeNull();
    \assert($profile !== null);
    \expect($profile->get('deploy_script'))->toBeNull();
});

\test('loaded profile has aliases', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('production');

    \expect($profile)->not->toBeNull();
    \assert($profile !== null);
    \expect($profile->aliases())->toBeArray();
    \expect($profile->aliases())->toHaveCount(2);
    \expect($profile->aliases())->toContain('www.shipper-cli-api-production.shippercli.com');
    \expect($profile->aliases())->toContain('api-v2.shipper-cli-api-production.shippercli.com');
});

\test('profile without aliases returns empty array', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('staging');

    \expect($profile)->not->toBeNull();
    \assert($profile !== null);
    \expect($profile->aliases())->toBeArray();
    \expect($profile->aliases())->toHaveCount(0);
});

\test('aliases returns empty array when value is not an array', function (): void {
    $profile = new ProfileConfig('test', 'main', ['aliases' => 'not-an-array']);

    \expect($profile->aliases())->toBeArray();
    \expect($profile->aliases())->toHaveCount(0);
});

\test('aliases filters out non-string values', function (): void {
    $profile = new ProfileConfig('test', 'main', ['aliases' => ['valid.example.com', 42, null, 'also-valid.example.com']]);

    \expect($profile->aliases())->toBeArray();
    \expect($profile->aliases())->toHaveCount(2);
    \expect($profile->aliases())->toContain('valid.example.com');
    \expect($profile->aliases())->toContain('also-valid.example.com');
});

\test('loaded project has queues', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->queues())->toBeArray();
    \expect($project->queues())->toHaveKey('default');
    \expect($project->queues())->toHaveKey('emails');
});

\test('loaded queue has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $queue = $project->getQueue('default');

    \expect($queue)->not->toBeNull();
    \assert($queue !== null);
    \expect($queue->connection())->toBe('database');
    \expect($queue->queue())->toBe('default');
    \expect($queue->maxSeconds())->toBe(60);
    \expect($queue->sleep())->toBe(30);
    \expect($queue->processes())->toBe(1);
    \expect($queue->maxTries())->toBe(1);
});

\test('loaded queue with custom values has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $queue = $project->getQueue('emails');

    \expect($queue)->not->toBeNull();
    \assert($queue !== null);
    \expect($queue->connection())->toBe('redis');
    \expect($queue->queue())->toBe('emails');
    \expect($queue->maxSeconds())->toBe(120);
    \expect($queue->sleep())->toBe(10);
    \expect($queue->processes())->toBe(3);
    \expect($queue->maxTries())->toBe(3);
});

\test('project without queues returns empty array', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->queues())->toBeArray();
    \expect($project->queues())->toHaveCount(0);
});

\test('getQueue returns null for non-existent queue', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);

    \expect($project->getQueue('nonexistent'))->toBeNull();
});

\test('queue config uses defaults when values not provided', function (): void {
    $queue = new QueueConfig;

    \expect($queue->connection())->toBe('database');
    \expect($queue->queue())->toBe('default');
    \expect($queue->maxSeconds())->toBe(60);
    \expect($queue->sleep())->toBe(30);
    \expect($queue->processes())->toBe(1);
    \expect($queue->maxTries())->toBe(1);
});

\test('loaded project has cron jobs', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->cron())->toBeArray();
    \expect($project->cron())->toHaveKey('scheduler');
    \expect($project->cron())->toHaveKey('backup');
});

\test('loaded cron job has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $cronJob = $project->getCron('scheduler');

    \expect($cronJob)->not->toBeNull();
    \assert($cronJob !== null);
    \expect($cronJob->command())->toContain('php artisan schedule:run');
    \expect($cronJob->frequency())->toBe('* * * * *');
    \expect($cronJob->user())->toBe('ploi');
});

\test('loaded cron job with custom frequency has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $cronJob = $project->getCron('backup');

    \expect($cronJob)->not->toBeNull();
    \assert($cronJob !== null);
    \expect($cronJob->command())->toContain('php artisan backup:run');
    \expect($cronJob->frequency())->toBe('0 2 * * *');
    \expect($cronJob->user())->toBe('ploi');
});

\test('project without cron returns empty array', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->cron())->toBeArray();
    \expect($project->cron())->toHaveCount(0);
});

\test('getCron returns null for non-existent cron job', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);

    \expect($project->getCron('nonexistent'))->toBeNull();
});

\test('cron config uses default user when not provided', function (): void {
    $cron = new CronConfig('echo hello', '* * * * *');

    \expect($cron->command())->toBe('echo hello');
    \expect($cron->frequency())->toBe('* * * * *');
    \expect($cron->user())->toBe('ploi');
});

\test('loaded project has redirects', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->redirects())->toBeArray();
    \expect($project->redirects())->toHaveKey('www-to-non-www');
    \expect($project->redirects())->toHaveKey('legacy');
});

\test('loaded redirect has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $redirect = $project->getRedirect('www-to-non-www');

    \expect($redirect)->not->toBeNull();
    \assert($redirect !== null);
    \expect($redirect->from())->toBe('/old-api');
    \expect($redirect->to())->toBe('/api/v2');
    \expect($redirect->type())->toBe('redirect');
});

\test('loaded redirect with permanent type has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $redirect = $project->getRedirect('legacy');

    \expect($redirect)->not->toBeNull();
    \assert($redirect !== null);
    \expect($redirect->from())->toBe('/legacy');
    \expect($redirect->to())->toBe('/v2');
    \expect($redirect->type())->toBe('permanent');
});

\test('project without redirects returns empty array', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->redirects())->toBeArray();
    \expect($project->redirects())->toHaveCount(0);
});

\test('getRedirect returns null for non-existent redirect', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);

    \expect($project->getRedirect('nonexistent'))->toBeNull();
});

\test('redirect config uses default type when not provided', function (): void {
    $redirect = new RedirectConfig('/old', '/new');

    \expect($redirect->from())->toBe('/old');
    \expect($redirect->to())->toBe('/new');
    \expect($redirect->type())->toBe(301);
});

\test('loaded project has php version', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->phpVersion())->toBe('8.3');
});

\test('project without php version defaults to empty string', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->phpVersion())->toBe('');
});

\test('project without nginx config defaults to empty string', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->nginxConfig())->toBe('');
});

\test('loaded project has daemons', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->daemons())->toBeArray();
    \expect($project->daemons())->toHaveKey('websocket');
    \expect($project->daemons())->toHaveKey('horizon');
});

\test('loaded daemon has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $daemon = $project->getDaemon('websocket');

    \expect($daemon)->not->toBeNull();
    \assert($daemon !== null);
    \expect($daemon->command())->toBe('php artisan websockets:serve');
    \expect($daemon->user())->toBe('ploi');
    \expect($daemon->processes())->toBe(1);
    \expect($daemon->directory())->toBe('/home/ploi/{site}');
});

\test('loaded daemon without directory has empty directory', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $daemon = $project->getDaemon('horizon');

    \expect($daemon)->not->toBeNull();
    \assert($daemon !== null);
    \expect($daemon->command())->toBe('php artisan horizon');
    \expect($daemon->directory())->toBe('');
});

\test('project without daemons returns empty array', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->daemons())->toBeArray();
    \expect($project->daemons())->toHaveCount(0);
});

\test('getDaemon returns null for non-existent daemon', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);

    \expect($project->getDaemon('nonexistent'))->toBeNull();
});

\test('daemon config uses defaults when values not provided', function (): void {
    $daemon = new DaemonConfig('php artisan horizon');

    \expect($daemon->command())->toBe('php artisan horizon');
    \expect($daemon->user())->toBe('ploi');
    \expect($daemon->processes())->toBe(1);
    \expect($daemon->directory())->toBe('');
});

\test('loaded project has network rules', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->networkRules())->toBeArray();
    \expect($project->networkRules())->toHaveKey('allow-redis');
});

\test('loaded network rule has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $rule = $project->getNetworkRule('allow-redis');

    \expect($rule)->not->toBeNull();
    \assert($rule !== null);
    \expect($rule->name())->toBe('Allow Redis');
    \expect($rule->port())->toBe(6379);
    \expect($rule->type())->toBe('tcp');
    \expect($rule->ruleType())->toBe('allow');
    \expect($rule->fromIp())->toBe('10.0.0.0/8');
});

\test('project without network rules returns empty array', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->networkRules())->toBeArray();
    \expect($project->networkRules())->toHaveCount(0);
});

\test('getNetworkRule returns null for non-existent rule', function (): void {
    $loader = new ConfigLoader('shipper.yml.example');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);

    \expect($project->getNetworkRule('nonexistent'))->toBeNull();
});

\test('network rule config uses defaults when values not provided', function (): void {
    $rule = new NetworkRuleConfig('Allow SSH', 22);

    \expect($rule->name())->toBe('Allow SSH');
    \expect($rule->port())->toBe(22);
    \expect($rule->type())->toBe('tcp');
    \expect($rule->ruleType())->toBe('allow');
    \expect($rule->fromIp())->toBeNull();
});

\test('network rule config with from_ip set to null returns null', function (): void {
    $rule = new NetworkRuleConfig('Allow HTTP', 80, 'tcp', 'allow', null);

    \expect($rule->fromIp())->toBeNull();
});

\test('project config php version and nginx config parse correctly from inline data', function (): void {
    $project = new ProjectConfig(
        'test',
        'ploi',
        './examples/test',
        [],
        [],
        '/public',
        '/',
        [],
        new SslConfig,
        new EnvironmentConfig,
        '',
        [],
        [],
        [],
        [],
        [],
        '8.2',
        "location /api {\n    try_files \$uri \$uri/ /index.php?\$query_string;\n}",
    );

    \expect($project->phpVersion())->toBe('8.2');
    \expect($project->nginxConfig())->toContain('location /api');
    \expect($project->nginxConfig())->toContain('try_files');
});

\test('project config preserves declared workload sections', function (): void {
    $project = new ProjectConfig(
        'test',
        'ploi',
        './examples/test',
        [],
        [],
        '/public',
        '/',
        [],
        null,
        null,
        '',
        [],
        [],
        [],
        [],
        [],
        '',
        '',
        ['queues' => true],
    );

    \expect($project->hasSection('queues'))->toBeTrue()
        ->and($project->hasSection('cron'))->toBeFalse();
});

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

\test('config loader parses existing server lifecycle config from profile', function (): void {
    $configFile = \tempnam(\sys_get_temp_dir(), 'shipper-server-existing-');
    \expect($configFile)->not->toBeFalse();
    \assert(\is_string($configFile));

    \file_put_contents($configFile, <<<'YAML'
projects:
  api:
    provider: ploi
    path: ./api
    repository:
      provider: github
      name: shippercli/cli
    profiles:
      production:
        branch: main
        domain: api.example.com
        infrastructure:
          server:
            mode: existing
            id: "123456"
YAML);

    $loader = new ConfigLoader($configFile);
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('production');
    \assert($profile !== null);
    $server = $profile->server();

    \expect($server)->not->toBeNull();
    \assert($server !== null);
    \expect($server->mode())->toBe('existing');
    \expect($server->id())->toBe('123456');
    \expect($server->cleanup())->toBeNull();
});

\test('config loader parses create server lifecycle config from profile', function (): void {
    $configFile = \tempnam(\sys_get_temp_dir(), 'shipper-server-create-');
    \expect($configFile)->not->toBeFalse();
    \assert(\is_string($configFile));

    \file_put_contents($configFile, <<<'YAML'
projects:
  api:
    provider: ploi
    path: ./api
    repository:
      provider: github
      name: shippercli/cli
    profiles:
      preview:
        branch: feature/test
        domain: preview.example.com
        infrastructure:
          server:
            mode: create
            cleanup: destroy
            ttl: 72h
            spec:
              name: "api-pr-123"
              region: "eu-west"
              size: "small"
YAML);

    $loader = new ConfigLoader($configFile);
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('preview');
    \assert($profile !== null);
    $server = $profile->server();

    \expect($server)->not->toBeNull();
    \assert($server !== null);
    \expect($server->mode())->toBe('create');
    \expect($server->cleanup())->toBe('destroy');
    \expect($server->ttl())->toBe('72h');
    \expect($server->spec())->toMatchArray([
        'name' => 'api-pr-123',
        'region' => 'eu-west',
        'size' => 'small',
    ]);
});
