# Shipper CLI

[![CI](https://github.com/shippercli/cli/actions/workflows/ci.yml/badge.svg)](https://github.com/shippercli/cli/actions)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen)](https://phpstan.org/)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-blue)](LICENSE)

Declarative application deployments through a built-in provider and Composer
provider plugins.

Shipper reads `shipper.yml`, validates the selected project and profile, shows
the planned operations, and delegates deployment work to the installed
provider package.

## Install

Install globally with Composer:

### Deployment Features
- ✅ Declarative YAML configuration (`shipper.yml`)
- ✅ Multiple projects and deployment profiles (production, staging, preview)
- ✅ Composer-discovered provider plugins through `shippercli/contracts`
- ✅ Plan/apply workflow for safe deployments
- ✅ Configuration validation
- ✅ GitHub Actions workflows for CI/CD
- ✅ Database configuration and automatic provisioning
- ✅ Database lifecycle management (create, link, destroy)
```bash
composer global require shippercli/cli
```

Or work from source:

```bash
composer install
./shipper list
```

Prebuilt PHAR binaries are published on the
[releases page](https://github.com/shippercli/cli/releases).

## Providers

Provider availability and lifecycle support currently differ by distribution:

| Provider | Distribution | Registered | Validate / plan | Apply | Destroy | Status / logs / rollback |
| --- | --- | --- | --- | --- | --- | --- |
| Ploi | `shippercli/provider-ploi` Composer plugin | When installed | Yes | Yes | Yes | Status and logs; no rollback |
| cPanel | `shippercli/provider-cpanel` Composer plugin | When installed | Yes | Yes | Yes | Yes |
| Forge, Railway, Cloudflare Pages, Hostinger, Coolify, EasyPanel, Portainer | Experimental source only | No | Experimental | Unavailable | Unavailable | No |

Incomplete in-tree providers are deliberately not registered. Calling an
unimplemented lifecycle operation directly returns failure with a clear
`not implemented` error rather than reporting a successful deployment.

For local global use, install the CLI and providers into the same Composer
home, then run Composer's global `vendor/bin/shipper`:

```bash
composer global require shippercli/cli shippercli/provider-ploi shippercli/provider-cpanel
```

Shipper discovers packages with Composer's runtime plugin metadata. Provider
credentials, platform features, and configuration options belong to each
provider's documentation rather than the core CLI.

The release PHAR has its own dependencies and cannot discover separately
installed provider packages. For CI, use
`shippercli/actions/.github/actions/shipper@c2c276e12f831ba2c3377a063d579fede5cc5ecc`, which installs the CLI and
providers together in an isolated Composer directory.

## Configure

Create `shipper.yml` in the application repository:

```yaml
providers:
  provider_name:
    api_token: "${PROVIDER_API_TOKEN}"

projects:
  backend:
    provider: provider_name
    path: "."
    web_directory: /public
    profiles:
      production:
        branch: main
        domain: "api.example.com"
      staging:
        branch: develop
        domain: "api.example-test.com"
```

`provider_name` is the slug registered by the installed package. Profiles can
override provider-supported runtime, database, domain, environment, and
lifecycle options.

Environment placeholders use `${NAME}` syntax and are resolved at runtime.
Keep credentials in the shell, CI secrets, or another secret manager.

## Commands

```bash
# Validate all configured projects and profiles.
shipper validate

# Preview provider operations without changing remote state.
shipper plan backend --profile=production

# Apply a deployment. --force skips the confirmation prompt.
shipper apply backend --profile=production --force

# Inspect provider-defined deployment and resource state.
shipper status backend --profile=production

# Read recent provider or application log lines.
shipper logs backend --profile=production --lines=100

# Restore the latest or a named provider-managed release.
shipper rollback backend --profile=production
shipper rollback backend --profile=production --release=release-id

# Remove only resources the provider identifies as Shipper-managed.
shipper destroy backend --profile=preview --force
```

`status`, `logs`, and `rollback` are optional provider capabilities. Shipper
returns a clear error when the selected provider does not implement one.

## Provider contracts

Every provider implements validation, planning, apply, destroy, name, and
error-reporting methods from `shippercli/contracts`.

Optional contracts add:

- deployment status
- recent logs
- release rollback
- server provisioning and cleanup

The CLI adapts installed contract providers to its internal flows. New
providers are Composer plugins and are discovered from their package metadata.

## GitHub Actions

A minimal deployment workflow installs Shipper and its provider packages in an
isolated tool directory through the composite action:

```yaml
name: Deploy

on:
  push:
    branches: [main]

permissions:
  contents: read

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Deploy with Shipper
        uses: shippercli/actions/.github/actions/shipper@c2c276e12f831ba2c3377a063d579fede5cc5ecc
        with:
          command: apply
          project: backend
          profile: production
          force: true
          providers: |
            shippercli/provider-cpanel:^1.0
        env:
          CPANEL_USERNAME: ${{ secrets.CPANEL_USERNAME }}
          CPANEL_API_TOKEN: ${{ secrets.CPANEL_API_TOKEN }}
```

See [GitHub Actions](docs/GITHUB_ACTIONS.md) for production, staging, preview,
cleanup, and reusable-action patterns.

## Documentation

- [Documentation index](docs/README.md)
- [Configuration](docs/CONFIGURATION.md)
- [Provider operations](docs/PROVIDER_OPERATIONS.md)
- [Server lifecycle](docs/SERVER_LIFECYCLE.md)
- [Sites](docs/SITES.md)
- [Databases](docs/DATABASES.md)
- [PR previews](docs/PR_PREVIEWS.md)
- [GitHub Actions](docs/GITHUB_ACTIONS.md)
- [Build and releases](docs/BUILD_SYSTEM.md)

- **[Configuration Guide](./docs/CONFIGURATION.md)** - Complete shipper.yml configuration reference
- **[Server Lifecycle](./docs/SERVER_LIFECYCLE.md)** - Existing servers, managed preview servers, and cleanup rules
- **[PR Previews](./docs/PR_PREVIEWS.md)** - Set up preview environments for pull requests
- **[Sites Management](./docs/SITES.md)** - Managing site lifecycle and deployment
- **[Database Management](./docs/DATABASES.md)** - Database configuration and operations
- **[GitHub Actions Setup](./docs/GITHUB_ACTIONS.md)** - Automated deployments with GitHub Actions
- **[GitHub Action Usage](./docs/GITHUB_ACTIONS.md)** - Using Shipper as a reusable GitHub Action
- **[Build System](./docs/BUILD_SYSTEM.md)** - Understanding the build and release process
- **[Strict Standards](./docs/STRICT_STANDARDS.md)** - Code quality and type safety standards
- **[Roadmap](./ROADMAP.md)** - Planned features and Ploi.io configurations not yet supported
## Development

```bash
composer format
composer format:check
composer analyse
XDEBUG_MODE=coverage composer test
composer build
```

The codebase targets PHP 8.3+, Laravel Pint, PHPStan level 9, and Pest.

## Release

Tags build and publish the PHAR through GitHub Actions:

```bash
git tag v1.0.0
git push origin v1.0.0
```

## License

MIT
