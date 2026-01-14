# Database Management Guide

This guide explains how Shipper manages databases for your applications.

## Overview

Shipper provides automatic database lifecycle management:
- Creating databases with secure passwords
- Managing database users
- Linking databases to sites
- Variable interpolation for dynamic names
- Automatic cleanup on site destruction

## Database Configuration

### Basic Configuration

Define databases in your project configuration:

```yaml
projects:
  api:
    databases:
      main:
        name: "myapp_production"
        user: "myapp_production"
        type: mysql
```

### Multiple Databases

Configure multiple databases per project:

```yaml
databases:
  main:
    name: "myapp_production"
    user: "myapp_production"
    type: mysql
  cache:
    name: "myapp_cache"
    user: "myapp_cache"
    type: mysql
  analytics:
    name: "myapp_analytics"
    user: "myapp_analytics"
    type: mysql
```

### Database Options

- `name` (required): Database name (supports variable interpolation)
- `user` (required): Database username (supports variable interpolation)
- `type` (required): Database type (`mysql` currently supported)

## Variable Interpolation

Use variables to create dynamic database names for different environments.

### Available Variables

**Built-in Variables:**
- `${PROJECT_NAME}`: Project name from configuration (e.g., `api`)
- `${PROFILE}`: Deployment profile (e.g., `production`, `staging`, `preview`)

**Environment Variables:**
- `${GITHUB_PR_NUMBER}`: Pull request number (for PR previews)
- `${GITHUB_HEAD_REF}`: Branch name (for PR previews)
- Any custom environment variable

### Variable Examples

**Pattern:** `myapp_${PROJECT_NAME}_${PROFILE}`

**Results:**
- Project `api`, Profile `production`: `myapp_api_production`
- Project `frontend`, Profile `staging`: `myapp_frontend_staging`

**Pattern:** `myapp_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}`

**Results:**
- Project `api`, Profile `preview`, PR #123: `myapp_api_preview_123`
- Project `api`, Profile `preview`, PR #456: `myapp_api_preview_456`

### Variable Handling

**Empty Variables:**
- Undefined environment variables are treated as empty strings
- Example: `myapp_${UNDEFINED}` becomes `myapp_`

**Cleanup Rules:**
- Trailing underscores are removed
- Multiple consecutive underscores are collapsed to one
- Example: `myapp__test___` becomes `myapp_test`

### Configuration Examples

**Production Database:**
```yaml
databases:
  main:
    name: "myapp_${PROJECT_NAME}_${PROFILE}"
    user: "myapp_${PROJECT_NAME}_${PROFILE}"
    type: mysql
```

**Preview Database (PR-specific):**
```yaml
databases:
  main:
    name: "myapp_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
    user: "myapp_${PROJECT_NAME}_${PROFILE}_${GITHUB_PR_NUMBER}"
    type: mysql
```

**Environment-specific:**
```yaml
databases:
  main:
    name: "myapp_${ENV}_${PROJECT_NAME}"
    user: "myapp_${ENV}_${PROJECT_NAME}"
    type: mysql
```

## Database Lifecycle

### 1. Creation

When running `shipper apply`, for each configured database:

1. **Check if exists**: Query Ploi for database by name
2. **Create database**: If doesn't exist, create with:
   - Specified name (with variables resolved)
   - Secure random password (32 characters)
   - Specified user
3. **Link to site**: Associate database with the site
4. **Set environment variables**: Inject credentials into site environment

### 2. Updates

For existing databases:
- Database is found by name
- No changes are made to existing databases
- Still linked to site if not already linked

### 3. Destruction

When running `shipper destroy`:

1. **Find all databases**: Get databases linked to the site
2. **Remove links**: Unlink databases from site
3. **Delete databases**: Permanently delete each database
4. **Delete users**: Remove database users

**Warning:** This is permanent and cannot be undone!

## Password Management

### Automatic Password Generation

- Passwords are automatically generated (32 characters)
- Includes uppercase, lowercase, numbers, and special characters
- Stored securely in Ploi
- Injected into site environment variables

### Accessing Passwords

**Via Ploi Dashboard:**
1. Navigate to Databases section
2. Click on database name
3. View password

