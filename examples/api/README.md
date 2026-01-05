# Example Laravel API Project

This is a Laravel application used as an example for the Deployer project.

## About

This Laravel application is part of the deployer-wip repository and serves as an example API project for testing deployment workflows.

## API Endpoints

### GET /api/jokes

Returns a random programming joke in JSON format.

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

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
