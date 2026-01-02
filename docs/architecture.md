# Architecture

## Overview

Deployer is a Laravel Zero CLI application built with a plugin-based architecture that allows declarative, configuration-driven deployments. It follows Infrastructure as Code (IaC) principles similar to Terraform, with a plan/apply workflow.

## Core Components

### 1. Command Layer

Located in `app/Commands/`, the command layer provides the CLI interface for users.

#### Main Commands

- **ValidateCommand**: Validates the `deployer.yml` configuration file
- **PlanCommand**: Shows what changes would be made (dry-run)
- **ApplyCommand**: Executes the deployment plan
- **DeployCommand**: Legacy command (deprecated in favor of plan/apply workflow)

#### Command Concerns

- **FormatsDeploymentPlan**: Shared trait for formatting deployment plan output consistently

### 2. Configuration Layer

Located in `app/Config/`, this layer handles loading and parsing the `deployer.yml` configuration.

#### Configuration Classes

- **ConfigLoader**: Loads and parses the YAML configuration file
- **DeployerConfig**: Root configuration object containing providers and projects
- **ProjectConfig**: Represents a single deployable project with its profiles
- **ProfileConfig**: Represents a deployment profile (production, staging, preview)

#### Configuration Flow

1. `ConfigLoader::load()` reads `deployer.yml`
2. Environment variables in format `${VAR_NAME}` are interpolated
3. Configuration is validated and parsed into typed objects
4. Configuration objects are passed to commands for execution

### 3. Provider Layer

Located in `app/Providers/Deployment/`, this layer implements the deployment provider pattern.

#### Provider Architecture

```
DeploymentProviderInterface (contract)
         ↑
         |
AbstractDeploymentProvider (shared functionality)
         ↑
         |
    PloiProvider (concrete implementation)
```

#### Provider Responsibilities

- **Authentication**: Handle API authentication with deployment services
- **Site Management**: Create, find, and configure deployment sites
- **Deployment**: Trigger and monitor deployments
- **Repository Integration**: Configure Git repository connections

#### Current Providers

- **PloiProvider**: Integrates with Ploi.io for server and site management

### 4. Provider Factory

The `ProviderFactory` creates provider instances based on configuration:

```php
$provider = ProviderFactory::create('ploi', $config);
```

This allows easy addition of new providers (AWS, DigitalOcean, custom solutions) without modifying existing code.

## Data Flow

### Validation Flow

```
User runs: ./deployer validate
    ↓
ValidateCommand
    ↓
ConfigLoader::load()
    ↓
Parse and validate YAML
    ↓
Return success/failure
```

### Deployment Flow

```
User runs: ./deployer apply api --profile=production
    ↓
ApplyCommand
    ↓
ConfigLoader::load()
    ↓
Get project config for "api"
    ↓
Get profile config for "production"
    ↓
ProviderFactory::create()
    ↓
Provider->deploy()
    ↓
  - Find/create site
  - Configure repository
  - Trigger deployment
  - Return status
```

## Design Patterns

### 1. Factory Pattern

Used in `ProviderFactory` to create provider instances without coupling commands to specific implementations.

### 2. Strategy Pattern

The provider interface allows swapping deployment strategies (Ploi, custom, etc.) without changing the command layer.

### 3. Configuration as Code

The `deployer.yml` file is the single source of truth, enabling version control and reproducible deployments.

### 4. Type Safety

All classes use strict types and are final by default (see [strict-standards.md](./strict-standards.md)).

## Extension Points

### Adding a New Provider

1. Create a new class implementing `DeploymentProviderInterface`
2. Extend `AbstractDeploymentProvider` for common functionality
3. Register the provider in `ProviderFactory::create()`
4. Add provider-specific configuration to `deployer.yml`

Example:

```php
final class DigitalOceanProvider extends AbstractDeploymentProvider
{
    public function deploy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        // Implementation
    }
}
```

### Adding a New Command

1. Create a command class extending Laravel Zero's `Command`
2. Inject `ConfigLoader` to access configuration
3. Use `ProviderFactory` to get the appropriate provider
4. Follow strict type standards

## Directory Structure

```
app/
├── Commands/              # CLI command implementations
│   ├── ApplyCommand.php   # Execute deployments
│   ├── PlanCommand.php    # Preview deployments
│   ├── ValidateCommand.php # Validate configuration
│   └── Concerns/          # Shared command functionality
├── Config/                # Configuration loading and parsing
│   ├── ConfigLoader.php   # YAML loader with env interpolation
│   ├── DeployerConfig.php # Root config object
│   ├── ProjectConfig.php  # Project-level config
│   └── ProfileConfig.php  # Profile-level config
└── Providers/
    └── Deployment/        # Deployment provider implementations
        ├── DeploymentProviderInterface.php
        ├── AbstractDeploymentProvider.php
        ├── PloiProvider.php
        └── ProviderFactory.php
```

## Testing Architecture

Tests are organized into:

- **Feature Tests**: End-to-end command testing
- **Unit Tests**: Individual class and method testing

See [development.md](./development.md) for testing guidelines.

## Security Considerations

1. **Credential Management**: API keys and secrets stored in environment variables
2. **Type Safety**: Strict types prevent type confusion vulnerabilities
3. **Validation**: Configuration is validated before execution
4. **Immutability**: Final classes prevent unexpected modifications

## Performance Considerations

1. **Lazy Loading**: Providers are instantiated only when needed
2. **Configuration Caching**: YAML is parsed once per command execution
3. **Native Functions**: Optimized function calls (see strict-standards.md)

## Future Architecture

Potential enhancements:

- **Plugin System**: Load providers from separate packages
- **Event System**: Hook into deployment lifecycle events
- **State Management**: Track deployment history and rollbacks
- **Multi-Provider**: Deploy to multiple providers simultaneously
- **Health Checks**: Verify deployment success with automated testing