**Via Environment Variables:**
Database credentials are automatically set in site environment:
- `DB_HOST`: Database host
- `DB_PORT`: Database port  
- `DB_DATABASE`: Database name
- `DB_USERNAME`: Database user
- `DB_PASSWORD`: Database password

**In Laravel `.env`:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp_production
DB_USERNAME=myapp_production
DB_PASSWORD=auto_generated_password
```

### Manual Password Changes

To change a database password:
1. Update password in Ploi dashboard
2. Update site environment variables
3. Redeploy the site

## Database Types

### MySQL

Currently, Shipper supports MySQL databases:

```yaml
databases:
  main:
    type: mysql
```

**Default Configuration:**
- Port: 3306
- Host: localhost (same server as site)
- Charset: utf8mb4
- Collation: utf8mb4_unicode_ci

### Future Database Types

Planned support for:
- PostgreSQL
- MariaDB
- SQLite (file-based)

## Multi-Database Scenarios

### Separate Read/Write Databases

```yaml
databases:
  write:
    name: "myapp_${PROFILE}_write"
    user: "myapp_${PROFILE}_write"
    type: mysql
  read:
    name: "myapp_${PROFILE}_read"
    user: "myapp_${PROFILE}_read"
    type: mysql
```

**In Laravel config/database.php:**
```php
'mysql' => [
    'write' => [
        'host' => env('DB_WRITE_HOST'),
        'database' => env('DB_WRITE_DATABASE'),
        'username' => env('DB_WRITE_USERNAME'),
        'password' => env('DB_WRITE_PASSWORD'),
    ],
    'read' => [
        'host' => env('DB_READ_HOST'),
        'database' => env('DB_READ_DATABASE'),
        'username' => env('DB_READ_USERNAME'),
        'password' => env('DB_READ_PASSWORD'),
    ],
],
```

### Microservices Architecture

```yaml
projects:
  users-service:
    databases:
      users:
        name: "users_${PROFILE}"
        user: "users_${PROFILE}"
        type: mysql
  
  orders-service:
    databases:
      orders:
        name: "orders_${PROFILE}"
        user: "orders_${PROFILE}"
        type: mysql
```

### Cache Database

```yaml
databases:
  main:
    name: "myapp_${PROFILE}"
    user: "myapp_${PROFILE}"
    type: mysql
  cache:
    name: "myapp_cache_${PROFILE}"
    user: "myapp_cache_${PROFILE}"
    type: mysql
```

## Database Migrations

### Running Migrations

Add to your deployment script (`.ploi/deploy.sh`):

```bash
#!/bin/bash
set -e

# Run migrations
php artisan migrate --force

# Seed data (only for non-production)
if [ "$PLOI_DOMAIN" != "api.example.com" ]; then
  php artisan db:seed --force
fi
```

### Migration Best Practices

1. **Always use `--force`**: Required for production environments
2. **Test locally first**: Run migrations locally before deploying
3. **Use transactions**: Wrap migrations in database transactions
4. **Backup first**: Backup production databases before major migrations
5. **Rollback plan**: Have a rollback strategy ready

### Zero-Downtime Migrations

For production deployments:

1. **Additive changes first**: Add new columns/tables
2. **Deploy code**: Deploy new code that works with both old and new schema
3. **Remove old columns**: In a later deployment, remove old columns

**Example:**

**Step 1 - Add new column:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('email_verified')->nullable();
});
```

**Step 2 - Deploy code that uses new column**

**Step 3 - Remove old column (later):**
```php
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('old_email_field');
});
```

## Database Backups

### Manual Backups

**Via Ploi Dashboard:**
1. Navigate to Databases
2. Select database
3. Click "Backup"

**Via CLI (on server):**
```bash
mysqldump -u username -p database_name > backup.sql
```

### Automated Backups

**Configure in Ploi:**
1. Navigate to Server → Backups
2. Enable automated backups
3. Set schedule (daily/weekly)
4. Configure retention period

**Custom Backup Script:**
```bash
#!/bin/bash
# backup-db.sh

DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="myapp_production"
BACKUP_DIR="/backups"

mysqldump -u root -p${DB_PASSWORD} ${DB_NAME} | gzip > ${BACKUP_DIR}/${DB_NAME}_${DATE}.sql.gz

# Remove backups older than 30 days
find ${BACKUP_DIR} -name "*.sql.gz" -mtime +30 -delete
```

