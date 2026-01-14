# PR Preview Deployments Guide

This guide explains how to set up and use PR preview deployments with Shipper.

## Overview

PR preview deployments automatically create temporary environments for each pull request, allowing you to:
- Test changes before merging
- Share work-in-progress with stakeholders
- Catch integration issues early
- Review UI/UX changes in a real environment

## How It Works

1. A pull request is opened against `main` or `develop` branch
2. GitHub Actions triggers the preview deployment workflow
3. Shipper creates a new site with a unique domain (e.g., `api-preview-123.example.com`)
4. Shipper creates dedicated preview databases
5. The PR is updated with deployment status and links
6. When the PR is closed or merged, the preview environment is automatically cleaned up

## Configuration

### 1. Configure Preview Profile in shipper.yml

Add a `preview` profile to your projects:

```yaml
projects:
  api:
    provider: ploi
    path: ./api
    repository:
      provider: github
      name: ulties/shipper
    databases:
      main:
        name: "shipper_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
        user: "shipper_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
        type: mysql
    profiles:
      preview:
        branch: "${GITHUB_HEAD_REF}"
        domain: "api-preview-${GITHUB_PR_NUMBER}.example.com"
```

**Key Points:**
- `branch`: Use `${GITHUB_HEAD_REF}` to deploy the PR branch
- `domain`: Use `${GITHUB_PR_NUMBER}` to create unique domains
- `databases`: Use `${GITHUB_PR_NUMBER}` for PR-specific databases

### 2. Create Preview Deployment Workflow

Create `.github/workflows/deploy-preview.yml`:

```yaml
name: Deploy Preview

on:
  pull_request:
    branches:
      - main
      - develop

permissions:
  contents: read
  pull-requests: write

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        project: [api, frontend]
    
    name: Deploy ${{ matrix.project }} Preview
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, json, yaml
          coverage: none
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-dev
      
      - name: Validate configuration
        run: ./shipper validate
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
      
      - name: Plan deployment
        run: ./shipper plan ${{ matrix.project }} --profile=preview
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
      
      - name: Deploy preview
        run: ./shipper apply ${{ matrix.project }} --profile=preview --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
      
      - name: Comment PR
        uses: actions/github-script@v7
        with:
          script: |
            const domain = '${{ matrix.project }}' === 'api' 
              ? 'api-preview-${{ github.event.pull_request.number }}.example.com'
              : 'preview-${{ github.event.pull_request.number }}.example.com';
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: `🚀 Preview deployed for **${{ matrix.project }}**!\n\n**URL:** https://${domain}\n**Branch:** \`${{ github.head_ref }}\`\n**Profile:** preview`
            })
```

### 3. Create Cleanup Workflow

Create `.github/workflows/cleanup-preview.yml`:

```yaml
name: Cleanup Preview

on:
  pull_request:
    types: [closed]

jobs:
  cleanup:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        project: [api, frontend]
    
    name: Cleanup ${{ matrix.project }} Preview
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, json, yaml
          coverage: none
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-dev
      
      - name: Destroy preview
        run: ./shipper destroy ${{ matrix.project }} --profile=preview --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_HEAD_REF: ${{ github.event.pull_request.head.ref }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
      
      - name: Comment PR
        uses: actions/github-script@v7
        with:
          script: |
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: `🧹 Preview environment for **${{ matrix.project }}** has been cleaned up.`
            })
```

## Using the GitHub Action

Alternatively, use the Shipper GitHub Action for simpler configuration:

```yaml
name: Deploy Preview

on:
  pull_request:
    branches: [main, develop]

permissions:
  contents: read
  pull-requests: write

jobs:
  deploy:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        project: [api, frontend]
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy Preview
        uses: ulties/shipper/.github/actions/shipper-cli@main
        with:
          command: apply
          project: ${{ matrix.project }}
          profile: preview
          force: true
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
```

## Environment Variables

Required environment variables for PR previews:

- `PLOI_API_KEY`: Your Ploi API key (from GitHub secrets)
- `GITHUB_HEAD_REF`: PR branch name (automatically available)
- `GITHUB_PR_NUMBER`: PR number (automatically available)

Set the Ploi API key in your repository secrets:
1. Go to Settings → Secrets and variables → Actions
2. Click "New repository secret"
3. Name: `PLOI_API_KEY`
4. Value: Your Ploi API key

## Preview Domain Strategy

### Subdomain Pattern

Use a consistent pattern for preview domains:
```yaml
domain: "api-preview-${GITHUB_PR_NUMBER}.example.com"
```

This creates domains like:
- `api-preview-123.example.com` for PR #123
- `api-preview-456.example.com` for PR #456

### Wildcard DNS

Configure a wildcard DNS record for your preview domains:

**DNS Record:**
```
Type: A or CNAME
Name: *.example.com
Value: [Your server IP or hostname]
```

This allows any subdomain to automatically resolve to your server.

### SSL Certificates

Ploi automatically provisions SSL certificates via Let's Encrypt for preview domains.

## Database Management

### Automatic Creation

Preview databases are automatically created with unique names:
```yaml
databases:
  main:
    name: "shipper_${PROJECT_NAME}_preview_${GITHUB_PR_NUMBER}"
    user: "shipper_${PROJECT_NAME}_preview_${GITHUB_PR_NUMBER}"
    type: mysql
