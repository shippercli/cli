# Sites Management Guide

This guide explains how Shipper manages sites and their lifecycle.

## Overview

Shipper automatically manages the complete lifecycle of sites on your deployment provider (Ploi):
- Creating new sites
- Configuring site settings
- Deploying code changes
- Managing site lifecycle
- Cleaning up sites

## Site Lifecycle

### 1. Site Creation

When you run `shipper apply` for a new deployment, Shipper:

1. **Checks if site exists** by domain name
2. **Creates site** if it doesn't exist with:
   - Domain name from profile configuration
   - Repository information (GitHub/GitLab/Bitbucket)
   - Web directory and project root settings
3. **Configures site** with proper settings
4. **Links databases** if configured
5. **Triggers deployment** from specified branch

### 2. Site Updates

For existing sites, Shipper:

1. **Finds site** by domain name
2. **Updates configuration** if changed
3. **Triggers new deployment** from branch
4. **Waits for deployment** to complete
5. **Reports status**

### 3. Site Destruction

When you run `shipper destroy`, Shipper:

1. **Finds site** by domain name
2. **Deletes all linked databases**
3. **Removes database users**
4. **Destroys the site**

## Site Configuration

### Domain Configuration

Domains are configured in the profile section:

```yaml
projects:
  api:
    profiles:
      production:
        domain: api.example.com
      staging:
        domain: api-staging.example.com
      preview:
        domain: "api-preview-${GITHUB_PR_NUMBER}.example.com"
```

**Best Practices:**
- Use subdomains for different environments
- Use variable interpolation for dynamic domains (PR previews)
- Ensure domains are unique across all sites

### Repository Configuration

Specify which repository to deploy from:

```yaml
repository:
  provider: github  # github, gitlab, bitbucket, or custom
  name: shippercli/cli  # username/repository
```

**Supported Providers:**
- `github`: GitHub repositories
- `gitlab`: GitLab repositories
- `bitbucket`: Bitbucket repositories
- `custom`: Custom Git repositories

**When sites are created:**
- Repository is automatically cloned
- Initial deployment is triggered
- Webhook is set up (if supported by provider)

### Web Directory Configuration

Configure where your application's public files are located:

```yaml
web_directory: /public  # Laravel default
project_root: /         # Repository root
```

**Common Configurations:**

**Laravel:**
```yaml
web_directory: /public
project_root: /
```

**Static Sites:**
```yaml
web_directory: /dist
project_root: /
```

**Nested Projects:**
```yaml
web_directory: /app/public
project_root: /app
```

### Branch Configuration

Specify which branch to deploy:

```yaml
profiles:
  production:
    branch: main
  staging:
    branch: develop
  preview:
    branch: "${GITHUB_HEAD_REF}"
```

**Deployment Behavior:**
- Sites deploy from the specified branch
- Pushing to the branch triggers automatic deployment (via webhook)
- Switching branches requires a new deployment

## Site Discovery

Shipper finds sites by domain name, not by ID. This means:

1. **Domain-based lookup**: Sites are identified by their domain
2. **No manual ID management**: You don't need to track site IDs
3. **Idempotent operations**: Running apply multiple times is safe
4. **Automatic creation**: Sites are created if they don't exist

**Example Workflow:**

```bash
# First run - creates site
./shipper apply api --profile=production
# Site created: api.example.com

# Second run - updates existing site
./shipper apply api --profile=production
# Site found: api.example.com, deploying...
```

## Deployment Process

### Triggering Deployments

Deployments are triggered when:

1. Running `shipper apply`
2. Pushing to the configured branch (via webhook)
3. Manual deployment in Ploi dashboard

### Deployment Timeout

Configure how long to wait for deployments:

```yaml
providers:
  ploi:
    deployment_timeout: 60  # seconds
```

**Default:** 60 seconds

**Behavior:**
- Shipper polls deployment status every 5 seconds
- If deployment doesn't complete within timeout, an error is reported
- The deployment continues running on the server

### Deployment Scripts

Ploi uses deployment scripts stored in your repository:

**Location:** `.ploi/deploy.sh` or custom location in Ploi settings

**Example Laravel Deploy Script:**
```bash
#!/bin/bash

# Exit on error
set -e

# Pull latest changes
git pull origin $PLOI_BRANCH

# Install dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Run migrations
php artisan migrate --force

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
php artisan queue:restart

echo "Deployment completed successfully!"
```

**Best Practices:**
- Always exit on errors (`set -e`)
- Use `--force` flag for production commands
- Clear and rebuild caches
- Restart background workers
- Log deployment steps

## Site Management Commands

### Validate Configuration

Check configuration before deploying:

```bash
./shipper validate
```

**Checks:**
- YAML syntax
- Required fields
- Provider credentials
- Environment variables

### Plan Deployment

Preview what changes will be made:

```bash
./shipper plan api --profile=production
```

