# Configuration Guide

## Overview

Deployer uses a declarative YAML configuration file (`deployer.yml`) to define deployment providers, projects, and deployment profiles. This guide covers all configuration options and best practices.

## Configuration File Location

The `deployer.yml` file should be placed in the root of your repository.

## Basic Structure

```yaml
providers:
  # Provider configurations
  
projects:
  # Project definitions
```

## Providers Section

Providers define the deployment services and their authentication credentials.

### Ploi Provider

```yaml
providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    api_url: "https://ploi.io/api"
    server_id: "105556"
```

#### Ploi Configuration Options

| Option | Required | Description | Example |
|--------|----------|-------------|---------|
| `api_key` | Yes | Ploi API authentication key | `"${PLOI_API_KEY}"` |
| `api_url` | Yes | Ploi API endpoint URL | `"https://ploi.io/api"` |
| `server_id` | Yes | Default Ploi server ID for deployments | `"105556"` |

**Note**: Use environment variable interpolation for sensitive values like API keys.

## Projects Section

Projects define what you want to deploy and how.

### Project Configuration

```yaml
projects:
  api:
    provider: ploi
    path: ./examples/api
    repository:
      provider: github
      name: ulties/deployer-wip
    web_directory: /public
    project_root: /
    profiles:
      # Profile definitions
```

#### Project Options

| Option | Required | Description | Default |
|--------|----------|-------------|---------|
| `provider` | Yes | Which provider to use (must match a provider name) | - |
| `path` | Yes | Relative path to project directory | - |
| `repository.provider` | Yes | Git hosting provider | - |
| `repository.name` | Yes | Repository identifier | - |
| `web_directory` | No | Web server document root relative to project root | `/public` |
| `project_root` | No | Project root directory | `/` |
| `profiles` | Yes | Deployment profile definitions | - |

#### Repository Providers

Supported values for `repository.provider`:

- `github` - GitHub repositories
- `gitlab` - GitLab repositories
- `bitbucket` - Bitbucket repositories
- `custom` - Custom Git URLs

### Profile Configuration

Profiles define different deployment environments (production, staging, preview).

```yaml
profiles:
  production:
    branch: main
    domain: api-live.ulties.dev
  staging:
    branch: develop
    domain: api-test.ulties.dev
  preview:
    branch: "${GITHUB_HEAD_REF}"
    domain: "api-preview-${GITHUB_PR_NUMBER}.ulties.dev"
```

#### Profile Options

| Option | Required | Description | Example |
|--------|----------|-------------|---------|
| `branch` | Yes | Git branch to deploy | `main` |
| `domain` | Yes | Domain name for this environment | `api.example.com` |

## Environment Variable Interpolation

Any value can use environment variable interpolation with the syntax `${VAR_NAME}`.

### Common Use Cases

1. **API Keys**: `"${PLOI_API_KEY}"`
2. **Branch Names**: `"${GITHUB_HEAD_REF}"`
3. **Dynamic Domains**: `"preview-${GITHUB_PR_NUMBER}.example.com"`

### Example with Environment Variables

```yaml
providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    server_id: "${PLOI_SERVER_ID}"

projects:
  api:
    profiles:
      preview:
        branch: "${GITHUB_HEAD_REF}"
        domain: "preview-${GITHUB_PR_NUMBER}.example.com"
```

Set environment variables:

```bash
export PLOI_API_KEY="your-api-key-here"
export PLOI_SERVER_ID="105556"
export GITHUB_HEAD_REF="feature/new-feature"
export GITHUB_PR_NUMBER="42"
```

## Complete Example

```yaml
# Deployer Configuration
providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    api_url: "https://ploi.io/api"
    server_id: "105556"

projects:
  # Laravel API Backend
  api:
    provider: ploi
    path: ./examples/api
    repository:
      provider: github
      name: ulties/deployer-wip
    web_directory: /public
    project_root: /
    profiles:
      production:
        branch: main
        domain: api-live.ulties.dev
      staging:
        branch: develop
        domain: api-test.ulties.dev
      preview:
        branch: "${GITHUB_HEAD_REF}"
        domain: "api-preview-${GITHUB_PR_NUMBER}.ulties.dev"

  # Frontend Application
  frontend:
    provider: ploi
    path: ./examples/frontend
    repository:
      provider: github
      name: ulties/deployer-wip
    web_directory: /public
    project_root: /
    profiles:
      production:
        branch: main
        domain: live.ulties.dev
      staging:
        branch: develop
        domain: test.ulties.dev
      preview:
        branch: "${GITHUB_HEAD_REF}"
        domain: "preview-${GITHUB_PR_NUMBER}.ulties.dev"
```

