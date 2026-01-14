# Shipper Configuration Guide

This guide explains how to configure Shipper for your deployments using the `shipper.yml` file.

## Overview

Shipper uses a declarative YAML configuration file (`shipper.yml`) that should be placed in the root of your repository. This file defines:
- Provider credentials and settings
- Projects to deploy
- Deployment profiles (production, staging, preview)
- Site and database configurations

## Configuration File Structure

```yaml
providers:
  <provider_name>:
    # Provider-specific configuration
    
projects:
  <project_name>:
    # Project configuration
    profiles:
      <profile_name>:
        # Profile-specific configuration
```

## Provider Configuration

### Ploi Provider

Currently, Shipper supports Ploi as a deployment provider.

```yaml
providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    api_url: "https://ploi.io/api"
    server_id: "105556"
    deployment_timeout: 60
```

**Configuration Options:**

- `api_key` (required): Your Ploi API key. Use environment variables for security (e.g., `${PLOI_API_KEY}`)
- `api_url` (required): Ploi API endpoint URL (default: `https://ploi.io/api`)
- `server_id` (required): The ID of your Ploi server where sites will be deployed
- `deployment_timeout` (optional): Maximum time in seconds to wait for deployment completion (default: 60)

**Getting Your Ploi API Key:**
1. Log in to your Ploi account
2. Navigate to Settings → API
3. Generate a new API token
4. Store it as a secret in your GitHub repository or environment

**Finding Your Server ID:**
1. Log in to Ploi
2. Navigate to your server
3. The server ID is in the URL: `https://ploi.io/servers/{server_id}`

## Project Configuration

Each project represents an application you want to deploy.

```yaml
projects:
  api:
    provider: ploi
    path: ./examples/api
    repository:
      provider: github
      name: ulties/shipper
    web_directory: /public
    project_root: /
    databases:
      main:
        name: "myapp_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
        user: "myapp_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
        type: mysql
    profiles:
      production:
        branch: main
        domain: api.example.com
      staging:
        branch: develop
        domain: api-staging.example.com
      preview:
        branch: "${GITHUB_HEAD_REF}"
        domain: "api-preview-${GITHUB_PR_NUMBER}.example.com"
```

**Project Options:**

- `provider` (required): Which provider to use (e.g., `ploi`)
- `path` (required): Path to the project directory relative to repository root
- `repository` (required): Repository configuration
  - `provider`: Git provider (`github`, `gitlab`, `bitbucket`, or `custom`)
  - `name`: Repository in format `username/repository`
- `web_directory` (optional): Web root directory (default: `/public` for Laravel)
- `project_root` (optional): Project root directory (default: `/`)
- `databases` (optional): Database configurations (see Database Configuration section)
- `profiles` (required): Deployment profiles (see Profile Configuration section)

## Profile Configuration

Profiles define different deployment environments (production, staging, preview).

```yaml
profiles:
  production:
    branch: main
    domain: api.example.com
  staging:
    branch: develop
    domain: api-staging.example.com
  preview:
    branch: "${GITHUB_HEAD_REF}"
    domain: "api-preview-${GITHUB_PR_NUMBER}.example.com"
```

**Profile Options:**

- `branch` (required): Git branch to deploy from
- `domain` (required): Domain name for the site

**Variable Interpolation:**

You can use environment variables in profile configuration:
- `${GITHUB_HEAD_REF}`: Branch name from GitHub PR
- `${GITHUB_PR_NUMBER}`: Pull request number
- Any custom environment variable

## Database Configuration

Shipper can automatically create and manage databases for your projects.

```yaml
databases:
  main:
    name: "myapp_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
    user: "myapp_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
    type: mysql
  cache:
    name: "myapp_cache_${PROFILE}"
    user: "myapp_cache_${PROFILE}"
    type: mysql
```

**Database Options:**

- `name` (required): Database name (supports variable interpolation)
- `user` (required): Database user name (supports variable interpolation)
- `type` (required): Database type (currently supports `mysql`)

**Variable Interpolation:**

Database names and users support the following variables:
- `${PROJECT_NAME}`: Project name from configuration (e.g., `api`)
- `${PROFILE}`: Deployment profile (e.g., `production`, `staging`, `preview`)
- `${GITHUB_PR_NUMBER}`: Pull request number (for PR previews)
- Any environment variable

