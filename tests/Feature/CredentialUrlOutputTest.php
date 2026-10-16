<?php

declare(strict_types=1);

use App\Commands\ShipperCommand;
use App\Deployment\ProviderRegistry;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\TestCredentialUrlProvider;

\test('URL credentials are hidden in public plan logs status and failure output', function (string $command, int $expected): void {
    ProviderRegistry::register('credential-url-test', TestCredentialUrlProvider::class);
    $config = \tempnam(\sys_get_temp_dir(), 'shipper-url-output-');
    \assert(\is_string($config));
    \file_put_contents($config, \json_encode([
        'providers' => ['credential-url-test' => ['url' => TestCredentialUrlProvider::URL, 'validation_error' => $command === 'validate']],
        'projects' => ['api' => ['provider' => 'credential-url-test', 'path' => '.', 'profiles' => ['production' => ['branch' => 'main']]]],
    ], JSON_THROW_ON_ERROR));
    try {
        $arguments = ['--config' => $config];
        if ($command !== 'validate') {
            $arguments['project'] = 'api';
        }
        if (\in_array($command, ['apply', 'destroy', 'rollback'], true)) {
            $arguments['--force'] = true;
        }
        \expect(Artisan::call($command, $arguments))->toBe($expected);
        $output = Artisan::output();
        \expect($output)->toContain('https://[redacted]@example.test/repo.git')
            ->and($output)->not->toContain('fixture-user', 'fixture-secret');
        if ($command === 'apply') {
            \expect(TestCredentialUrlProvider::$receivedUrl)->toBe(TestCredentialUrlProvider::URL);
        }
    } finally {
        @\unlink($config);
    }
})->with([['plan', 0], ['logs', 0], ['status', 0], ['apply', 1], ['destroy', 1], ['rollback', 1], ['validate', 1]]);

\test('URL redaction preserves host paths and ordinary SSH addresses', function (): void {
    $command = new class extends ShipperCommand {};
    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
    $command->line('HTTP://encoded%40user:p%40ss@example.test/path and https://token@example.test/next');
    $command->line('https://example.test/path git@example.test:org/repo.git');
    \expect($output->fetch())->toBe("HTTP://[redacted]@example.test/path and https://[redacted]@example.test/next\nhttps://example.test/path git@example.test:org/repo.git\n");
});

\test('a redaction failure never falls back to printing raw credentials', function (): void {
    $command = new class extends ShipperCommand {};
    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
    $limit = \ini_get('pcre.backtrack_limit');
    \ini_set('pcre.backtrack_limit', '0');
    try {
        $command->line(TestCredentialUrlProvider::URL);
    } finally {
        \ini_set('pcre.backtrack_limit', $limit === false ? '1000000' : $limit);
    }
    $text = $output->fetch();
    \expect($text)->toContain('Output withheld:')
        ->and(\str_contains($text, 'fixture-secret'))->toBeFalse();
});
