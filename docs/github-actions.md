# GitHub Actions Integration

## Overview

Deployer includes GitHub Actions workflows for automated CI/CD. The workflows handle code quality checks, automated deployments, and preview environments for pull requests.

## Available Workflows

### 1. CI Workflow (`ci.yml`)

Runs on every push and pull request to validate code quality.

**Location**: `.github/workflows/ci.yml`

**Triggers**:
- Push to any branch
- Pull request to any branch

**Jobs**:
1. **Code Style** - Runs Laravel Pint to check formatting
2. **Static Analysis** - Runs PHPStan level 9 for type safety
3. **Tests** - Runs Pest test suite

**Usage**:
```yaml
name: CI

on:
  push:
    branches: ['*']
  pull_request:
    branches: ['*']

jobs:
  code-style:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: composer format:check

  static-analysis:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: composer analyse

  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: composer test
```

### 2. Production Deployment (`deploy-production.yml`)

Deploys all projects to production when code is merged to `main`.

**Location**: `.github/workflows/deploy-production.yml`

**Triggers**:
- Push to `main` branch

**Environment Variables Required**:
- `PLOI_API_KEY` - Ploi API authentication key

**What It Does**:
1. Checks out code
2. Sets up PHP environment
3. Installs dependencies
4. Deploys all configured projects to production profile

**Example**:
```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install Dependencies
        run: composer install --no-dev --optimize-autoloader
        
      - name: Deploy API to Production
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
        run: ./deployer apply api --profile=production --force
        
      - name: Deploy Frontend to Production
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
        run: ./deployer apply frontend --profile=production --force
```

### 3. Staging Deployment (`deploy-staging.yml`)

Deploys all projects to staging when code is pushed to `develop`.

**Location**: `.github/workflows/deploy-staging.yml`

**Triggers**:
- Push to `develop` branch

**Environment Variables Required**:
- `PLOI_API_KEY` - Ploi API authentication key

**What It Does**:
1. Checks out code
2. Sets up PHP environment
3. Installs dependencies
4. Deploys all configured projects to staging profile

### 4. Preview Deployment (`deploy-preview.yml`)

Creates preview environments for pull requests and posts deployment URLs as comments.

**Location**: `.github/workflows/deploy-preview.yml`

**Triggers**:
- Pull request opened, synchronized, or reopened

**Environment Variables Required**:
- `PLOI_API_KEY` - Ploi API authentication key
- `GITHUB_TOKEN` - Automatically provided by GitHub Actions

**What It Does**:
1. Checks out code
2. Sets up PHP environment
3. Installs dependencies
4. Deploys all projects to preview profile with PR-specific domains
5. Comments on PR with deployment URLs

**Example**:
```yaml
name: Deploy Preview

on:
  pull_request:
    types: [opened, synchronize, reopened]

jobs:
  deploy-preview:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install Dependencies
        run: composer install --no-dev --optimize-autoloader
        
      - name: Deploy to Preview
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
        run: |
          ./deployer apply api --profile=preview --force
          ./deployer apply frontend --profile=preview --force
          
      - name: Comment PR
        uses: actions/github-script@v6
        with:
          script: |
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: `## Preview Deployed! 🚀\n\n` +
                    `- API: https://api-preview-${{ github.event.pull_request.number }}.ulties.dev\n` +
                    `- Frontend: https://preview-${{ github.event.pull_request.number }}.ulties.dev`
            })
```

## Setup Instructions

### 1. Configure Repository Secrets

Navigate to your repository settings: `Settings → Secrets and variables → Actions`

Add the following secrets:

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `PLOI_API_KEY` | Your Ploi API key | `sk_live_...` |

**GITHUB_TOKEN** is automatically provided by GitHub Actions.

### 2. Enable Workflows

The workflows are automatically enabled when you push them to your repository.

### 3. Configure Branch Protection

For production safety, configure branch protection rules:

1. Go to `Settings → Branches`
2. Add rule for `main` branch:
   - ✅ Require status checks to pass before merging
   - ✅ Require branches to be up to date before merging
   - Select: `code-style`, `static-analysis`, `tests`
   - ✅ Require pull request reviews before merging

## Workflow Patterns

### Sequential Deployments

Deploy projects in a specific order:

```yaml
- name: Deploy API First
  run: ./deployer apply api --profile=production --force

- name: Deploy Frontend After API
  run: ./deployer apply frontend --profile=production --force
```

### Parallel Deployments

Deploy multiple projects simultaneously (faster):

```yaml
jobs:
  deploy-api:
    runs-on: ubuntu-latest
    steps:
      - # Setup steps
      - run: ./deployer apply api --profile=production --force

  deploy-frontend:
    runs-on: ubuntu-latest
    steps:
      - # Setup steps
      - run: ./deployer apply frontend --profile=production --force
```

### Conditional Deployments

Deploy only when specific paths change:

```yaml
on:
  push:
    paths:
      - 'examples/api/**'
      - 'deployer.yml'

