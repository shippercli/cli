<?php

declare(strict_types=1);

use App\Deployment\PloiProvider;

\test('isFailureLog detects log ending with "failed"', function (): void {
    $provider = new PloiProvider([]);

    \expect($provider->isFailureLog('Clone Git repository failed'))->toBeTrue();
    \expect($provider->isFailureLog('Install virtual host failed'))->toBeTrue();
    \expect($provider->isFailureLog('Script execution failed'))->toBeTrue();
    \expect($provider->isFailureLog('CLONE GIT REPOSITORY FAILED'))->toBeTrue(); // case-insensitive
});

\test('isFailureLog detects deployment failure keyword', function (): void {
    $provider = new PloiProvider([]);

    \expect($provider->isFailureLog('deployment failure occurred'))->toBeTrue();
});

\test('isFailureLog detects fatal and critical errors', function (): void {
    $provider = new PloiProvider([]);

    \expect($provider->isFailureLog('fatal error: out of memory'))->toBeTrue();
    \expect($provider->isFailureLog('critical error in deployment script'))->toBeTrue();
});

\test('isFailureLog does not flag successful log entries', function (): void {
    $provider = new PloiProvider([]);

    \expect($provider->isFailureLog('Pull git changes'))->toBeFalse();
    \expect($provider->isFailureLog('Install virtual host shipper-cli-api-preview-74.ulties.dev'))->toBeFalse();
    \expect($provider->isFailureLog('Clone Git repository'))->toBeFalse();
    \expect($provider->isFailureLog('Deployment started'))->toBeFalse();
    \expect($provider->isFailureLog('Running deployment script'))->toBeFalse();
});