**Output:**
- Whether site will be created or updated
- Domain name
- Branch to deploy
- Database changes

### Apply Deployment

Execute the deployment:

```bash
# Interactive mode (asks for confirmation)
./shipper apply api --profile=production

# Force mode (no confirmation)
./shipper apply api --profile=production --force
```

### Destroy Site

Remove a site and its resources:

```bash
# Interactive mode
./shipper destroy api --profile=preview

# Force mode
./shipper destroy api --profile=preview --force
```

**Warning:** This permanently deletes:
- The site
- All associated databases
- All data (cannot be recovered)

## Multiple Projects

Deploy multiple projects from the same repository:

```yaml
projects:
  api:
    path: ./api
    profiles:
      production:
        domain: api.example.com
  
  frontend:
    path: ./frontend
    profiles:
      production:
        domain: www.example.com
```

**Deploying:**
```bash
# Deploy specific project
./shipper apply api --profile=production

# Deploy all projects (requires custom scripting)
for project in api frontend; do
  ./shipper apply $project --profile=production --force
done
```

## Site Security

### SSL Certificates

SSL certificates are automatically provisioned by Ploi using Let's Encrypt:

- **Automatic renewal**: Certificates auto-renew before expiration
- **Wildcard support**: Configure wildcard DNS for preview domains
- **Multiple domains**: Add additional domains in Ploi dashboard

### Environment Variables

Set environment variables in Ploi dashboard:

1. Navigate to site → Environment
2. Add variables (e.g., `DB_PASSWORD`, `API_KEY`)
3. Save and redeploy

**Important:** Database credentials are automatically set by Shipper.

### Basic Authentication

Add basic auth for preview environments:

1. Navigate to site in Ploi
2. Go to Settings → Security
3. Enable basic authentication
4. Set username and password

## Monitoring and Logs

### Deployment Logs

View deployment logs in:
- Ploi dashboard → Site → Deployments
- GitHub Actions logs (if using CI/CD)

### Application Logs

Access logs via:
- SSH into server
- Ploi log viewer
- Centralized logging service

### Site Status

Check site status:
- Ploi dashboard shows deployment status
- Health check endpoints in your application
- Monitoring services (e.g., Uptime Robot)

## Troubleshooting

### Site Creation Fails

**Error:** "Domain already exists"
- **Solution:** Domain is already used by another site. Choose a different domain.

**Error:** "Repository not accessible"
- **Solution:** Verify repository is public or Ploi has access via deploy key.

**Error:** "Server not found"
- **Solution:** Check `server_id` in configuration matches your Ploi server.

### Deployment Fails

**Error:** "Deployment timeout"
- **Solution:** Increase `deployment_timeout` in configuration or check deployment script for errors.

**Error:** "Deployment script failed"
- **Solution:** Check deployment logs in Ploi for specific errors.

**Error:** "Database connection failed"
- **Solution:** Verify database credentials in environment variables.

### Site Not Accessible

**Issue:** 404 or server error
- **Check:** Web directory is configured correctly
- **Check:** Deployment completed successfully
- **Check:** SSL certificate is provisioned

**Issue:** DNS not resolving
- **Check:** DNS records point to correct server
- **Check:** DNS propagation time (can take up to 48 hours)

## Best Practices

1. **Use Plan First**: Always run `plan` before `apply`
2. **Test Locally**: Test changes locally before deploying
3. **Staging First**: Deploy to staging before production
4. **Monitor Deployments**: Watch deployment logs for errors
5. **Backup First**: Backup databases before major changes
6. **Use Force in CI**: Use `--force` flag in automated deployments
7. **Clean Up Previews**: Always clean up PR preview environments
8. **Document Process**: Keep deployment runbooks updated

## Advanced Topics

### Custom Deploy Hooks

Add custom logic before/after deployments:

```bash
#!/bin/bash
# .ploi/deploy.sh

# Pre-deployment
echo "Starting deployment..."
php artisan down

# Main deployment
composer install --no-dev
php artisan migrate --force

# Post-deployment
php artisan up
php artisan cache:clear
php artisan queue:restart

# Custom notifications
curl -X POST "https://hooks.slack.com/..." \
  -d '{"text":"Deployment completed!"}'
```

### Zero-Downtime Deployments

Configure zero-downtime deployments in Ploi:

1. Enable "Quick Deploy" in site settings
2. Use Laravel Horizon for queue workers
3. Use database migration strategies that don't require downtime

### Multi-Server Deployments

For high-availability setups:

1. Configure load balancer
2. Deploy to multiple servers
3. Use shared storage for uploaded files
4. Use centralized cache (Redis)

## Next Steps

- [Database Management](./DATABASES.md) - Learn about database features
- [GitHub Actions Setup](./GITHUB_ACTIONS.md) - Automate deployments
- [Configuration Guide](./CONFIGURATION.md) - Detailed configuration options