### Restoring Backups

**From SQL file:**
```bash
mysql -u username -p database_name < backup.sql
```

**From compressed backup:**
```bash
gunzip < backup.sql.gz | mysql -u username -p database_name
```

## Preview Database Management

### Automatic Preview Databases

For PR previews, use dynamic naming:

```yaml
databases:
  main:
    name: "myapp_${PROJECT_NAME}_preview_${GITHUB_PR_NUMBER}"
    user: "myapp_${PROJECT_NAME}_preview_${GITHUB_PR_NUMBER}"
    type: mysql
```

**Benefits:**
- Each PR gets its own database
- No conflicts between PRs
- Automatic cleanup on PR close

### Seeding Preview Data

Add test data seeding to deployment script:

```bash
#!/bin/bash
# .ploi/deploy.sh

# Run migrations
php artisan migrate --force

# Seed test data for previews
if [[ "$PLOI_DOMAIN" == *"preview"* ]]; then
  php artisan db:seed --force
fi
```

### Preview Database Cleanup

When PR is closed, the cleanup workflow runs:

```bash
./shipper destroy api --profile=preview --force
```

This automatically:
1. Deletes the preview site
2. Deletes the preview database
3. Removes database user

## Troubleshooting

### Database Creation Fails

**Error:** "Database name already exists"
- **Solution:** Database name must be unique. Use different pattern or variables.

**Error:** "Invalid database name"
- **Solution:** Database names can only contain letters, numbers, and underscores.

**Error:** "Permission denied"
- **Solution:** Verify Ploi API key has database management permissions.

### Connection Failures

**Error:** "Access denied for user"
- **Solution:** Verify database credentials in environment variables.

**Error:** "Unknown database"
- **Solution:** Database wasn't created. Check Shipper logs and Ploi dashboard.

**Error:** "Too many connections"
- **Solution:** Increase max connections in MySQL config or optimize connection pooling.

### Migration Failures

**Error:** "Syntax error in migration"
- **Solution:** Test migration locally before deploying.

**Error:** "Table already exists"
- **Solution:** Check migration status with `php artisan migrate:status`.

**Error:** "Migration timeout"
- **Solution:** Break large migrations into smaller chunks.

## Best Practices

1. **Use Variable Interpolation**: Always use variables for environment-specific names
2. **Consistent Naming**: Use consistent patterns across all databases
3. **Backup Regularly**: Enable automated backups for production
4. **Test Migrations**: Test migrations on staging before production
5. **Monitor Database Size**: Track database growth and optimize queries
6. **Use Transactions**: Wrap data modifications in transactions
7. **Clean Up Previews**: Always clean up preview databases
8. **Document Schema**: Keep database schema documented
9. **Index Properly**: Add database indexes for performance
10. **Secure Credentials**: Never commit database credentials to code

## Database Monitoring

### Performance Monitoring

**Key Metrics:**
- Query execution time
- Slow query log
- Connection pool usage
- Database size growth

**Tools:**
- MySQL Performance Schema
- Slow query log analysis
- Ploi monitoring dashboard
- Third-party monitoring (DataDog, New Relic)

### Query Optimization

**Laravel Query Debugging:**
```php
// Enable query log
DB::enableQueryLog();

// Your queries
User::where('active', true)->get();

// Dump queries
dd(DB::getQueryLog());
```

**Identify Slow Queries:**
```bash
# Enable slow query log in MySQL
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

# View slow queries
tail -f /var/log/mysql/slow-query.log
```

## Advanced Topics

### Database Replication

For high-availability setups:

1. Configure master-slave replication
2. Update connection config in Laravel
3. Use read replicas for queries
4. Write to master only

### Connection Pooling

Optimize database connections:

```php
// config/database.php
'options' => [
    PDO::ATTR_PERSISTENT => true,
],
```

### Database Sharding

For large-scale applications:

1. Partition data across multiple databases
2. Use consistent hashing for shard selection
3. Implement in application layer

## Next Steps

- [Sites Management](./SITES.md) - Learn about site lifecycle
- [Configuration Guide](./CONFIGURATION.md) - Database configuration details
- [PR Previews](./PR_PREVIEWS.md) - Preview database strategies
