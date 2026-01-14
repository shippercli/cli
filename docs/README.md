# Shipper Documentation

Welcome to the Shipper documentation! This comprehensive guide covers everything you need to know about deploying applications with Shipper.

## Quick Links

- [Main README](../README.md) - Overview and quick start guide
- [Roadmap](../ROADMAP.md) - Planned features and Ploi.io configurations not yet supported
- [Configuration Guide](./CONFIGURATION.md) - Complete shipper.yml configuration reference
- [PR Previews](./PR_PREVIEWS.md) - Set up preview environments for pull requests
- [Sites Management](./SITES.md) - Managing site lifecycle and deployment
- [Database Management](./DATABASES.md) - Database configuration and operations
- [GitHub Actions](./GITHUB_ACTIONS.md) - Automated deployments with GitHub Actions
- [GitHub Action Usage](./GITHUB_ACTION.md) - Using Shipper as a reusable GitHub Action
- [Build System](./BUILD_SYSTEM.md) - Understanding the build and release process
- [Strict Standards](./STRICT_STANDARDS.md) - Code quality and type safety standards

## Getting Started

### New to Shipper?

1. **[Read the main README](../README.md)** - Understand what Shipper is and its features
2. **[Configuration Guide](./CONFIGURATION.md)** - Learn how to configure `shipper.yml`
3. **[GitHub Actions Setup](./GITHUB_ACTIONS.md)** - Set up automated deployments

### Common Tasks

#### Setting Up Deployments

- [Configure shipper.yml](./CONFIGURATION.md) - Define your projects and profiles
- [Set up GitHub Actions workflows](./GITHUB_ACTIONS.md) - Automate your deployments
- [Configure PR previews](./PR_PREVIEWS.md) - Enable preview environments

#### Managing Resources

