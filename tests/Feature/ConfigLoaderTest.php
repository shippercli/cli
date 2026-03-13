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

/** @phpstan-ignore method.notFound */
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
