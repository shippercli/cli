# Example: Using Shipper CLI in GitHub Actions

This directory contains example workflows demonstrating how to use the Shipper CLI action in your own repositories.

## Basic Usage

```yaml
name: Deploy with Shipper

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Validate Configuration
        uses: ulties/deployer-wip/.github/actions/shipper-cli@main
        with:
          command: validate
      
      - name: Deploy to Production
        uses: ulties/deployer-wip/.github/actions/shipper-cli@main
        with:
          command: apply
          project: api
          profile: production
          force: true
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

## Multi-Project Deployment

```yaml
name: Deploy Multiple Projects

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        project: [api, frontend]
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy ${{ matrix.project }}
        uses: ulties/deployer-wip/.github/actions/shipper-cli@main
        with:
          command: apply
          project: ${{ matrix.project }}
          profile: production
          force: true
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

## Preview Deployments for Pull Requests

```yaml
name: Preview Deployment

on:
  pull_request:
    types: [opened, synchronize]

jobs:
  preview:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Plan Preview Deployment
        uses: ulties/deployer-wip/.github/actions/shipper-cli@main
        with:
          command: plan
          project: api
          profile: preview
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
      
      - name: Deploy Preview
        uses: ulties/deployer-wip/.github/actions/shipper-cli@main
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

## Using a Specific Version

```yaml
- name: Deploy with Specific Version
  uses: ulties/deployer-wip/.github/actions/shipper-cli@main
  with:
    command: apply
    project: api
    profile: production
    version: v1.0.0  # Use a specific release
    force: true
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

## Custom Working Directory

If your `shipper.yml` is not in the repository root:

```yaml
- name: Deploy from Subdirectory
  uses: ulties/deployer-wip/.github/actions/shipper-cli@main
  with:
    command: apply
    project: api
    profile: production
    working-directory: ./infrastructure
    force: true
  env:
    PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

## Available Commands

- `validate`: Validate the shipper.yml configuration
- `plan`: Show what changes would be made (dry-run)
- `apply`: Execute the deployment

## Required Environment Variables

Most commands require the following environment variables:

- `PLOI_API_KEY`: Your Ploi API key

For preview deployments, you may also need:

- `GITHUB_PR_NUMBER`: Pull request number
- `GITHUB_HEAD_REF`: Branch name for the PR

## Action Inputs

| Input | Required | Default | Description |
|-------|----------|---------|-------------|
| `command` | Yes | - | The shipper command to run (validate, plan, apply) |
| `project` | No | - | The project name from shipper.yml |
| `profile` | No | - | The deployment profile (production, staging, preview) |
| `force` | No | false | Skip confirmation prompts |
| `version` | No | latest | Version of shipper CLI to use |
| `working-directory` | No | . | Directory containing shipper.yml |

## Action Outputs

| Output | Description |
|--------|-------------|
| `exit-code` | Exit code from the shipper command |
