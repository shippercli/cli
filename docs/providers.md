# Provider System

## Overview

The provider system is a pluggable architecture that allows Deployer to integrate with different deployment services. Providers handle the deployment logic for their specific platform while maintaining a consistent interface.

## Architecture

### Provider Interface

All providers must implement the `DeploymentProviderInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;

interface DeploymentProviderInterface
{
    /**
     * Deploy a project with the specified profile
     */
    public function deploy(ProjectConfig $project, ProfileConfig $profile): bool;
    
    /**
     * Validate provider configuration
     */
    public function validate(): bool;
}
```

### Abstract Base Provider

The `AbstractDeploymentProvider` provides common functionality:

```php
<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

abstract class AbstractDeploymentProvider implements DeploymentProviderInterface
{
    public function __construct(
        protected readonly array $config,
    ) {}
    
    /**
     * Get configuration value
     */
    protected function getConfig(string $key, string|null $default = null): string|null
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Validate required configuration keys exist
     */
    protected function validateRequiredConfig(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!isset($this->config[$key])) {
                return false;
            }
        }
        
        return true;
    }
}
```

## Current Providers

### Ploi Provider

The Ploi provider integrates with [Ploi.io](https://ploi.io) for server and site management.

**Class**: `App\Providers\Deployment\PloiProvider`

**Configuration**:
```yaml
providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    api_url: "https://ploi.io/api"
    server_id: "105556"
```

**Features**:
- Automatic site creation and management
- Repository configuration
- Branch-based deployments
- Zero-downtime deployments

**Implementation Example**:
```php
<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;

final class PloiProvider extends AbstractDeploymentProvider
{
    public function deploy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        // 1. Find or create site
        $site = $this->findOrCreateSite($profile->domain);
        
        // 2. Configure repository if needed
        if (!$site->hasRepository()) {
            $this->configureRepository($site, $project);
        }
        
        // 3. Trigger deployment
        return $this->triggerDeployment($site, $profile->branch);
    }
    
    public function validate(): bool
    {
        return $this->validateRequiredConfig([
            'api_key',
            'api_url',
            'server_id',
        ]);
    }
    
    private function findOrCreateSite(string $domain): Site
    {
        // Implementation
    }
    
    private function configureRepository(Site $site, ProjectConfig $project): void
    {
        // Implementation
    }
    
    private function triggerDeployment(Site $site, string $branch): bool
    {
        // Implementation
    }
}
```

## Creating a New Provider

### Step 1: Create Provider Class

Create a new file in `app/Providers/Deployment/`:

```php
<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;

final class YourProvider extends AbstractDeploymentProvider
{
    public function deploy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        // Your deployment logic
        
        return true;  // or false on failure
    }
    
    public function validate(): bool
    {
        return $this->validateRequiredConfig([
            'api_key',
            'endpoint',
            // ... other required config
        ]);
    }
}
```

### Step 2: Register Provider

Update `ProviderFactory::create()` to register your provider:

```php
<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

final class ProviderFactory
{
    public static function create(string $name, array $config): DeploymentProviderInterface
    {
        return match ($name) {
            'ploi' => new PloiProvider($config),
            'your_provider' => new YourProvider($config),  // Add your provider
            default => throw new \InvalidArgumentException("Provider {$name} not found"),
        };
    }
}
```

### Step 3: Configure in YAML

Add your provider configuration to `deployer.yml`:

```yaml
providers:
  your_provider:
    api_key: "${YOUR_API_KEY}"
    endpoint: "https://api.example.com"
    # ... other configuration

projects:
  my_project:
    provider: your_provider  # Use your provider
    # ... rest of project config
```

### Step 4: Write Tests

Create tests for your provider:

```php
<?php

declare(strict_types=1);

use App\Providers\Deployment\YourProvider;

it('deploys successfully', function (): void {
    $config = [
        'api_key' => 'test-key',
        'endpoint' => 'https://api.example.com',
    ];
    
    $provider = new YourProvider($config);
    
    expect($provider->validate())->toBeTrue();
});

it('validates required configuration', function (): void {
    $provider = new YourProvider([]);
    
    expect($provider->validate())->toBeFalse();
});
```

## Provider Best Practices

### 1. Use Strict Types

Always declare strict types:

```php
<?php

declare(strict_types=1);
```

### 2. Type All Parameters

```php
public function deploy(ProjectConfig $project, ProfileConfig $profile): bool
{
    // Implementation
}
```

### 3. Make Classes Final

```php
final class YourProvider extends AbstractDeploymentProvider
{
    // Implementation
}
```

### 4. Handle Errors Gracefully

```php
public function deploy(ProjectConfig $project, ProfileConfig $profile): bool
{
    try {
        // Deployment logic
        return true;
    } catch (\Exception $e) {
        $this->logError($e->getMessage());
        return false;
    }
}
```

### 5. Validate Configuration Early

```php
public function validate(): bool
{
    if (!$this->validateRequiredConfig(['api_key'])) {
        return false;
    }
    
    // Additional validation
    if (empty($this->getConfig('api_key'))) {
        return false;
    }
    
    return true;
}
```

### 6. Use Dependency Injection

Inject HTTP clients and other dependencies:

```php
public function __construct(
    array $config,
    private readonly HttpClientInterface $client = new HttpClient(),
) {
    parent::__construct($config);
}
```

### 7. Implement Idempotency

Multiple deployments should be safe:

```php
public function deploy(ProjectConfig $project, ProfileConfig $profile): bool
{
    // Check if already deployed
    if ($this->isAlreadyDeployed($profile)) {
        return true;  // Already in desired state
    }
    
    // Perform deployment
    return $this->performDeployment($project, $profile);
}
```

### 8. Provide Clear Feedback

Log what's happening:

```php
public function deploy(ProjectConfig $project, ProfileConfig $profile): bool
{
    $this->info("Finding site for domain: {$profile->domain}");
    $site = $this->findOrCreateSite($profile->domain);
    
    $this->info("Configuring repository: {$project->repository->name}");
    $this->configureRepository($site, $project);
    
    $this->info("Deploying branch: {$profile->branch}");
    return $this->triggerDeployment($site, $profile->branch);
}
```

## Provider Examples

### Example: DigitalOcean App Platform

```php
<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;

final class DigitalOceanProvider extends AbstractDeploymentProvider
{
    public function deploy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $appId = $this->findOrCreateApp($project, $profile);
        
        $this->updateAppSpec($appId, $project, $profile);
        
        return $this->triggerDeployment($appId);
    }
    
    public function validate(): bool
    {
        return $this->validateRequiredConfig([
            'api_token',
        ]);
    }
    
    private function findOrCreateApp(ProjectConfig $project, ProfileConfig $profile): string
    {
        $apps = $this->apiCall('GET', '/v2/apps');
        
        foreach ($apps as $app) {
            if ($app['name'] === $this->getAppName($profile)) {
                return $app['id'];
            }
        }
        
        // Create new app
        $response = $this->apiCall('POST', '/v2/apps', [
            'spec' => $this->buildAppSpec($project, $profile),
        ]);
        
        return $response['app']['id'];
    }
    
    private function buildAppSpec(ProjectConfig $project, ProfileConfig $profile): array
    {
        return [
            'name' => $this->getAppName($profile),
            'region' => $this->getConfig('region', 'nyc'),
            'services' => [
                [
                    'name' => 'web',
                    'github' => [
                        'repo' => $project->repository->name,
                        'branch' => $profile->branch,
                    ],
                ],
            ],
        ];
    }
    
    private function getAppName(ProfileConfig $profile): string
    {
        return str_replace('.', '-', $profile->domain);
    }
    
    private function apiCall(string $method, string $endpoint, array $data = []): array
    {
        // HTTP client implementation
        return [];
    }
}
```

**Configuration**:
```yaml
providers:
  digitalocean:
    api_token: "${DO_API_TOKEN}"
    region: "nyc"

projects:
  my_app:
    provider: digitalocean
    # ... rest of config
```

### Example: Custom SSH Deployment

```php
<?php

declare(strict_types=1);

namespace App\Providers\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;

final class SshProvider extends AbstractDeploymentProvider
{
    public function deploy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $connection = $this->connect();
        
        $this->pullLatestCode($connection, $profile->branch);
        $this->installDependencies($connection);
        $this->runMigrations($connection);
        $this->restartServices($connection);
        
        return true;
    }
    
    public function validate(): bool
    {
        return $this->validateRequiredConfig([
            'host',
            'user',
            'key_path',
            'deploy_path',
        ]);
    }
    
    private function connect(): SshConnection
    {
        return new SshConnection(
            host: $this->getConfig('host'),
            user: $this->getConfig('user'),
            keyPath: $this->getConfig('key_path'),
        );
    }
    
    private function pullLatestCode(SshConnection $connection, string $branch): void
    {
        $deployPath = $this->getConfig('deploy_path');
        
        $connection->exec("cd {$deployPath} && git fetch origin");
        $connection->exec("cd {$deployPath} && git checkout {$branch}");
        $connection->exec("cd {$deployPath} && git pull origin {$branch}");
    }
    
    private function installDependencies(SshConnection $connection): void
    {
        $deployPath = $this->getConfig('deploy_path');
        $connection->exec("cd {$deployPath} && composer install --no-dev");
    }
    
    private function runMigrations(SshConnection $connection): void
    {
        $deployPath = $this->getConfig('deploy_path');
        $connection->exec("cd {$deployPath} && php artisan migrate --force");
    }
    
    private function restartServices(SshConnection $connection): void
    {
        $connection->exec("sudo systemctl restart php-fpm");
        $connection->exec("sudo systemctl restart nginx");
    }
}
```

**Configuration**:
```yaml
providers:
  ssh:
    host: "deploy.example.com"
    user: "deployer"
    key_path: "${HOME}/.ssh/deploy_key"
    deploy_path: "/var/www/app"

projects:
  my_app:
    provider: ssh
    # ... rest of config
```

## Testing Providers

### Unit Tests

Test provider logic in isolation:

```php
<?php

declare(strict_types=1);

use App\Providers\Deployment\YourProvider;

it('validates configuration correctly', function (): void {
    $validConfig = ['api_key' => 'test-key'];
    $provider = new YourProvider($validConfig);
    
    expect($provider->validate())->toBeTrue();
});

it('rejects invalid configuration', function (): void {
    $invalidConfig = [];
    $provider = new YourProvider($invalidConfig);
    
    expect($provider->validate())->toBeFalse();
});
```

### Integration Tests

Test with mocked API responses:

```php
<?php

declare(strict_types=1);

use App\Providers\Deployment\YourProvider;

it('deploys successfully with valid credentials', function (): void {
    $mockClient = Mockery::mock('HttpClient');
    $mockClient->shouldReceive('post')
        ->once()
        ->with('/deploy', Mockery::any())
        ->andReturn(['status' => 'success']);
    
    $provider = new YourProvider(['api_key' => 'test'], $mockClient);
    
    expect($provider->deploy($project, $profile))->toBeTrue();
});
```

## Provider Checklist

When creating a new provider, ensure:

- [ ] Implements `DeploymentProviderInterface`
- [ ] Extends `AbstractDeploymentProvider`
- [ ] Uses `declare(strict_types=1)`
- [ ] Class is `final`
- [ ] All methods have type hints
- [ ] Configuration validation implemented
- [ ] Error handling is robust
- [ ] Unit tests written
- [ ] Integration tests written
- [ ] Documentation added to this file
- [ ] Registered in `ProviderFactory`
- [ ] Example configuration provided

## Troubleshooting

### Provider Not Found

**Error**: `Provider 'xyz' not found`

**Solution**: Ensure provider is registered in `ProviderFactory::create()`

### Invalid Configuration

**Error**: `Configuration validation failed`

**Solution**: Check `validate()` method and required config keys

### API Authentication Failure

**Error**: `401 Unauthorized`

**Solution**: 
1. Verify API credentials are correct
2. Check environment variables are set
3. Ensure API key has necessary permissions

### Deployment Timeout

**Error**: `Deployment timed out`

**Solution**:
1. Increase timeout in provider implementation
2. Check network connectivity
3. Verify deployment service is operational

## Next Steps

- Review [architecture documentation](./architecture.md)
- Learn about [configuration options](./configuration.md)
- Read [development guide](./development.md)
- Study [strict coding standards](./strict-standards.md)
