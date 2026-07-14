<?php

declare(strict_types=1);

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\Providers\Cpanel\CpanelProvider;

function invokeCpanelProviderMethod(CpanelProvider $provider, string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionClass($provider);
    $reflectionMethod = $reflection->getMethod($method);

    return $reflectionMethod->invoke($provider, ...$arguments);
}

function makeCpanelProject(string $path = './site', string $webDirectory = '/'): ProjectConfig
{
    return new ProjectConfig(
        'demo',
        'cpanel',
        $path,
        [],
        [
            'provider' => 'github',
            'name' => 'shippercli/demo',
            'url' => 'https://github.com/shippercli/demo.git',
        ],
        $webDirectory,
    );
}

function makeCpanelProfile(array $config = []): ProfileConfig
{
    return new ProfileConfig(
        'production',
        'main',
        \array_merge([
            'domain' => 'cpanel-demo.shippercli.com',
        ], $config),
    );
}

\test('cpanel plan reports fileman deployment when configured', function (): void {
    $provider = new CpanelProvider([
        'host' => 'boundless.herosite.pro',
        'port' => 2083,
        'username' => 'shippercli',
        'password' => 'secret',
        'deployment_method' => 'fileman',
    ]);

    $plan = $provider->plan(
        \makeCpanelProject(),
        \makeCpanelProfile(),
    );

    \expect($plan['deployment_method'])->toBe('fileman');
    \expect($plan['actions'])->toContain('Upload deployment archive via cPanel File Manager');
    \expect($plan['actions'])->toContain('Execute deployment extractor over HTTPS');
});

\test('cpanel derives sensible fileman deploy paths', function (): void {
    $provider = new CpanelProvider([
        'host' => 'boundless.herosite.pro',
        'port' => 2083,
        'username' => 'shippercli',
        'password' => 'secret',
    ]);

    $subdomainPath = \invokeCpanelProviderMethod(
        $provider,
        'getFilemanDeployPath',
        \makeCpanelProfile([
            'domain' => 'cpanel-demo.shippercli.com',
        ]),
        'cpanel-demo.shippercli.com',
    );
    $rootPath = \invokeCpanelProviderMethod(
        $provider,
        'getFilemanDeployPath',
        \makeCpanelProfile([
            'domain' => 'shippercli.com',
        ]),
        'shippercli.com',
    );

    \expect($subdomainPath)->toBe('/cpanel-demo');
    \expect($rootPath)->toBe('/public_html');
});

\test('cpanel builds direct deployment archives without app prefix', function (): void {
    $provider = new CpanelProvider([
        'host' => 'boundless.herosite.pro',
        'port' => 2083,
        'username' => 'shippercli',
        'password' => 'secret',
    ]);

    $sourceDirectory = \sys_get_temp_dir().'/shipper-cpanel-direct-'.\bin2hex(\random_bytes(4));
    $archivePath = \sys_get_temp_dir().'/shipper-cpanel-direct-'.\bin2hex(\random_bytes(4)).'.zip';
    @\mkdir($sourceDirectory, 0700, true);
    \file_put_contents($sourceDirectory.'/index.html', '<h1>Hello</h1>');
    @\mkdir($sourceDirectory.'/assets', 0700, true);
    \file_put_contents($sourceDirectory.'/assets/app.css', 'body{}');

    \invokeCpanelProviderMethod($provider, 'buildDeploymentArchive', $sourceDirectory, $archivePath, 'direct');

    $zip = new ZipArchive;
    $zip->open($archivePath);
    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (\is_string($name)) {
            $entries[] = $name;
        }
    }
    $zip->close();

    \expect($entries)->toContain('index.html');
    \expect($entries)->toContain('assets/app.css');
    \expect($entries)->not->toContain('app/index.html');

    @\unlink($archivePath);
    @\unlink($sourceDirectory.'/assets/app.css');
    @\rmdir($sourceDirectory.'/assets');
    @\unlink($sourceDirectory.'/index.html');
    @\rmdir($sourceDirectory);
});

\test('cpanel builds public-copy deployment archives with app prefix', function (): void {
    $provider = new CpanelProvider([
        'host' => 'boundless.herosite.pro',
        'port' => 2083,
        'username' => 'shippercli',
        'password' => 'secret',
    ]);

    $sourceDirectory = \sys_get_temp_dir().'/shipper-cpanel-app-'.\bin2hex(\random_bytes(4));
    $archivePath = \sys_get_temp_dir().'/shipper-cpanel-app-'.\bin2hex(\random_bytes(4)).'.zip';
    @\mkdir($sourceDirectory.'/public', 0700, true);
    @\mkdir($sourceDirectory.'/bootstrap', 0700, true);
    \file_put_contents($sourceDirectory.'/public/index.php', '<?php echo "hi";');
    \file_put_contents($sourceDirectory.'/bootstrap/app.php', '<?php return [];');

    \invokeCpanelProviderMethod($provider, 'buildDeploymentArchive', $sourceDirectory, $archivePath, 'public_copy');

    $zip = new ZipArchive;
    $zip->open($archivePath);
    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (\is_string($name)) {
            $entries[] = $name;
        }
    }
    $zip->close();

    \expect($entries)->toContain('app/public/index.php');
    \expect($entries)->toContain('app/bootstrap/app.php');

    @\unlink($archivePath);
    @\unlink($sourceDirectory.'/public/index.php');
    @\unlink($sourceDirectory.'/bootstrap/app.php');
    @\rmdir($sourceDirectory.'/public');
    @\rmdir($sourceDirectory.'/bootstrap');
    @\rmdir($sourceDirectory);
});
