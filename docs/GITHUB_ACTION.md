# Using Shipper as a GitHub Action

This guide explains how to use Shipper as a reusable GitHub Action in your workflows.

## Overview

The Shipper GitHub Action provides a simple way to integrate Shipper deployments into your GitHub Actions workflows without needing to manually install PHP, Composer, or build the Shipper CLI.

**Benefits:**
- No PHP or Composer setup required
- Automatic binary download from releases
- Simple configuration
- Consistent versioning
- Reusable across multiple workflows

## Quick Start

```yaml
name: Deploy

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

## Action Location

The Shipper CLI action is available at:
```
ulties/shipper/.github/actions/shipper-cli@main
```

**Reference Options:**
- `@main` - Latest development version
- `@v1.0.0` - Specific release version (recommended for production)
- `@939e086` - Specific commit hash

## Inputs

### Required Inputs

#### `command`

The Shipper command to execute.

**Type:** String  
**Required:** Yes  
**Options:** `validate`, `plan`, `apply`, `destroy`

**Examples:**
```yaml
command: validate  # Validate configuration
command: plan      # Dry-run deployment
command: apply     # Execute deployment
command: destroy   # Remove site and databases
```

### Optional Inputs

#### `project`

Project name from `shipper.yml` to deploy.

**Type:** String  
**Required:** No (required for plan/apply/destroy commands)  
**Default:** None

**Example:**
```yaml
project: api
```

#### `profile`

Deployment profile to use (production, staging, preview).

**Type:** String  
**Required:** No (required for plan/apply/destroy commands)  
**Default:** None

**Example:**
```yaml
profile: production
```

#### `force`

Skip confirmation prompts. Always use `true` in CI/CD.

**Type:** Boolean  
**Required:** No  
**Default:** `false`

**Example:**
```yaml
force: true  # Recommended for CI/CD
```

#### `version`

Shipper CLI version to use.

**Type:** String  
**Required:** No  
**Default:** `latest`

**Examples:**
```yaml
version: latest  # Latest release
version: v1.0.0  # Specific version
```

#### `working-directory`

Directory containing `shipper.yml`.

**Type:** String  
**Required:** No  
**Default:** `.` (repository root)

**Example:**
```yaml
working-directory: ./infrastructure
```

## Outputs

### `exit-code`

Exit code from the Shipper command.

**Type:** String  
**Values:** `0` (success), non-zero (failure)

**Usage:**
```yaml
- name: Deploy
  id: deploy
  uses: ulties/shipper/.github/actions/shipper-cli@main
  with:
    command: apply
    project: api
    profile: production
    force: true
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}

- name: Check deployment result
  if: steps.deploy.outputs.exit-code == '0'
  run: echo "Deployment successful!"
```

## Usage Examples

### Validate Configuration

```yaml
- name: Validate Configuration
  uses: ulties/shipper/.github/actions/shipper-cli@main
  with:
    command: validate
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### Plan Deployment

```yaml
- name: Plan Deployment
  uses: ulties/shipper/.github/actions/shipper-cli@main
  with:
    command: plan
    project: api
    profile: production
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### Deploy to Production

```yaml
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

### Deploy PR Preview

```yaml
- name: Deploy Preview
  uses: ulties/shipper/.github/actions/shipper-cli@main
  with:
    command: apply
    project: api
    profile: preview
    force: true
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
    GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
    GITHUB_HEAD_REF: ${{ github.head_ref }}
```

### Destroy Preview Environment

```yaml
- name: Cleanup Preview
  uses: ulties/shipper/.github/actions/shipper-cli@main
  with:
    command: destroy
    project: api
    profile: preview
    force: true
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
    GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
    GITHUB_HEAD_REF: ${{ github.event.pull_request.head.ref }}
```

### Use Specific Version

```yaml
- name: Deploy with Specific Version
  uses: ulties/shipper/.github/actions/shipper-cli@v1.0.0
  with:
    command: apply
    project: api
    profile: production
    version: v1.0.0
    force: true
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### Custom Working Directory

```yaml
- name: Deploy from Subdirectory
  uses: ulties/shipper/.github/actions/shipper-cli@main
  with:
    command: apply
    project: api
    profile: production
    working-directory: ./deployment
    force: true
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

