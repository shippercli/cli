# GitHub Actions Setup Guide

This guide explains how to set up automated deployments with GitHub Actions using Shipper.

## Overview

Shipper integrates seamlessly with GitHub Actions to provide:
- Automated deployments on push to branches
- PR preview environments
- Automatic cleanup of preview environments
- Deployment status reporting

## Quick Start

### 1. Add Secrets

Add your Ploi API key to GitHub repository secrets:

1. Go to repository Settings → Secrets and variables → Actions
2. Click "New repository secret"
3. Name: `PLOI_API_KEY`
4. Value: Your Ploi API key
5. Click "Add secret"

### 2. Create Workflow Files

Create `.github/workflows/` directory and add workflow files.

### 3. Configure shipper.yml

Ensure your `shipper.yml` has profiles configured for your workflows.

## Deployment Workflows

### Production Deployment

Deploy to production when code is pushed to `main` branch.

**File:** `.github/workflows/deploy-production.yml`

```yaml
name: Deploy Production

on:
  push:
    branches:
      - main

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        project: [api, frontend]
    
    name: Deploy ${{ matrix.project }} to Production
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, json, yaml
          coverage: none
      
      - name: Cache Composer packages
        uses: actions/cache@v3
        with:
          path: vendor
          key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-php-
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-dev
      
      - name: Validate configuration
        run: ./shipper validate
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
      
      - name: Deploy to production
        run: ./shipper apply ${{ matrix.project }} --profile=production --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### Staging Deployment

Deploy to staging when code is pushed to `develop` branch.

**File:** `.github/workflows/deploy-staging.yml`

```yaml
name: Deploy Staging

on:
  push:
    branches:
      - develop

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        project: [api, frontend]
    
    name: Deploy ${{ matrix.project }} to Staging
    
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
      
      - name: Deploy to staging
        run: ./shipper apply ${{ matrix.project }} --profile=staging --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### Preview Deployment (Pull Requests)

Deploy preview environments for pull requests.

**File:** `.github/workflows/deploy-preview.yml`

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

### Cleanup Preview Environments

Automatically clean up preview environments when PRs are closed.

**File:** `.github/workflows/cleanup-preview.yml`

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

## Using Shipper GitHub Action

For simpler configuration, use the reusable Shipper GitHub Action.

### Basic Usage

```yaml
name: Deploy with Shipper Action

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy to Production
        uses: ulties/shipper/.github/actions/shipper-cli@main
        with:
          command: apply
          project: api
          profile: production
          force: true
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### Action Inputs

| Input | Required | Default | Description |
|-------|----------|---------|-------------|
| `command` | Yes | - | Shipper command (validate, plan, apply) |
| `project` | No | - | Project name from shipper.yml |
| `profile` | No | - | Deployment profile (production, staging, preview) |
| `force` | No | false | Skip confirmation prompts |
| `version` | No | latest | Version of shipper CLI to use |
| `working-directory` | No | . | Directory containing shipper.yml |

### Action Outputs

| Output | Description |
|--------|-------------|
| `exit-code` | Exit code from the shipper command |

### Multi-Project Deployment

```yaml
jobs:
  deploy:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        project: [api, frontend, admin]
    steps:
      - uses: actions/checkout@v4
      
      - uses: ulties/shipper/.github/actions/shipper-cli@main
        with:
          command: apply
          project: ${{ matrix.project }}
          profile: production
          force: true
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### With Specific Version

```yaml
- uses: ulties/shipper/.github/actions/shipper-cli@v1.0.0
  with:
    command: apply
    project: api
    profile: production
    version: v1.0.0
    force: true
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

## Workflow Patterns

### Sequential Deployments

Deploy projects in a specific order:

```yaml
jobs:
  deploy-api:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: ./shipper apply api --profile=production --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
  
  deploy-frontend:
    runs-on: ubuntu-latest
    needs: deploy-api  # Wait for API to deploy first
    steps:
      - uses: actions/checkout@v4
      - run: ./shipper apply frontend --profile=production --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### Conditional Deployments

Deploy based on file changes:

```yaml
on:
  push:
    branches: [main]
    paths:
      - 'api/**'
      - 'shipper.yml'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: ./shipper apply api --profile=production --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### Manual Deployment

Allow manual deployment triggers:

```yaml
name: Manual Deploy

