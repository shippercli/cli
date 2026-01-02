# Example API Project

This is a placeholder for an example Laravel API project.

## Structure

In a real deployment, this directory would contain:
- Laravel application files
- `composer.json` for dependencies
- `.env.example` for environment configuration
- Database migrations
- API routes and controllers

## Deployment

This project is deployed using the `deployer` CLI with the configuration in `deployer.yml`.

### Profiles

- **production**: Deployed from `main` branch to production server
- **staging**: Deployed from `develop` branch to staging server
- **preview**: Deployed from PR branches to preview server

### Commands

```bash
# Validate configuration
./deployer validate

# Plan deployment (dry-run)
./deployer plan api --profile=production

# Deploy to production
./deployer apply api --profile=production

# Deploy to staging
./deployer apply api --profile=staging
```