jobs:
  deploy:
    # Deploy only API when API code changes
    run: ./deployer apply api --profile=production --force
```

### Environment-Specific Jobs

Use GitHub environments for approvals:

```yaml
jobs:
  deploy:
    runs-on: ubuntu-latest
    environment:
      name: production
      url: https://api.example.com
    steps:
      - # Deployment steps
```

## Environment Variables

### Standard Variables

These are automatically available in GitHub Actions:

| Variable | Description | Example |
|----------|-------------|---------|
| `GITHUB_REF` | Full git ref | `refs/heads/main` |
| `GITHUB_SHA` | Commit SHA | `a1b2c3d4...` |
| `GITHUB_HEAD_REF` | PR source branch | `feature/new-feature` |
| `GITHUB_BASE_REF` | PR target branch | `main` |
| `GITHUB_ACTOR` | User who triggered | `username` |

### Custom Variables for Deployer

Pass these to deployment commands:

```yaml
env:
  PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
  GITHUB_HEAD_REF: ${{ github.head_ref }}
  GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
```

## Notifications

### Slack Notifications

Add Slack notifications on deployment:

```yaml
- name: Notify Slack
  if: always()
  uses: 8398a7/action-slack@v3
  with:
    status: ${{ job.status }}
    text: 'Deployment to production completed'
    webhook_url: ${{ secrets.SLACK_WEBHOOK }}
```

### Email Notifications

Configure in repository settings: `Settings → Notifications`

### PR Comments

Automatically comment on PRs with deployment info:

```yaml
- name: Comment on PR
  uses: actions/github-script@v6
  with:
    script: |
      github.rest.issues.createComment({
        issue_number: context.issue.number,
        owner: context.repo.owner,
        repo: context.repo.repo,
        body: 'Deployment successful! 🎉'
      })
```

## Troubleshooting

### Issue: Workflow Not Triggering

**Cause**: Workflow file in wrong location or syntax error

**Solution**:
1. Ensure workflow files are in `.github/workflows/`
2. Validate YAML syntax
3. Check trigger conditions match your branch names

### Issue: Secrets Not Found

**Cause**: Secret not configured or wrong name

**Solution**:
1. Verify secret name in repository settings
2. Check secret name matches exactly in workflow
3. Secrets are case-sensitive

### Issue: Deployment Fails

**Cause**: Various deployment-related issues

**Solution**:
1. Check workflow logs for error messages
2. Verify `deployer.yml` is valid (`./deployer validate`)
3. Ensure API credentials are correct
4. Test deployment locally first

### Issue: PHP Version Mismatch

**Cause**: Workflow using different PHP version than required

**Solution**:
Update `setup-php` action:

```yaml
- name: Setup PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: '8.2'  # Match your requirements
```

## Best Practices

### 1. Always Validate Before Deploying

```yaml
- name: Validate Configuration
  run: ./deployer validate

- name: Deploy
  run: ./deployer apply api --profile=production --force
```

### 2. Use `--force` in CI/CD

Skip interactive prompts in automated environments:

```yaml
run: ./deployer apply api --profile=production --force
```

### 3. Optimize Dependencies

Use production-optimized dependencies:

```yaml
run: composer install --no-dev --optimize-autoloader
```

### 4. Cache Dependencies

Speed up workflows with caching:

```yaml
- name: Cache Composer dependencies
  uses: actions/cache@v3
  with:
    path: vendor
    key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
```

### 5. Separate CI and CD

Keep code quality checks separate from deployments:

- CI workflow: Runs on all branches
- CD workflows: Run only on specific branches

### 6. Use Matrix Builds

Test multiple PHP versions:

```yaml
strategy:
  matrix:
    php-version: ['8.1', '8.2', '8.3']
```

### 7. Require CI Passing Before Deploy

```yaml
jobs:
  deploy:
    needs: [code-style, static-analysis, tests]
    # Only runs if all CI jobs pass
```

## Security Considerations

1. **Never Log Secrets**: Avoid logging sensitive data
2. **Use Secrets for Credentials**: Store API keys as repository secrets
3. **Limit Secret Access**: Use environment-specific secrets when possible
4. **Review Logs**: Check workflow logs don't expose credentials
5. **Require Reviews**: Use branch protection for production branches

## Monitoring

### Workflow Status Badges

Add to README.md:

```markdown
![CI](https://github.com/ulties/deployer-wip/workflows/CI/badge.svg)
![Deploy Production](https://github.com/ulties/deployer-wip/workflows/Deploy%20to%20Production/badge.svg)
```

### Deployment History

View deployment history:
1. Go to `Actions` tab
2. Filter by workflow name
3. View individual run logs

## Next Steps

- Understand the [provider system](./providers.md)
- Learn about [configuration options](./configuration.md)
- Read [development guide](./development.md) for contributing
