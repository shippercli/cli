<?php

declare(strict_types=1);

use App\Config\ConfigLoader;

\test('config loader loads valid config', function (): void {
    $loader = new ConfigLoader('deployer.yml');
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
    $loader = new ConfigLoader('deployer.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \expect($project->name())->toBe('api');
    \expect($project->provider())->toBe('ploi');
    \expect($project->path())->toBe('./examples/api');
});

\test('loaded project has profiles', function (): void {
    $loader = new ConfigLoader('deployer.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \expect($project->profiles())->toBeArray();
    \expect($project->profiles())->toHaveKey('production');
    \expect($project->profiles())->toHaveKey('staging');
    \expect($project->profiles())->toHaveKey('preview');
});

\test('loaded profile has expected properties', function (): void {
    $loader = new ConfigLoader('deployer.yml');
    $config = $loader->load();
    $project = $config->getProject('api');
    $profile = $project?->getProfile('production');

    \expect($profile)->not->toBeNull();
    \expect($profile->name())->toBe('production');
    \expect($profile->branch())->toBe('main');
});

\test('loaded project has databases', function (): void {
    $loader = new ConfigLoader('deployer.yml');
    $config = $loader->load();
    $project = $config->getProject('api');

    \expect($project)->not->toBeNull();
    \expect($project->databases())->toBeArray();
    \expect($project->databases())->toHaveKey('main');
});

\test('loaded database has expected properties', function (): void {
    $loader = new ConfigLoader('deployer.yml');
    $config = $loader->load();
    $project = $config->getProject('api');
    $database = $project?->getDatabase('main');

    \expect($database)->not->toBeNull();
    \expect($database->name())->toBe('${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}');
    \expect($database->user())->toBe('${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}');
    \expect($database->type())->toBe('mysql');
});
