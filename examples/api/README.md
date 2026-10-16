# Example Laravel API Project

This is a Laravel application used as an example for the Shipper project.

## About

This Laravel application is part of the shipper-wip repository and serves as an example API project for testing deployment workflows.

## API Endpoints

### GET /api/jokes

Returns a sample programming joke in JSON format.

**Example Response:**
```json
{
  "joke": "Why do programmers prefer dark mode? Because light attracts bugs!",
  "type": "programming",
  "setup": "Why do programmers prefer dark mode?",
  "punchline": "Because light attracts bugs!"
}
```

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
./shipper plan api --profile=production

# Deploy to production
./shipper apply api --profile=production

# Deploy to staging
./shipper apply api --profile=staging
```

## Local Development

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Start development server
php artisan serve
```

## Verification

From `examples/api`, run `composer install`, `composer test`, `npm ci`, and
`npm run build`. PHP 8.2 or later is supported; Composer resolves the lockfile
against PHP 8.2 to preserve that minimum. Frontend CI covers Node 22 and 24.
The repository's `.github/workflows/api-example.yml` runs the example tests,
platform checks, dependency audits, and frontend builds. These checks do not
deploy the example or verify provider connectivity.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
