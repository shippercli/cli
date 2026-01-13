# Shipper

A Laravel Zero application for declarative, config-driven deployments with strict type checking and code quality standards.

## Overview

Shipper is a CLI tool that reads a repository-level config file (`shipper.yml`) and performs plan/apply style deployments through a pluggable provider system. It follows the same philosophy as Infrastructure as Code tools like Terraform, but for application deployments.

## Features

### Deployment Features
- ✅ Declarative YAML configuration (`shipper.yml`)
- ✅ Multiple projects and deployment profiles (production, staging, preview)
- ✅ Pluggable provider system (currently supports Ploi)
- ✅ Plan/apply workflow for safe deployments
- ✅ Configuration validation
- ✅ GitHub Actions workflows for CI/CD
- ✅ Database configuration and automatic provisioning
- ✅ Database lifecycle management (create, link, destroy)

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

Create a `shipper.yml` file in your repository root:

```yaml
providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    api_url: "https://ploi.io/api"
    server_id: "105556"  # Your Ploi server ID
    deployment_timeout: 60  # Maximum time (in seconds) to wait for deployment (default: 60)

projects:
  api:
    provider: ploi
    path: ./examples/api
    # Repository configuration
    repository:
      provider: github  # github, gitlab, bitbucket, or custom
      name: ulties/shipper-wip  # username/repository
    # Site configuration
    web_directory: /public  # Default Laravel public directory
    project_root: /  # Root of the project
    # Database configuration
    databases:
      main:
        name: "shipper_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
        user: "shipper_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
        type: mysql
    profiles:
      production:
        branch: main
        domain: shipper-wip-api.ulties.dev
      staging:
        branch: develop
        domain: shipper-wip-api-test.ulties.dev
      preview:
        branch: "${GITHUB_HEAD_REF}"
        domain: "shipper-wip-api-preview-${GITHUB_PR_NUMBER}.ulties.dev"
```

**Configuration Notes:**
- The `server_id` is configured once at the provider level
- The `deployment_timeout` specifies how long to wait for deployment completion (default: 60 seconds)
- Deployment status is polled every 5 seconds until completion or timeout
- Sites are automatically created/found by domain name
- Repository is automatically installed from GitHub/GitLab/Bitbucket when a new site is created
- `web_directory` defaults to `/public` (Laravel standard)
- `project_root` defaults to `/` (project root)
- No need to manually manage site IDs - the shipper handles this automatically
- Domains use subdomains of ulties.dev for different environments

**Database Configuration:**
- Databases are automatically created/found by name
- Database names and users support variable interpolation:
  - `${PROJECT_NAME}` - The project name from config (e.g., "api")
  - `${PROFILE}` - The deployment profile (e.g., "production", "staging", "preview")
  - Any environment variable (e.g., `${GITHUB_PR_NUMBER}` for PR-specific databases)
- Environment variables that are not set will be treated as empty strings
- Trailing underscores and multiple consecutive underscores are automatically cleaned up
- Each database is created with a secure random password
- Databases are linked to their respective sites
- When a site is destroyed, its associated databases are also deleted
- Examples:
  - For project "api" with profile "production", using pattern `shipper_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}`, the database name will be "shipper_api_production"
  - For project "api" with profile "preview" and PR #123, the database name will be "shipper_api_preview_123"

### CLI Commands

```bash
# Validate configuration
./shipper validate

# Plan a deployment (dry-run)
./shipper plan api --profile=production

# Execute a deployment
./shipper apply api --profile=production

# Execute with force (skip confirmation)
./shipper apply api --profile=production --force

# List all commands
./shipper list
```

### Provider System

The shipper uses a pluggable provider system. Currently supported:

- **Ploi**: Deploy to servers managed by Ploi.io

To add a new provider, implement the `DeploymentProviderInterface` and register it in `ProviderFactory`.

## Project Structure

```
shipper/
├── app/
│   ├── Commands/           # CLI commands
│   │   ├── ValidateCommand.php
│   │   ├── PlanCommand.php
│   │   └── ApplyCommand.php
│   ├── Config/             # Configuration classes
│   │   ├── ConfigLoader.php
│   │   ├── ShipperConfig.php
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
├── shipper.yml            # Main configuration file
└── shipper                # CLI entry point
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