**Variable Handling:**
- Undefined environment variables are treated as empty strings
- Trailing underscores are automatically removed
- Multiple consecutive underscores are collapsed to a single underscore

**Examples:**

For project "api" with profile "production":
- Pattern: `myapp_${PROJECT_NAME}_${PROFILE}`
- Result: `myapp_api_production`

For project "api" with profile "preview" and PR #123:
- Pattern: `myapp_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}`
- Result: `myapp_api_preview_123`

**Database Management:**

- Databases are automatically created if they don't exist
- Each database gets a secure random password
- Databases are automatically linked to their respective sites
- When a site is destroyed, associated databases are also deleted

## Environment Variables

Shipper supports environment variable interpolation throughout the configuration file using the `${VARIABLE_NAME}` syntax.

**Common Variables:**

- `${PLOI_API_KEY}`: Ploi API key (required)
- `${GITHUB_HEAD_REF}`: PR branch name (for preview deployments)
- `${GITHUB_PR_NUMBER}`: PR number (for preview deployments)
- `${PROJECT_NAME}`: Project name (automatically available)
- `${PROFILE}`: Profile name (automatically available)

**Setting Environment Variables:**

**Locally:**
```bash
export PLOI_API_KEY="your-api-key"
./shipper apply api --profile=production
```

**In GitHub Actions:**
```yaml
env:
  PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
  GITHUB_HEAD_REF: ${{ github.head_ref }}
  GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
```

## Complete Example

Here's a complete `shipper.yml` example:

```yaml
# Provider configuration
providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    api_url: "https://ploi.io/api"
    server_id: "105556"
    deployment_timeout: 60

# Projects
projects:
  # Laravel API
  api:
    provider: ploi
    path: ./api
    repository:
      provider: github
      name: ulties/shipper
    web_directory: /public
    project_root: /
    databases:
      main:
        name: "shipper_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
        user: "shipper_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
        type: mysql
    profiles:
      production:
        branch: main
        domain: api.example.com
      staging:
        branch: develop
        domain: api-staging.example.com
      preview:
        branch: "${GITHUB_HEAD_REF}"
        domain: "api-preview-${GITHUB_PR_NUMBER}.example.com"
  
  # Frontend Application
  frontend:
    provider: ploi
    path: ./frontend
    repository:
      provider: github
      name: ulties/shipper
    web_directory: /public
    project_root: /
    profiles:
      production:
        branch: main
        domain: www.example.com
      staging:
        branch: develop
        domain: staging.example.com
      preview:
        branch: "${GITHUB_HEAD_REF}"
        domain: "preview-${GITHUB_PR_NUMBER}.example.com"
```

## Validation

Always validate your configuration before deploying:

```bash
./shipper validate
```

This will check:
- Configuration file syntax
- Required fields are present
- Provider credentials are accessible
- Variables can be resolved

## Best Practices

1. **Use Environment Variables for Secrets**: Never commit API keys or passwords to your repository
2. **Consistent Naming**: Use consistent patterns for database names and domains
3. **Profile Structure**: Use standard profile names (production, staging, preview)
4. **Domain Strategy**: Use subdomains for different environments
5. **Database Cleanup**: Ensure preview databases are cleaned up (see PR Preview guide)
6. **Validate First**: Always run `shipper validate` before deploying

## Troubleshooting

**Issue**: "Failed to load configuration"
- **Solution**: Check that `shipper.yml` exists and has valid YAML syntax

**Issue**: "Provider credentials invalid"
- **Solution**: Verify `PLOI_API_KEY` environment variable is set correctly

**Issue**: "Server not found"
- **Solution**: Check that `server_id` matches your Ploi server ID

**Issue**: "Domain already exists"
- **Solution**: Domains must be unique. Check if the domain is already used by another site

**Issue**: "Database name contains invalid characters"
- **Solution**: Database names can only contain letters, numbers, and underscores

## Next Steps

- [PR Preview Deployments](./PR_PREVIEWS.md) - Learn how to set up PR preview environments
- [Sites Management](./SITES.md) - Learn about site configuration and management
- [Database Management](./DATABASES.md) - Deep dive into database features
- [GitHub Actions Setup](./GITHUB_ACTIONS.md) - Set up automated deployments
