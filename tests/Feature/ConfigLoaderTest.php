<?php

declare(strict_types=1);

use App\Config\ConfigLoader;

\test('config loader loads valid config', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();

    \expect($config->projects())->toBeArray();
    \expect($config->projects())->toHaveKey('api');
    \expect($config->projects())->toHaveKey('frontend');
});

\test('config loader throws exception for missing file', function (): void {
    $loader = new ConfigLoader('nonexistent.yml');
    $loader->load();
})->throws(\RuntimeException::class, 'Config file not found');

\test('loaded project has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->name())->toBe('api');
    \expect($project->provider())->toBe('ploi');
    \expect($project->path())->toBe('./examples/api');
});

\test('loaded project has profiles', function (): void {
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->databases())->toBeArray();
    \expect($project->databases())->toHaveKey('main');
});

\test('loaded database has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->ssl()->enabled())->toBeTrue();
    \expect($project->ssl()->type())->toBe('letsencrypt');
});

\test('project without ssl config defaults to disabled', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->ssl()->enabled())->toBeFalse();
    \expect($project->ssl()->type())->toBe('letsencrypt');
});

\test('loaded project has environment config', function (): void {
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->environment()->isEmpty())->toBeTrue();
});

\test('loaded project has deploy script', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->deployScript())->not->toBe('');
    \expect($project->deployScript())->toContain('composer install --no-dev');
    \expect($project->deployScript())->toContain('php artisan migrate --force');
});

\test('project without deploy script defaults to empty string', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->deployScript())->toBe('');
});

\test('profile can override project deploy script', function (): void {
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('staging');

    \expect($profile)->not->toBeNull();
    \assert($profile !== null);
    \expect($profile->get('deploy_script'))->toBeNull();
});

\test('loaded profile has aliases', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);
    $profile = $project->getProfile('production');

    \expect($profile)->not->toBeNull();
    \assert($profile !== null);
    \expect($profile->aliases())->toBeArray();
    \expect($profile->aliases())->toHaveCount(2);
    \expect($profile->aliases())->toContain('www.shipper-cli-api-production.ulties.dev');
    \expect($profile->aliases())->toContain('api-v2.shipper-cli-api-production.ulties.dev');
});

\test('profile without aliases returns empty array', function (): void {
    $loader = new ConfigLoader('shipper.yml');
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
    $profile = new \App\Config\ProfileConfig('test', 'main', ['aliases' => 'not-an-array']);

    \expect($profile->aliases())->toBeArray();
    \expect($profile->aliases())->toHaveCount(0);
});

\test('aliases filters out non-string values', function (): void {
    $profile = new \App\Config\ProfileConfig('test', 'main', ['aliases' => ['valid.example.com', 42, null, 'also-valid.example.com']]);

    \expect($profile->aliases())->toBeArray();
    \expect($profile->aliases())->toHaveCount(2);
    \expect($profile->aliases())->toContain('valid.example.com');
    \expect($profile->aliases())->toContain('also-valid.example.com');
});

\test('loaded project has queues', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->queues())->toBeArray();
    \expect($project->queues())->toHaveKey('default');
    \expect($project->queues())->toHaveKey('emails');
});

\test('loaded queue has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->queues())->toBeArray();
    \expect($project->queues())->toHaveCount(0);
});

\test('getQueue returns null for non-existent queue', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);

    \expect($project->getQueue('nonexistent'))->toBeNull();
});

\test('queue config uses defaults when values not provided', function (): void {
    $queue = new \App\Config\QueueConfig;

    \expect($queue->connection())->toBe('database');
    \expect($queue->queue())->toBe('default');
    \expect($queue->maxSeconds())->toBe(60);
    \expect($queue->sleep())->toBe(30);
    \expect($queue->processes())->toBe(1);
    \expect($queue->maxTries())->toBe(1);
});

\test('loaded project has cron jobs', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->cron())->toBeArray();
    \expect($project->cron())->toHaveKey('scheduler');
    \expect($project->cron())->toHaveKey('backup');
});