```

### Automatic Cleanup

When the cleanup workflow runs:
1. The preview site is destroyed
2. Associated databases are automatically deleted
3. Database users are removed

### Data Seeding

To seed preview databases with test data, add a deploy script:

**In your Laravel project:**
```bash
# .ploi/deploy.sh
php artisan migrate --force
php artisan db:seed --force
```

## Commenting on Pull Requests

The workflow automatically comments on PRs with deployment status:

**Success Comment:**
```
🚀 Preview deployed for **api**!

**URL:** https://api-preview-123.example.com
**Branch:** `feature/new-feature`
**Profile:** preview
```

**Cleanup Comment:**
```
🧹 Preview environment for **api** has been cleaned up.
```

### Custom Comments

Customize the PR comment in the workflow:

```yaml
- name: Comment PR
  uses: actions/github-script@v7
  with:
    script: |
      const domain = 'api-preview-${{ github.event.pull_request.number }}.example.com';
      github.rest.issues.createComment({
        issue_number: context.issue.number,
        owner: context.repo.owner,
        repo: context.repo.repo,
        body: `## 🚀 Preview Deployment
        
**URL:** https://${domain}
**Branch:** \`${{ github.head_ref }}\`
**Commit:** ${{ github.sha }}

### Test Credentials
- **Email:** test@example.com
- **Password:** password

This preview will be automatically cleaned up when the PR is closed.`
      })
```

## Automatic Cleanup Strategies

### On PR Close (Recommended)

Clean up when PR is closed or merged:
```yaml
on:
  pull_request:
    types: [closed]
```

### Weekly Cleanup

Create a scheduled cleanup for stale previews:

```yaml
name: Weekly Cleanup

on:
  schedule:
    - cron: '0 0 * * 0'  # Every Sunday at midnight

jobs:
  cleanup:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      # Add logic to find and cleanup old preview sites
      # This requires custom scripting based on your needs
```

## Troubleshooting

### Issue: Preview not deploying

**Check:**
1. Verify `PLOI_API_KEY` is set in repository secrets
2. Check workflow run logs in Actions tab
3. Verify shipper.yml has preview profile configured
4. Ensure DNS wildcard is configured

### Issue: Domain not accessible

**Check:**
1. Verify DNS wildcard record is configured
2. Wait a few minutes for DNS propagation
3. Check Ploi dashboard for SSL certificate status
4. Verify site is deployed in Ploi

### Issue: Database connection errors

**Check:**
1. Verify database was created (check Ploi dashboard)
2. Check environment variables are set correctly
3. Verify database credentials in site environment variables

### Issue: Cleanup not working

**Check:**
1. Verify cleanup workflow is triggered on PR close
2. Check that `GITHUB_PR_NUMBER` is available in cleanup workflow
3. Verify Ploi API key has permissions to delete sites

## Best Practices

1. **Use Consistent Naming**: Always include PR number in domain and database names
2. **Automate Cleanup**: Always configure automatic cleanup on PR close
3. **Test Before Merging**: Verify preview deployment before approving PR
4. **Seed Test Data**: Include data seeding in deploy scripts
5. **Share Credentials**: Include test credentials in PR comments if needed
6. **Monitor Costs**: Preview environments consume server resources
7. **Limit Preview Scope**: Only deploy what's necessary for testing
8. **Use Matrix Strategy**: Deploy multiple projects in parallel

## Security Considerations

1. **API Keys**: Store Ploi API key in GitHub secrets, never in code
2. **Preview Access**: Consider adding basic auth to preview sites
3. **Test Data**: Don't use production data in preview databases
4. **Cleanup**: Always clean up previews to prevent resource leaks
5. **Permissions**: Use minimal GitHub Actions permissions required

## Next Steps

- [Sites Management](./SITES.md) - Learn about site lifecycle management
- [Database Management](./DATABASES.md) - Deep dive into database features
- [GitHub Actions Setup](./GITHUB_ACTIONS.md) - Complete GitHub Actions guide