- [Create and manage sites](./SITES.md) - Site lifecycle management
- [Configure databases](./DATABASES.md) - Database setup and management
- [Deploy applications](./SITES.md#deployment-process) - Trigger deployments

#### Advanced Topics

- [Use Shipper as a GitHub Action](./GITHUB_ACTION.md) - Reusable action integration
- [Understanding the build system](./BUILD_SYSTEM.md) - Binary building and releases
- [Code quality standards](./STRICT_STANDARDS.md) - Type safety and best practices

## Documentation Structure

### Configuration

**[CONFIGURATION.md](./CONFIGURATION.md)** - Complete configuration guide
- Provider configuration (Ploi)
- Project setup
- Profile configuration
- Database configuration
- Environment variables
- Complete examples
- Best practices

### PR Previews

**[PR_PREVIEWS.md](./PR_PREVIEWS.md)** - Preview deployment guide
- How preview deployments work
- Configuration for previews
- GitHub Actions workflows
- Domain strategy
- Database management
- Automatic cleanup
- Troubleshooting

### Sites Management

**[SITES.md](./SITES.md)** - Site lifecycle guide
- Site creation and updates
- Domain configuration
- Repository setup
- Deployment process
- Site commands (validate, plan, apply, destroy)
- Multiple projects
- Security and monitoring

### Database Management

**[DATABASES.md](./DATABASES.md)** - Database features guide
- Database configuration
- Variable interpolation
- Database lifecycle
- Password management
- Multiple databases
- Migrations
- Backups and restore
- Preview databases

### GitHub Actions

**[GITHUB_ACTIONS.md](./GITHUB_ACTIONS.md)** - CI/CD automation guide
- Production deployments
- Staging deployments
- Preview deployments
- Cleanup workflows
- Workflow patterns
- Notifications
- Security best practices

### GitHub Action

**[GITHUB_ACTION.md](./GITHUB_ACTION.md)** - Reusable action guide
- Using Shipper as a GitHub Action
- Action inputs and outputs
- Usage examples
- Version management
- Migration guide
- Troubleshooting

### Build System

**[BUILD_SYSTEM.md](./BUILD_SYSTEM.md)** - Build and release guide
- How the binary is built
- Box configuration
- Release process
- Distribution methods
- Reusable GitHub Action
- Version management

### Strict Standards

**[STRICT_STANDARDS.md](./STRICT_STANDARDS.md)** - Code quality guide
- Type safety rules
- PHPStan configuration
- Laravel Pint setup
- Testing with Pest
- Best practices
- Continuous integration

## Key Features

### Declarative Configuration

Define your entire deployment infrastructure in `shipper.yml`:
- Multiple projects
- Multiple environments (production, staging, preview)
- Database configurations
- Provider settings

### Plan and Apply Workflow

Like infrastructure-as-code tools:
1. **Plan**: Preview what changes will be made
2. **Apply**: Execute the deployment
3. **Validate**: Check configuration before deploying

### Automatic Resource Management

Shipper handles:
- Site creation and configuration
- Database provisioning
- SSL certificates
- Environment variables
- Deployment tracking

### GitHub Actions Integration

- Automated deployments on push
- PR preview environments
- Automatic cleanup
- Deployment status reporting

## Support and Resources

### Getting Help

- **Issues**: Report bugs or request features on [GitHub Issues](https://github.com/ulties/shipper/issues)
- **Discussions**: Ask questions on [GitHub Discussions](https://github.com/ulties/shipper/discussions)
- **Documentation**: Browse this comprehensive documentation

### Contributing

Contributions are welcome! See the main repository for contribution guidelines.

### License

Shipper is open-source software licensed under the MIT license.

## Common Workflows

### Setting Up a New Project

1. Create `shipper.yml` in your repository root
2. Configure provider credentials
3. Define your project and profiles
4. Add GitHub Actions workflows
5. Set repository secrets (PLOI_API_KEY)
6. Push to trigger first deployment

**Example:** See [Configuration Guide](./CONFIGURATION.md#complete-example)

### Adding PR Previews

1. Add preview profile to project configuration
2. Configure preview domain pattern
3. Add preview database configuration
4. Create preview deployment workflow
5. Create cleanup workflow
6. Test with a pull request

**Example:** See [PR Previews Guide](./PR_PREVIEWS.md#configuration)

### Deploying Multiple Projects

1. Define multiple projects in `shipper.yml`
2. Use matrix strategy in workflows
3. Deploy all projects or specific ones
4. Monitor deployment status

**Example:** See [Sites Management](./SITES.md#multiple-projects)

## Troubleshooting

### Configuration Issues

- [Configuration validation](./CONFIGURATION.md#validation)
- [Common configuration errors](./CONFIGURATION.md#troubleshooting)

### Deployment Issues

- [Site deployment failures](./SITES.md#troubleshooting)
- [Database connection errors](./DATABASES.md#troubleshooting)

### GitHub Actions Issues

- [Workflow not triggering](./GITHUB_ACTIONS.md#troubleshooting)
- [Permission errors](./GITHUB_ACTIONS.md#troubleshooting)

## Best Practices

1. **Always validate configuration** before deploying
2. **Use plan command** to preview changes
3. **Enable PR previews** for better testing
4. **Automate cleanup** of preview environments
5. **Use environment variables** for secrets
6. **Pin versions** in production
7. **Monitor deployments** for issues
8. **Backup databases** before major changes
9. **Test in staging** before production
10. **Document your setup** for your team

## Updates and Changelog

Check the [releases page](https://github.com/ulties/shipper/releases) for:
- New features
- Bug fixes
- Breaking changes
- Migration guides

## Next Steps

Choose your path:

**New User?**
→ [Configuration Guide](./CONFIGURATION.md)

**Setting up CI/CD?**
→ [GitHub Actions Guide](./GITHUB_ACTIONS.md)

**Need PR Previews?**
→ [PR Previews Guide](./PR_PREVIEWS.md)

**Managing Databases?**
→ [Database Guide](./DATABASES.md)

**Want to Contribute?**
→ [Strict Standards](./STRICT_STANDARDS.md)