\test('loaded cron job has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->cron())->toBeArray();
    \expect($project->cron())->toHaveCount(0);
});

\test('getCron returns null for non-existent cron job', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);

    \expect($project->getCron('nonexistent'))->toBeNull();
});

\test('cron config uses default user when not provided', function (): void {
    $cron = new \App\Config\CronConfig('echo hello', '* * * * *');

    \expect($cron->command())->toBe('echo hello');
    \expect($cron->frequency())->toBe('* * * * *');
    \expect($cron->user())->toBe('ploi');
});

\test('loaded project has redirects', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->redirects())->toBeArray();
    \expect($project->redirects())->toHaveKey('www-to-non-www');
    \expect($project->redirects())->toHaveKey('legacy');
});

\test('loaded redirect has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->redirects())->toBeArray();
    \expect($project->redirects())->toHaveCount(0);
});

\test('getRedirect returns null for non-existent redirect', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);

    \expect($project->getRedirect('nonexistent'))->toBeNull();
});

\test('redirect config uses default type when not provided', function (): void {
    $redirect = new \App\Config\RedirectConfig('/old', '/new');

    \expect($redirect->from())->toBe('/old');
    \expect($redirect->to())->toBe('/new');
    \expect($redirect->type())->toBe(301);
});

\test('loaded project has php version', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->phpVersion())->toBe('8.3');
});

\test('project without php version defaults to empty string', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->phpVersion())->toBe('');
});

\test('project without nginx config defaults to empty string', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->nginxConfig())->toBe('');
});

\test('loaded project has daemons', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->daemons())->toBeArray();
    \expect($project->daemons())->toHaveKey('websocket');
    \expect($project->daemons())->toHaveKey('horizon');
});

\test('loaded daemon has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->daemons())->toBeArray();
    \expect($project->daemons())->toHaveCount(0);
});

\test('getDaemon returns null for non-existent daemon', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);

    \expect($project->getDaemon('nonexistent'))->toBeNull();
});

\test('daemon config uses defaults when values not provided', function (): void {
    $daemon = new \App\Config\DaemonConfig('php artisan horizon');

    \expect($daemon->command())->toBe('php artisan horizon');
    \expect($daemon->user())->toBe('ploi');
    \expect($daemon->processes())->toBe(1);
    \expect($daemon->directory())->toBe('');
});

\test('loaded project has network rules', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->networkRules())->toBeArray();
    \expect($project->networkRules())->toHaveKey('allow-redis');
});

\test('loaded network rule has expected properties', function (): void {
    $loader = new ConfigLoader('shipper.yml');
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
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('frontend');

    \expect($project)->not->toBeNull();
    \assert($project !== null);
    \expect($project->networkRules())->toBeArray();
    \expect($project->networkRules())->toHaveCount(0);
});

\test('getNetworkRule returns null for non-existent rule', function (): void {
    $loader = new ConfigLoader('shipper.yml');
    $config = $loader->load();
    $project = $config->getProject('api');
    \assert($project !== null);

    \expect($project->getNetworkRule('nonexistent'))->toBeNull();
});

\test('network rule config uses defaults when values not provided', function (): void {
    $rule = new \App\Config\NetworkRuleConfig('Allow SSH', 22);

    \expect($rule->name())->toBe('Allow SSH');
    \expect($rule->port())->toBe(22);
    \expect($rule->type())->toBe('tcp');
    \expect($rule->ruleType())->toBe('allow');
    \expect($rule->fromIp())->toBeNull();
});

\test('network rule config with from_ip set to null returns null', function (): void {
    $rule = new \App\Config\NetworkRuleConfig('Allow HTTP', 80, 'tcp', 'allow', null);

    \expect($rule->fromIp())->toBeNull();
});

\test('project config php version and nginx config parse correctly from inline data', function (): void {
    $project = new \App\Config\ProjectConfig(
        'test',
        'ploi',
        './examples/test',
        [],
        [],
        '/public',
        '/',
        [],
        new \App\Config\SslConfig,
        new \App\Config\EnvironmentConfig,
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
