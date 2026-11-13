<?php

declare(strict_types=1);

use App\Config\ConfigLoader;

\test('configuration interpolates references once and preserves injected secret bytes', function (string $secret): void {
    $originalSecret = \getenv('SHIPPER_AUDIT_SECRET');
    $originalNested = \getenv('SHIPPER_AUDIT_NESTED');
    $file = \tempnam(\sys_get_temp_dir(), 'shipper-interpolation-');
    \putenv('SHIPPER_AUDIT_SECRET='.$secret);
    \putenv('SHIPPER_AUDIT_NESTED=unexpected-second-expansion');
    \file_put_contents($file, <<<'YAML'
providers:
  coolify:
    api_token: '${SHIPPER_AUDIT_SECRET}'
projects:
  api:
    environment:
      variables:
        TOKEN: '${SHIPPER_AUDIT_SECRET}'
    profiles:
      production:
        environment:
          variables:
            TOKEN: '${SHIPPER_AUDIT_SECRET}'
        coolify:
          api_token: '${SHIPPER_AUDIT_SECRET}'
          preview_environment_variables:
            TOKEN: '${SHIPPER_AUDIT_SECRET}'
          database_options:
            main:
              postgres_password: '${SHIPPER_AUDIT_SECRET}'
          direct: '${SHIPPER_AUDIT_NESTED}'
YAML);
    try {
        $config = (new ConfigLoader($file))->load();
        $project = $config->getProject('api');
        if ($project === null) {
            throw new RuntimeException('Expected fixture project');
        }
        $profile = $project->getProfile('production');
        if ($profile === null) {
            throw new RuntimeException('Expected fixture profile');
        }
        \expect($config->providers())->toBe(['coolify' => ['api_token' => $secret]])
            ->and($project->environment()->variables()['TOKEN'])->toBe($secret)
            ->and($profile->environment()->variables()['TOKEN'])->toBe($secret)
            ->and($profile->config()['coolify'])->toBe([
                'api_token' => $secret,
                'preview_environment_variables' => ['TOKEN' => $secret],
                'database_options' => ['main' => ['postgres_password' => $secret]],
                'direct' => 'unexpected-second-expansion',
            ]);
    } finally {
        \unlink($file);
        \putenv($originalSecret === false ? 'SHIPPER_AUDIT_SECRET' : 'SHIPPER_AUDIT_SECRET='.$originalSecret);
        \putenv($originalNested === false ? 'SHIPPER_AUDIT_NESTED' : 'SHIPPER_AUDIT_NESTED='.$originalNested);
    }
})->with(['literal-${SHIPPER_AUDIT_NESTED}', 'secret-+%:@-${SHIPPER_AUDIT_NESTED}-é', '']);
