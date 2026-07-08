<?php

declare(strict_types=1);

use App\Config\ConfigLoader;

test('config loader parses existing server lifecycle config from profile', function (): void {
    $configFile = tempnam(sys_get_temp_dir(), 'shipper-server-existing-');
    expect($configFile)->not->toBeFalse();
    assert(is_string($configFile));

    file_put_contents($configFile, <<<'YAML'
projects:
  api:
    provider: ploi
    path: ./api
    repository:
      provider: github
      name: ulties/shipper
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
    assert($project !== null);
    $profile = $project->getProfile('production');
    assert($profile !== null);
    $server = $profile->server();

    expect($server)->not->toBeNull();
    assert($server !== null);
    expect($server->mode())->toBe('existing');
    expect($server->id())->toBe('123456');
    expect($server->cleanup())->toBeNull();
});

test('config loader parses create server lifecycle config from profile', function (): void {
    $configFile = tempnam(sys_get_temp_dir(), 'shipper-server-create-');
    expect($configFile)->not->toBeFalse();
    assert(is_string($configFile));

    file_put_contents($configFile, <<<'YAML'
projects:
  api:
    provider: ploi
    path: ./api
    repository:
      provider: github
      name: ulties/shipper
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
    assert($project !== null);
    $profile = $project->getProfile('preview');
    assert($profile !== null);
    $server = $profile->server();

    expect($server)->not->toBeNull();
    assert($server !== null);
    expect($server->mode())->toBe('create');
    expect($server->cleanup())->toBe('destroy');
    expect($server->ttl())->toBe('72h');
    expect($server->spec())->toMatchArray([
        'name' => 'api-pr-123',
        'region' => 'eu-west',
        'size' => 'small',
    ]);
});
