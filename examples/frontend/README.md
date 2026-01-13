# Example Frontend Project

This is a placeholder for an example frontend project (e.g., Next.js, Vue, React).

## Structure

In a real deployment, this directory would contain:
- Frontend application files
- `package.json` for dependencies
- Build configuration
- Static assets
- Routing configuration

## Deployment

This project is deployed using the `shipper` CLI with the configuration in `shipper.yml`.

### Profiles

- **production**: Deployed from `main` branch to production server
- **staging**: Deployed from `develop` branch to staging server
- **preview**: Deployed from PR branches to preview server

### Commands

```bash
# Validate configuration
./shipper validate

# Plan deployment (dry-run)
./shipper plan frontend --profile=production

# Deploy to production
./shipper apply frontend --profile=production

# Deploy to staging
./shipper apply frontend --profile=staging
```
