# Development Guide

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- Git

### Initial Setup

1. **Clone the repository**:
```bash
git clone https://github.com/ulties/deployer-wip.git
cd deployer-wip
```

2. **Install dependencies**:
```bash
composer install
```

3. **Copy environment file**:
```bash
cp .env.example .env
```

4. **Configure environment**:
Edit `.env` and add your credentials:
```
PLOI_API_KEY=your-api-key-here
```

5. **Test the installation**:
```bash
./deployer list
./deployer validate
```

## Development Workflow

### Making Changes

1. **Create a feature branch**:
```bash
git checkout -b feature/your-feature-name
```

2. **Make your changes** following the strict coding standards (see [strict-standards.md](./strict-standards.md))

3. **Run code quality checks**:
```bash
composer format      # Fix code style
composer analyse     # Run static analysis
composer test        # Run tests
```

4. **Commit your changes**:
```bash
git add .
git commit -m "feat: add your feature description"
```

5. **Push and create a pull request**:
```bash
git push origin feature/your-feature-name
```

### Commit Message Convention

Follow conventional commits format:

- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting, etc.)
- `refactor:` - Code refactoring
- `test:` - Adding or updating tests
- `chore:` - Maintenance tasks

**Examples**:
```
feat: add support for DigitalOcean provider
fix: resolve issue with environment variable interpolation
docs: update configuration guide with examples
test: add unit tests for ConfigLoader
```

## Code Quality Tools

### 1. Laravel Pint (Code Style)

**Purpose**: Enforces consistent code formatting and style

**Configuration**: `pint.json`

**Commands**:
```bash
# Check code style (doesn't modify files)
composer format:check
./pint.phar --test

# Fix code style automatically
composer format
./pint.phar
```

**Rules Enforced**:
- Strict type declarations
- Final classes by default
- Ordered imports
- No superfluous PHPDoc
- Single quotes for strings
- Strict comparison operators

### 2. PHPStan (Static Analysis)

**Purpose**: Catches type errors and potential bugs before runtime

**Configuration**: `phpstan.neon`

**Level**: 9 (Maximum)

**Commands**:
```bash
# Run static analysis
composer analyse
./phpstan.phar analyse

# Run with verbose output
./phpstan.phar analyse -v
```

**What It Checks**:
- Type safety across all code
- Return type consistency
- Uninitialized properties
- Dead code and always-true conditions
- Dynamic property usage (disabled)
- Mixed types (not allowed)

### 3. Pest (Testing)

**Purpose**: Modern PHP testing framework

**Configuration**: `tests/Pest.php`, `phpunit.xml`

**Commands**:
```bash
# Run all tests
composer test
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/ValidateCommandTest.php

# Run with coverage
./vendor/bin/pest --coverage

# Run with verbose output
./vendor/bin/pest --verbose
```

## Testing Guidelines

### Test Organization

```
tests/
├── Feature/          # Integration and feature tests
│   ├── ValidateCommandTest.php
│   ├── PlanCommandTest.php
│   └── ApplyCommandTest.php
└── Unit/            # Unit tests
    ├── ConfigLoaderTest.php
    ├── PloiProviderTest.php
    └── ProviderFactoryTest.php
```

### Writing Tests

#### Feature Tests

Test complete command workflows:

```php
<?php

declare(strict_types=1);

it('validates deployer configuration', function (): void {
    $this->artisan('validate')
        ->expectsOutput('✓ Configuration is valid')
        ->assertSuccessful();
});

it('shows error for invalid configuration', function (): void {
    // Remove config file
    rename('deployer.yml', 'deployer.yml.backup');
    
    $this->artisan('validate')
        ->expectsOutput('✗ Configuration file not found')
        ->assertFailed();
        
    // Restore config
    rename('deployer.yml.backup', 'deployer.yml');
});
```

#### Unit Tests

Test individual classes and methods:

```php
<?php

declare(strict_types=1);

use App\Config\ConfigLoader;

it('loads configuration from yaml file', function (): void {
    $loader = new ConfigLoader();
    $config = $loader->load('deployer.yml');
    
    expect($config)->not->toBeNull();
    expect($config->projects)->toHaveKey('api');
});

it('interpolates environment variables', function (): void {
    putenv('TEST_VAR=test-value');
    
    $loader = new ConfigLoader();
    $result = $loader->interpolate('${TEST_VAR}');
    
    expect($result)->toBe('test-value');
});
```

### Test Coverage

Aim for:
- **90%+ coverage** for core business logic
- **100% coverage** for critical paths (deployment, configuration)

Check coverage:
```bash
./vendor/bin/pest --coverage --min=90
```

### Mocking External Services

Use mocks for API calls:

```php
<?php

declare(strict_types=1);

use App\Providers\Deployment\PloiProvider;

it('deploys to ploi successfully', function (): void {
    $mockClient = Mockery::mock('HttpClient');
    $mockClient->shouldReceive('post')
        ->once()
        ->andReturn(['status' => 'success']);
    
    $provider = new PloiProvider($mockClient);
    $result = $provider->deploy($project, $profile);
    
    expect($result)->toBeTrue();
});
```

## Project Structure

### Directory Layout

