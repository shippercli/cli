---
description: "How to wire Shipper into CI workflows for production deploys, preview environments, and cleanup jobs."
section: "Automation"
order: 70
---

# GitHub Actions Workflows

This page explains how to automate Shipper from GitHub Actions.

## Reusable Shipper Action

```yaml
- uses: shippercli/actions/.github/actions/shipper@f31a980b0c6d51b531735d4cd68b2268ad54d193
  with:
    command: apply
    project: api
    profile: production
    force: true
    providers: |
      shippercli/provider-cpanel:^1.0
  env:
    CPANEL_API_TOKEN: ${{ secrets.CPANEL_API_TOKEN }}
```

Supported inputs:

- `command`
- `project`
- `profile`
- `force`

Provider secrets vary by provider. Confirm the exact variable names on the provider page.

## Production Workflow Example

```yaml
name: Deploy Production

on:
  push:
    branches:
      - main

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shippercli/actions/.github/actions/shipper@f31a980b0c6d51b531735d4cd68b2268ad54d193
        with:
          command: apply
          project: api
          profile: production
          force: true
          providers: |
            shippercli/provider-cpanel:^1.0
        env:
          CPANEL_API_TOKEN: ${{ secrets.CPANEL_API_TOKEN }}
```

## Preview Workflow Example

```yaml
name: Deploy Preview

on:
  pull_request:
    types: [opened, synchronize, reopened]

jobs:
  preview:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shippercli/actions/.github/actions/shipper@f31a980b0c6d51b531735d4cd68b2268ad54d193
        with:
          command: apply
          project: api
          profile: preview
          force: true
          providers: |
            shippercli/provider-cpanel:^1.0
        env:
          CPANEL_API_TOKEN: ${{ secrets.CPANEL_API_TOKEN }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
```

## Cleanup Workflow Example

```yaml
name: Destroy Preview

on:
  pull_request:
    types: [closed]

jobs:
  cleanup:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shippercli/actions/.github/actions/shipper@f31a980b0c6d51b531735d4cd68b2268ad54d193
        with:
          command: destroy
          project: api
          profile: preview
          force: true
          providers: |
            shippercli/provider-cpanel:^1.0
        env:
          CPANEL_API_TOKEN: ${{ secrets.CPANEL_API_TOKEN }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
```

## Failure Modes

- wrong secret names for the selected provider
- missing PR context variables in preview workflows
- running destructive cleanup on the wrong profile