## Complete Workflow Examples

### Production Deployment

```yaml
name: Deploy Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        project: [api, frontend]
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Validate
        uses: ulties/shipper/.github/actions/shipper-cli@main
        with:
          command: validate
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
      
      - name: Deploy ${{ matrix.project }}
        uses: ulties/shipper/.github/actions/shipper-cli@main
        with:
          command: apply
          project: ${{ matrix.project }}
          profile: production
          force: true
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### PR Preview with Cleanup

```yaml
name: PR Preview

on:
  pull_request:
    types: [opened, synchronize, closed]
    branches: [main]

permissions:
  contents: read
  pull-requests: write

jobs:
  deploy:
    if: github.event.action != 'closed'
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy Preview
        uses: ulties/shipper/.github/actions/shipper-cli@main
        with:
          command: apply
          project: api
          profile: preview
          force: true
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
      
      - name: Comment PR
        uses: actions/github-script@v7
        with:
          script: |
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: '🚀 Preview deployed! https://api-preview-${{ github.event.pull_request.number }}.example.com'
            })
  
  cleanup:
    if: github.event.action == 'closed'
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Cleanup Preview
        uses: ulties/shipper/.github/actions/shipper-cli@main
        with:
          command: destroy
          project: api
          profile: preview
          force: true
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
          GITHUB_HEAD_REF: ${{ github.event.pull_request.head.ref }}
```

### Manual Deployment Trigger

```yaml
name: Manual Deploy

on:
  workflow_dispatch:
    inputs:
      project:
        description: 'Project to deploy'
        required: true
        type: choice
        options: [api, frontend, admin]
      profile:
        description: 'Deployment profile'
        required: true
        type: choice
        options: [production, staging]
      plan-only:
        description: 'Plan only (dry-run)'
        type: boolean
        default: false

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: ${{ inputs.plan-only && 'Plan' || 'Deploy' }} ${{ inputs.project }}
        uses: ulties/shipper/.github/actions/shipper-cli@main
        with:
          command: ${{ inputs.plan-only && 'plan' || 'apply' }}
          project: ${{ inputs.project }}
          profile: ${{ inputs.profile }}
          force: true
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

## Environment Variables

### Required Environment Variables

#### PLOI_API_KEY

Your Ploi API key for authenticating with Ploi.

**Setup:**
1. Go to repository Settings → Secrets and variables → Actions
2. Add secret named `PLOI_API_KEY`
3. Set value to your Ploi API key

**Usage:**
```yaml
env:
  PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

### Optional Environment Variables

#### GITHUB_PR_NUMBER

Pull request number for preview deployments.

**Usage:**
```yaml
env:
  GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
```

#### GITHUB_HEAD_REF

Branch name for preview deployments.

**Usage:**
```yaml
env:
  GITHUB_HEAD_REF: ${{ github.head_ref }}
```

#### Custom Variables

Any custom environment variables used in your `shipper.yml`:

```yaml
env:
  PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
  CUSTOM_VAR: ${{ secrets.CUSTOM_VAR }}
  ENVIRONMENT: production
```

## How It Works

The Shipper GitHub Action:

1. **Downloads Binary**: Downloads the Shipper CLI binary from GitHub releases
2. **Verifies Binary**: Checks the binary is executable and valid
3. **Sets Working Directory**: Changes to specified directory
4. **Executes Command**: Runs the Shipper command with provided arguments
5. **Returns Exit Code**: Outputs the command's exit code

**No PHP/Composer Required**: The action handles everything!

## Version Management

### Using Latest Version

```yaml
uses: ulties/shipper/.github/actions/shipper-cli@main
with:
  version: latest  # or omit version input
```

**Pros:**
- Always get latest features and fixes
- Simple configuration

**Cons:**
- Less predictable
- May introduce breaking changes

### Using Specific Version

```yaml
uses: ulties/shipper/.github/actions/shipper-cli@v1.0.0
with:
  version: v1.0.0
