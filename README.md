# Deployer

A Laravel Zero application for declarative, config-driven deployments with strict type checking and code quality standards.

## Overview

Deployer is a CLI tool that reads a repository-level config file (`deployer.yml`) and performs plan/apply style deployments through a pluggable provider system. It follows the same philosophy as Infrastructure as Code tools like Terraform, but for application deployments.

## Features

### Deployment Features
- ✅ Declarative YAML configuration (`deployer.yml`)
- ✅ Multiple projects and deployment profiles (production, staging, preview)
- ✅ Pluggable provider system (currently supports Ploi)
- ✅ Plan/apply workflow for safe deployments
- ✅ Configuration validation
- ✅ GitHub Actions workflows for CI/CD

### Strict Type Enforcement
- ✅ `declare(strict_types=1)` in all PHP files
- ✅ Type hints on all method parameters and return types
- ✅ Final classes by default (immutability)
- ✅ No mixed types allowed
- ✅ Strict comparison operators

### Code Quality Tools

#### PHPStan (Level 9)
Configured with maximum strictness:
- No mixed types
- All properties must have type declarations
- Checks for always-true conditions
- Validates return types in protected and public methods
- Reports uninitialized properties
- Dynamic properties disabled

#### Laravel Pint
Code style enforcement with:
- Strict type declarations
- Strict comparison
- Native function invocation optimization
- Ordered imports
- Final class enforcement
- No superfluous PHPDoc tags

#### Pest Testing
Modern testing with:
- Type-safe test cases
- Feature and unit testing support
- Integration with Laravel Zero

## Installation

```bash
composer install
cp .env.example .env
```

## Usage

### Configuration

Create a `deployer.yml` file in your repository root:

```yaml
providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    api_url: "https://ploi.io/api"

projects:
  api:
    provider: ploi
    path: ./examples/api
    profiles:
      production:
        branch: main
        server_id: "${PLOI_SERVER_ID_PROD}"
        site_id: "${PLOI_SITE_ID_API_PROD}"
      staging:
        branch: develop
        server_id: "${PLOI_SERVER_ID_STAGE}"
        site_id: "${PLOI_SITE_ID_API_STAGE}"
```

### CLI Commands

```bash
# Validate configuration
./deployer validate

# Plan a deployment (dry-run)
./deployer plan api --profile=production

# Execute a deployment
./deployer apply api --profile=production

# Execute with force (skip confirmation)
./deployer apply api --profile=production --force

# List all commands
./deployer list
```

### Provider System

The deployer uses a pluggable provider system. Currently supported:

- **Ploi**: Deploy to servers managed by Ploi.io

To add a new provider, implement the `DeploymentProviderInterface` and register it in `ProviderFactory`.

## Project Structure

```
deployer/
├── app/
│   ├── Commands/           # CLI commands
│   │   ├── ValidateCommand.php
│   │   ├── PlanCommand.php
│   │   └── ApplyCommand.php
│   ├── Config/             # Configuration classes
│   │   ├── ConfigLoader.php
│   │   ├── DeployerConfig.php
│   │   ├── ProjectConfig.php
│   │   └── ProfileConfig.php
│   └── Providers/
│       └── Deployment/     # Deployment providers
│           ├── DeploymentProviderInterface.php
│           ├── AbstractDeploymentProvider.php
│           ├── PloiProvider.php
│           └── ProviderFactory.php
├── examples/               # Example deployable projects
│   ├── api/
│   └── frontend/
├── .github/workflows/      # CI/CD workflows
│   ├── ci.yml              # Code quality checks
│   ├── deploy-production.yml
│   ├── deploy-staging.yml
│   └── deploy-preview.yml
├── deployer.yml            # Main configuration file
└── deployer                # CLI entry point
```

## GitHub Actions

Three deployment workflows are included:

### Production (main branch)
Deploys all projects to production when code is pushed to `main`.

### Staging (develop branch)
Deploys all projects to staging when code is pushed to `develop`.

### Preview (pull requests)
Deploys preview environments for pull requests and comments on the PR with deployment status.

## Development

```bash
# Run code style checks
composer format:check

# Fix code style
composer format

# Run static analysis
composer analyse

# Run tests
composer test
```

## Continuous Integration

GitHub Actions automatically runs:
1. Code style validation (Pint)
2. Static analysis (PHPStan level 9)
3. Tests (Pest)

All checks must pass before merging.

## Strict Rules Applied

1. **Type Safety**: Every method has explicit parameter and return types
2. **Immutability**: Classes are final by default
3. **Strict Comparisons**: Using `===` and `!==` operators
4. **No Mixed Types**: Explicit types required everywhere
5. **Property Types**: All properties must declare types
6. **PHPStan Level 9**: Maximum static analysis strictness
7. **Code Style**: Enforced via Pint with strict rules

## License

MIT