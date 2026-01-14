# Roadmap

This document lists Ploi.io features and configurations that are not yet supported in Shipper's configuration system. Features are organized by category and prioritized based on common use cases.

## Currently Supported Features

For reference, Shipper currently supports:
- ✅ Site creation and deployment
- ✅ Domain configuration
- ✅ Repository installation (GitHub, GitLab, Bitbucket, Custom)
- ✅ Web directory and project root configuration
- ✅ Branch-based deployments
- ✅ MySQL database creation and management
- ✅ Database user management
- ✅ Database linking to sites
- ✅ Site destruction
- ✅ Deployment monitoring and timeout configuration
- ✅ Deployment logs retrieval
- ✅ Configuration validation (plan/apply workflow)

## Missing Features

### 🔴 High Priority - Core Functionality

#### SSL/TLS Certificates
- [ ] Automatic SSL certificate provisioning via Let's Encrypt
- [ ] Custom SSL certificate upload
- [ ] SSL certificate renewal management
- [ ] Force HTTPS redirection
- [ ] SSL certificate monitoring and expiration alerts

#### Environment Variables
- [ ] Set site-specific environment variables via configuration
- [ ] Bulk environment variable updates
- [ ] Environment variable encryption/secrets management
- [ ] Environment variable inheritance (server → site)
- [ ] .env file management

#### Deployment Scripts
- [ ] Custom deployment script configuration
- [ ] Pre-deployment hooks
- [ ] Post-deployment hooks
- [ ] Deployment script templates
- [ ] Script validation before deployment

#### Multiple Domains/Aliases
- [ ] Configure multiple domains per site
- [ ] Domain aliases configuration
- [ ] Wildcard domain support
- [ ] Domain redirection rules
- [ ] www to non-www (or vice versa) redirection

### 🟡 Medium Priority - Enhanced Functionality

#### Queue Workers
- [ ] Configure Laravel queue workers
- [ ] Queue worker process management (supervisor)
- [ ] Multiple queue worker configurations
- [ ] Queue worker restart on deployment
- [ ] Custom queue worker parameters (--tries, --timeout, etc.)

#### Scheduled Jobs (Cron)
- [ ] Configure cron jobs for sites
- [ ] Cron job scheduling (minute, hourly, daily, etc.)
- [ ] Multiple cron job support
- [ ] Cron job logging
- [ ] Laravel scheduler integration

#### Database Features
- [ ] PostgreSQL database support
- [ ] Database backup configuration
- [ ] Automated database backup scheduling
- [ ] Database restore from backup
- [ ] Database user permissions/privileges customization
- [ ] Remote database connections
- [ ] Database import/export

#### Site Settings
- [ ] PHP version selection per site
- [ ] PHP-FPM configuration (memory_limit, max_execution_time, etc.)
- [ ] Web server configuration (Nginx/Apache)
- [ ] Site isolation/security settings
- [ ] Basic authentication (username/password for site access)
- [ ] IP whitelisting/blacklisting
- [ ] Custom Nginx configuration

#### Application Features
- [ ] Laravel-specific optimizations (opcache, config cache, etc.)
- [ ] Node.js version management
- [ ] NPM/Yarn build script execution
- [ ] Composer install options (--no-dev, --optimize-autoloader)
- [ ] Asset compilation during deployment
- [ ] Storage/cache directory permissions

### 🟢 Low Priority - Advanced Features

#### Monitoring & Notifications
- [ ] Site uptime monitoring
- [ ] SSL certificate expiration notifications
- [ ] Deployment success/failure notifications
- [ ] Webhook notifications (Slack, Discord, custom)
- [ ] Email notifications
- [ ] Performance monitoring integration

#### Server Management
- [ ] Multiple server support in single configuration
- [ ] Server selection per profile/environment
- [ ] Load balancer configuration
- [ ] Server health checks
- [ ] Server firewall rules
- [ ] Server-level software installation (Redis, Elasticsearch, etc.)