## Best Practices

### 1. Use Environment Variables for Secrets

Never commit API keys or secrets to version control:

```yaml
# ❌ Bad
api_key: "sk_live_123456789"

# ✅ Good
api_key: "${PLOI_API_KEY}"
```

### 2. Consistent Naming

Use consistent profile names across projects:

```yaml
projects:
  api:
    profiles:
      production:  # Use same name
      staging:     # across all
      preview:     # projects
  
  frontend:
    profiles:
      production:  # Same names
      staging:     # make CI/CD
      preview:     # easier
```

### 3. Domain Conventions

Establish clear domain naming conventions:

- Production: `{service}.example.com`
- Staging: `{service}-staging.example.com` or `staging-{service}.example.com`
- Preview: `{service}-pr-{number}.example.com`

### 4. Document Your Configuration

Add comments to explain project-specific settings:

```yaml
projects:
  api:
    # Laravel API - requires public directory for web root
    web_directory: /public
    # Project files are at repository root
    project_root: /
```

### 5. Validate Before Committing

Always validate configuration before committing:

```bash
./deployer validate
```

## Configuration Validation

The `validate` command checks:

1. ✅ YAML syntax is valid
2. ✅ Required fields are present
3. ✅ Provider references exist
4. ✅ Environment variables are set
5. ✅ Paths exist locally

Example validation:

```bash
./deployer validate

# Output:
# ✓ Configuration is valid
# ✓ All providers defined
# ✓ All projects configured correctly
```

## Troubleshooting

### Error: Missing API Key

```
Error: Environment variable PLOI_API_KEY is not set
```

**Solution**: Set the environment variable:

```bash
export PLOI_API_KEY="your-api-key"
```

### Error: Invalid YAML

```
Error: Unable to parse deployer.yml
```

**Solution**: Check YAML syntax. Common issues:
- Missing quotes around values with special characters
- Incorrect indentation (use spaces, not tabs)
- Missing colons after keys

### Error: Provider Not Found

```
Error: Provider 'aws' not found
```

**Solution**: Ensure the provider is defined in the `providers` section:

```yaml
providers:
  aws:
    # Provider configuration
```

### Error: Project Path Not Found

```
Error: Project path './examples/api' does not exist
```

**Solution**: Ensure the path exists relative to the repository root.

## GitHub Actions Integration

When using with GitHub Actions, set environment variables as secrets:

```yaml
env:
  PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
  GITHUB_HEAD_REF: ${{ github.head_ref }}
  GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
```

See [github-actions.md](./github-actions.md) for complete examples.

## Advanced Configuration

### Multiple Providers

You can configure multiple providers:

```yaml
providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    api_url: "https://ploi.io/api"
    server_id: "105556"
  
  custom:
    api_key: "${CUSTOM_API_KEY}"
    endpoint: "https://api.example.com"

projects:
  api:
    provider: ploi
    # ...
  
  legacy:
    provider: custom
    # ...
```

### Custom Web Directories

For non-Laravel projects:

```yaml
projects:
  static-site:
    web_directory: /dist  # Build output directory
    project_root: /
  
  nextjs:
    web_directory: /.next  # Next.js build directory
    project_root: /
```

## Migration from Other Tools

### From Deployer PHP

Map your Deployer PHP recipes to profiles:

```yaml
# deployer.php -> deployer.yml
profiles:
  production:  # host('production')
    branch: main
    domain: example.com
```

### From Manual Deployments

1. Document your current deployment domains
2. Identify which branches deploy where
3. Map to profile configuration
4. Test with `plan` before using `apply`

## Next Steps

- Learn about [GitHub Actions integration](./github-actions.md)
- Understand the [provider system](./providers.md)
- Read [development guide](./development.md) for contributing