```

**Pros:**
- Predictable behavior
- No surprise changes
- Recommended for production

**Cons:**
- Must manually update

### Pinning Action Version

Pin the action reference for maximum stability:

```yaml
uses: ulties/shipper/.github/actions/shipper-cli@v1.0.0
with:
  command: apply
  project: api
  profile: production
  version: v1.0.0  # Also pin CLI version
  force: true
```

## Troubleshooting

### Binary Download Fails

**Error:** "Failed to download or make shipper executable"

**Solutions:**
1. Check internet connectivity in GitHub Actions runner
2. Verify the release exists at specified version
3. Check GitHub Actions has permission to download from releases

### Command Not Found

**Error:** "Command 'X' is not defined"

**Solutions:**
1. Verify command name is correct (validate, plan, apply, destroy)
2. Check spelling and case sensitivity

### Missing Required Input

**Error:** "Input required and not supplied: X"

**Solutions:**
1. Add the required input (project, profile)
2. Verify the command requires those inputs

### Configuration Not Found

**Error:** "Configuration file not found"

**Solutions:**
1. Ensure `shipper.yml` exists in repository
2. Check `working-directory` input is correct
3. Verify checkout action runs before Shipper action

### Permission Denied

**Error:** "Permission denied"

**Solutions:**
1. Verify `PLOI_API_KEY` secret is set
2. Check API key has required permissions
3. Verify workflow permissions are adequate

## Best Practices

1. **Always Use Force**: Set `force: true` in automated workflows
2. **Pin Versions**: Use specific versions for production deployments
3. **Validate First**: Run `validate` before `apply` commands
4. **Use Secrets**: Store API keys in GitHub secrets
5. **Check Exit Codes**: Use outputs to check command success
6. **Comment PRs**: Update PRs with deployment status
7. **Clean Up**: Always clean up preview environments
8. **Matrix Strategy**: Deploy multiple projects in parallel
9. **Minimal Permissions**: Only grant necessary workflow permissions
10. **Test Locally**: Test with Shipper CLI locally first

## Advantages Over Manual Setup

**Traditional Approach:**
```yaml
- name: Setup PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: '8.3'

- name: Install Composer dependencies
  run: composer install --no-dev

- name: Deploy
  run: ./shipper apply api --profile=production --force
```

**Using Shipper Action:**
```yaml
- name: Deploy
  uses: ulties/shipper/.github/actions/shipper-cli@main
  with:
    command: apply
    project: api
    profile: production
    force: true
```

**Benefits:**
- ✅ Fewer steps
- ✅ Faster execution (no PHP/Composer setup)
- ✅ Consistent binary version
- ✅ Simpler configuration
- ✅ Less maintenance

## Security Considerations

1. **Pin Action Versions**: Use specific versions in production
2. **Store Secrets Securely**: Use GitHub secrets for API keys
3. **Minimal Permissions**: Only grant required permissions
4. **Review Changes**: Review action updates before adopting
5. **Limit Access**: Restrict who can trigger workflows

## Migrating from CLI to Action

**Before:**
```yaml
steps:
  - uses: actions/checkout@v4
  - name: Setup PHP
    uses: shivammathur/setup-php@v2
    with:
      php-version: '8.3'
  - run: composer install --no-dev
  - run: ./shipper apply api --profile=production --force
    env:
      PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

**After:**
```yaml
steps:
  - uses: actions/checkout@v4
  - uses: ulties/shipper/.github/actions/shipper-cli@main
    with:
      command: apply
      project: api
      profile: production
      force: true
    env:
      PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

## Next Steps

- [GitHub Actions Setup](./GITHUB_ACTIONS.md) - Complete workflow examples
- [Configuration Guide](./CONFIGURATION.md) - Configure shipper.yml
- [PR Previews](./PR_PREVIEWS.md) - Set up preview deployments
- [Build System](./BUILD_SYSTEM.md) - How releases are built