on:
  workflow_dispatch:
    inputs:
      project:
        description: 'Project to deploy'
        required: true
        type: choice
        options:
          - api
          - frontend
      profile:
        description: 'Deployment profile'
        required: true
        type: choice
        options:
          - production
          - staging

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy ${{ inputs.project }}
        run: ./shipper apply ${{ inputs.project }} --profile=${{ inputs.profile }} --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### Approval Required

Require manual approval for production:

```yaml
jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: production  # Configure in repo settings
    steps:
      - uses: actions/checkout@v4
      - run: ./shipper apply api --profile=production --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

## Notifications

### Slack Notifications

Send deployment notifications to Slack:

```yaml
- name: Notify Slack
  if: always()
  uses: slackapi/slack-github-action@v1
  with:
    payload: |
      {
        "text": "Deployment ${{ job.status }}: ${{ matrix.project }} to ${{ github.ref_name }}",
        "blocks": [
          {
            "type": "section",
            "text": {
              "type": "mrkdwn",
              "text": "*Deployment Status:* ${{ job.status }}\n*Project:* ${{ matrix.project }}\n*Branch:* ${{ github.ref_name }}"
            }
          }
        ]
      }
  env:
    SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
```

### Email Notifications

GitHub Actions sends email notifications by default for workflow failures.

Configure in: Profile → Settings → Notifications → Actions

## Caching

### Cache Composer Dependencies

```yaml
- name: Cache Composer packages
  uses: actions/cache@v3
  with:
    path: vendor
    key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
    restore-keys: |
      ${{ runner.os }}-php-
```

### Cache Shipper Binary

```yaml
- name: Cache Shipper
  uses: actions/cache@v3
  with:
    path: builds/shipper
    key: shipper-${{ hashFiles('composer.lock') }}
```

## Security Best Practices

### 1. Use Secrets

Never commit credentials:
```yaml
env:
  PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}  # ✅ Good
  # PLOI_API_KEY: "hardcoded-key"  # ❌ Bad
```

### 2. Minimal Permissions

Only grant necessary permissions:
```yaml
permissions:
  contents: read
  pull-requests: write  # Only if commenting on PRs
```

### 3. Pin Action Versions

Use specific versions for security:
```yaml
uses: actions/checkout@v4  # ✅ Good
# uses: actions/checkout@main  # ⚠️ Less secure
```

### 4. Restrict Branch Deployment

Only deploy from specific branches:
```yaml
on:
  push:
    branches:
      - main  # Only production branch
```

### 5. Use Environments

Protect production with required reviewers:
```yaml
jobs:
  deploy:
    environment: production  # Requires approval
```

## Troubleshooting

### Workflow Not Triggering

**Check:**
1. Workflow file is in `.github/workflows/`
2. YAML syntax is valid
3. Branch filter matches push branch
4. Workflow is enabled in Actions tab

### Deployment Fails

**Check:**
1. `PLOI_API_KEY` secret is set correctly
2. Configuration is valid (`./shipper validate`)
3. Server has sufficient resources
4. Deployment timeout is adequate

### Permission Errors

**Check:**
1. Workflow permissions are set correctly
2. PLOI_API_KEY has necessary permissions
3. GitHub token has required permissions

## Best Practices

1. **Validate First**: Always run `validate` before `apply`
2. **Use Force in CI**: Always use `--force` flag in automated workflows
3. **Cache Dependencies**: Cache Composer packages for faster builds
4. **Matrix Strategy**: Deploy multiple projects in parallel
5. **Comment PRs**: Always comment deployment status on PRs
6. **Clean Up**: Always clean up preview environments
7. **Monitor Workflows**: Regularly check workflow run status
8. **Use Environments**: Protect production with required approvals
9. **Pin Versions**: Use specific action versions for security
10. **Test Locally**: Test deployments locally before pushing

## Next Steps

- [Using Shipper as GitHub Action](./GITHUB_ACTION.md) - Detailed action usage
- [PR Previews](./PR_PREVIEWS.md) - PR preview deployment guide
- [Build System](./BUILD_SYSTEM.md) - Understanding the build process