```
deployer/
├── app/                    # Application code
│   ├── Commands/          # CLI commands
│   ├── Config/            # Configuration classes
│   ├── Providers/         # Service providers
│   └── Kernel.php         # Application kernel
├── bootstrap/             # Application bootstrap
├── config/               # Framework configuration
├── docs/                 # Documentation (you are here!)
├── examples/             # Example deployable projects
├── routes/               # Console routes
├── tests/                # Test suite
│   ├── Feature/         # Feature tests
│   └── Unit/            # Unit tests
├── .github/workflows/    # GitHub Actions
├── deployer              # CLI entry point
├── deployer.yml          # Configuration file
├── composer.json         # Dependencies
├── phpstan.neon         # Static analysis config
├── phpunit.xml          # Test configuration
└── pint.json            # Code style config
```

### Adding New Files

Always follow these rules:

1. **Start with strict types**:
```php
<?php

declare(strict_types=1);

namespace App\YourNamespace;
```

2. **Use final classes**:
```php
final class YourClass
{
    // Implementation
}
```

3. **Type everything**:
```php
public function method(string $param): int
{
    return 42;
}
```

## Adding New Features

### Adding a New Command

1. **Create the command class**:
```bash
php artisan make:command YourCommand
```

2. **Implement the command**:
```php
<?php

declare(strict_types=1);

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

final class YourCommand extends Command
{
    protected $signature = 'your:command {argument}';
    
    protected $description = 'Command description';
    
    public function handle(): int
    {
        // Implementation
        
        return self::SUCCESS;
    }
}
```

3. **Add tests**:
```php
it('runs your command successfully', function (): void {
    $this->artisan('your:command', ['argument' => 'value'])
        ->assertSuccessful();
});
```

### Adding a New Provider

See [providers.md](./providers.md) for detailed instructions.

### Adding a New Configuration Option

1. **Update configuration classes**:
```php
final class ProjectConfig
{
    public function __construct(
        public readonly string $provider,
        public readonly string $path,
        public readonly string $newOption,  // Add here
        // ...
    ) {}
}
```

2. **Update YAML parsing**:
```php
$config = new ProjectConfig(
    provider: $data['provider'],
    path: $data['path'],
    newOption: $data['new_option'] ?? 'default',
    // ...
);
```

3. **Update documentation** in [configuration.md](./configuration.md)

4. **Add validation** if needed

5. **Write tests**

## Debugging

### Debug Mode

Enable debug output:
```bash
./deployer your:command -vvv
```

### Logging

Add logging to your code:
```php
$this->info('Information message');
$this->warn('Warning message');
$this->error('Error message');
```

### Interactive Debugging

Use Tinker for REPL:
```bash
php artisan tinker
```

### Dump and Die

Use Laravel's `dd()` helper:
```php
dd($variable);  // Dumps and dies

dump($variable);  // Dumps and continues
```

## Performance Optimization

### Composer Optimization

```bash
# Production optimization
composer install --no-dev --optimize-autoloader

# Generate optimized class map
composer dump-autoload --optimize
```

### Profiling

Use Xdebug for profiling:
```bash
php -dxdebug.mode=profile ./deployer your:command
```

## Troubleshooting

### Common Issues

#### Issue: Pint Fails

**Solution**: Run format to fix automatically:
```bash
composer format
```

#### Issue: PHPStan Errors

**Solution**: Fix type issues. Common fixes:
- Add return types: `public function method(): void`
- Type properties: `private string $property`
- Use strict comparison: `===` instead of `==`

#### Issue: Tests Fail

**Solution**: 
1. Check test output for specific failure
2. Ensure test database/environment is set up
3. Clear cache: `php artisan config:clear`

#### Issue: Command Not Found

**Solution**: Clear route cache:
```bash
php artisan route:clear
```

### Getting Help

1. **Check existing issues**: [GitHub Issues](https://github.com/ulties/deployer-wip/issues)
2. **Read documentation**: You're in it!
3. **Ask the team**: Create a new issue with your question

## Contributing Guidelines

### Pull Request Process

1. **Ensure all checks pass**:
   - ✅ Code style (Pint)
   - ✅ Static analysis (PHPStan)
   - ✅ Tests (Pest)

2. **Write descriptive PR description**:
   - What changes were made
   - Why they were needed
   - How to test them

3. **Request review** from maintainers

4. **Address feedback** promptly

5. **Squash commits** before merging (if requested)

### Code Review Checklist

Reviewers will check:

- [ ] Code follows strict standards
- [ ] All methods have type hints
- [ ] Classes are final (unless designed for extension)
- [ ] Tests are included and passing
- [ ] Documentation is updated
- [ ] No security vulnerabilities introduced
- [ ] Performance is not degraded
- [ ] Changes are minimal and focused

## Resources

### Documentation

- [Architecture Overview](./architecture.md)
- [Configuration Guide](./configuration.md)
- [Provider System](./providers.md)
- [GitHub Actions](./github-actions.md)
- [Strict Standards](./strict-standards.md)

### External Resources

- [Laravel Zero Documentation](https://laravel-zero.com/)
- [PHPStan Documentation](https://phpstan.org/)
- [Pest Documentation](https://pestphp.com/)
- [Laravel Pint](https://laravel.com/docs/pint)

### Community

- [GitHub Discussions](https://github.com/ulties/deployer-wip/discussions)
- [Issue Tracker](https://github.com/ulties/deployer-wip/issues)

## Next Steps

- Read [architecture documentation](./architecture.md)
- Explore [provider system](./providers.md)
- Review [strict coding standards](./strict-standards.md)
