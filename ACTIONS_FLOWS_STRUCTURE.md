# Actions and Flows Organization

This document describes the organizational structure of Actions and Flows in the Shipper application.

## Overview

Actions and Flows are now organized into logical subdirectories to improve code organization and maintainability.

## Actions Structure

Actions are grouped by their primary responsibility:

### `app/Actions/Server/`
Server and site management operations (Ploi-specific):
- `DeleteSiteAction.php` - Delete a site from the server
- `DestroySiteAction.php` - Destroy a site deployment
- `GetAllSitesAction.php` - Retrieve all sites from the server
- `GetDeploymentLogsAction.php` - Fetch deployment logs for a site

### `app/Actions/Deployment/`
Core deployment operations:
- `CreateDeploymentPlanAction.php` - Create a deployment plan
- `ExecuteDeploymentAction.php` - Execute a deployment
- `ValidateProjectAction.php` - Validate project configuration

### `app/Actions/Configuration/`
Configuration management:
- `LoadConfigurationAction.php` - Load and parse configuration files

### `app/Actions/GitHub/`
GitHub integration operations:
- `GetOpenPullRequestsAction.php` - Fetch open pull requests from GitHub

### `app/Actions/Cleanup/`
Maintenance and cleanup operations:
- `FindOrphanedSitesAction.php` - Identify orphaned preview sites

## Flows Structure

Flows orchestrate multiple actions to accomplish complex tasks:

### `app/Flows/Deployment/`
Deployment workflow orchestration:
- `ApplyDeploymentFlow.php` - Complete deployment workflow (plan + execute)
- `PlanDeploymentFlow.php` - Deployment planning workflow
- `DestroyDeploymentFlow.php` - Site destruction workflow

### `app/Flows/Maintenance/`
Maintenance and cleanup workflows:
- `CleanupOrphanedSitesFlow.php` - Cleanup workflow for orphaned sites

### `app/Flows/Validation/`
Configuration validation workflows:
- `ValidateConfigurationFlow.php` - Configuration validation workflow

## Namespaces

All classes follow PSR-4 autoloading standards:

### Actions
- `App\Actions\Server\*`
- `App\Actions\Deployment\*`
- `App\Actions\Configuration\*`
- `App\Actions\GitHub\*`
- `App\Actions\Cleanup\*`

### Flows
- `App\Flows\Deployment\*`
- `App\Flows\Maintenance\*`
- `App\Flows\Validation\*`

## Usage Examples

### Importing Actions
```php
use App\Actions\Server\DeleteSiteAction;
use App\Actions\Deployment\ExecuteDeploymentAction;
use App\Actions\Configuration\LoadConfigurationAction;
```

### Importing Flows
```php
use App\Flows\Deployment\ApplyDeploymentFlow;
use App\Flows\Validation\ValidateConfigurationFlow;
```

## Adding New Actions or Flows

When adding new Actions or Flows:

1. Determine the appropriate category (Server, Deployment, Configuration, etc.)
2. Create the class in the corresponding directory
3. Use the appropriate namespace (e.g., `namespace App\Actions\Server;`)
4. If creating a new category, create a new subdirectory and update this documentation

## Benefits

This organization provides:

- **Clear categorization** - Easy to find related functionality
- **Better maintainability** - Logical grouping reduces cognitive load
- **Scalability** - New actions can be added to appropriate categories
- **Namespace clarity** - Import statements clearly indicate the action's purpose