#### Backup & Recovery
- [ ] Automated file backups
- [ ] Backup retention policies
- [ ] Point-in-time recovery
- [ ] Backup storage configuration (S3, etc.)
- [ ] Disaster recovery procedures

#### Git & Repository
- [ ] Deploy key management
- [ ] Private repository access configuration
- [ ] Git submodule support
- [ ] Monorepo deployment strategies
- [ ] Tag-based deployments (not just branches)
- [ ] Deployment from specific commit SHA

#### Redirects & Routing
- [ ] Custom redirect rules
- [ ] Path-based routing
- [ ] Reverse proxy configuration
- [ ] Custom headers configuration
- [ ] CORS configuration

#### Cache & Performance
- [ ] Redis configuration and management
- [ ] Memcached configuration
- [ ] OPcache configuration
- [ ] CDN integration (CloudFlare, etc.)
- [ ] Static asset optimization
- [ ] HTTP/2 and HTTP/3 support

#### Security
- [ ] Firewall configuration per site
- [ ] Rate limiting configuration
- [ ] DDoS protection settings
- [ ] Security headers (CSP, HSTS, etc.)
- [ ] Fail2ban integration
- [ ] ModSecurity WAF rules

#### Logs & Debugging
- [ ] Application log viewing
- [ ] Error log management
- [ ] Access log configuration
- [ ] Log rotation settings
- [ ] Remote logging (Papertrail, Loggly, etc.)
- [ ] Debug mode toggle

### 🔵 Future Considerations

#### CI/CD Enhancements
- [ ] Multi-stage deployments (test → staging → production)
- [ ] Rollback functionality
- [ ] Blue-green deployments
- [ ] Canary deployments
- [ ] A/B testing support

#### Collaboration Features
- [ ] Team member management
- [ ] Role-based access control (RBAC)
- [ ] Deployment approvals workflow
- [ ] Audit logging
- [ ] Activity timeline

#### Database Clustering & Replication
- [ ] Master-slave database replication
- [ ] Database clustering configuration
- [ ] Read replica configuration
- [ ] Database connection pooling
- [ ] Automated failover

#### Container & Orchestration
- [ ] Docker container support
- [ ] Kubernetes integration
- [ ] Container registry configuration
- [ ] Microservices deployment patterns

#### Additional Application Stacks
- [ ] Static site generators (Hugo, Jekyll, Next.js, etc.)
- [ ] Python application support (Django, Flask)
- [ ] Ruby application support (Rails)
- [ ] Go application support
- [ ] Elixir/Phoenix support

## Implementation Notes

### Configuration Structure

As features are implemented, they should follow the existing configuration patterns:

```yaml
providers:
  ploi:
    # Provider-level configuration

projects:
  my-app:
    # Project-level configuration
    ssl:
      # SSL configuration
    environment:
      # Environment variables
    workers:
      # Queue workers
    cron:
      # Cron jobs
    profiles:
      production:
        # Profile-specific overrides
```

### Backward Compatibility

- All new features should be optional and maintain backward compatibility
- Default values should match Ploi.io defaults when possible
- Configuration validation should provide clear error messages
- Plan command should show what changes will be made

### Testing Strategy

- Each new feature should include unit tests
- Integration tests with Ploi.io API should be added
- Configuration validation tests for error cases
- Documentation should be updated with examples

## Contributing

If you'd like to contribute to implementing any of these features:

1. Open an issue to discuss the feature implementation
2. Follow the existing code patterns and strict typing standards
3. Add comprehensive tests
4. Update documentation
5. Submit a pull request

## Priority Notes

**High Priority** features are essential for most production deployments and should be implemented first.

**Medium Priority** features are commonly used and significantly enhance the deployment experience.

**Low Priority** features are nice-to-have for advanced use cases or specific scenarios.

**Future Considerations** are exploratory ideas that may or may not fit the project's scope.

## Feedback

If you have suggestions for additional features or want to reprioritize items on this roadmap, please open an issue or discussion on GitHub.
