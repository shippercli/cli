# Deployer

A Laravel Zero CLI application for declarative, configuration-driven deployments with strict type checking and code quality standards.

## Overview

Deployer is a CLI tool that reads a repository-level config file (`deployer.yml`) and performs plan/apply style deployments through a pluggable provider system. It follows Infrastructure as Code principles similar to Terraform, but for application deployments.

## Key Features

- ✅ **Declarative Configuration** - YAML-based deployment configuration
- ✅ **Plan/Apply Workflow** - Preview changes before deploying
- ✅ **Multiple Environments** - Production, staging, and preview profiles
- ✅ **Pluggable Providers** - Extensible provider system (Ploi supported)
- ✅ **Type Safety** - Strict PHP types throughout the codebase
- ✅ **Code Quality** - PHPStan level 9, Laravel Pint, Pest tests
- ✅ **CI/CD Ready** - GitHub Actions workflows included

## Quick Start

### Installation

```bash
composer install
cp .env.example .env
# Configure your PLOI_API_KEY in .env
```

### Basic Usage

```bash
# Validate your configuration
./deployer validate

# Preview a deployment (dry-run)
./deployer plan api --profile=production

# Execute a deployment
./deployer apply api --profile=production
```

### Example Configuration

Create a `deployer.yml` file in your repository root:

```yaml
providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    api_url: "https://ploi.io/api"
    server_id: "105556"

projects:
  api:
    provider: ploi
    path: ./examples/api
    repository:
      provider: github
      name: ulties/deployer-wip
    profiles:
      production:
        branch: main
        domain: api.example.com
      staging:
        branch: develop
        domain: staging.example.com
```

## Documentation

Comprehensive documentation is available in the [`docs/`](./docs) folder:

- **[Architecture](./docs/architecture.md)** - System design and components
- **[Configuration](./docs/configuration.md)** - Complete configuration guide
- **[Providers](./docs/providers.md)** - Provider system and creating custom providers
- **[GitHub Actions](./docs/github-actions.md)** - CI/CD workflows and automation
- **[Development](./docs/development.md)** - Contributing and development guide
- **[Strict Standards](./docs/strict-standards.md)** - Coding standards and best practices

## Development

```bash
# Run code style checks and fixes
composer format:check  # Check only
composer format        # Fix automatically

# Run static analysis
composer analyse

# Run tests
composer test
```

All code must pass PHPStan level 9, Laravel Pint checks, and Pest tests before merging.

## GitHub Actions

Included workflows:
- **CI** - Code style, static analysis, and tests on every push/PR
- **Production** - Auto-deploy to production on `main` branch
- **Staging** - Auto-deploy to staging on `develop` branch
- **Preview** - Deploy preview environments for pull requests

See [GitHub Actions documentation](./docs/github-actions.md) for details.

## License

MIT