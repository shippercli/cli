# Deployer

A Laravel Zero application built with strict type checking and code quality standards.

## Features

This project implements strict coding standards inspired by Nuno Maduro's strict Laravel approach:

### Strict Type Enforcement
- ✅ `declare(strict_types=1)` in all PHP files
- ✅ Type hints on all method parameters and return types
- ✅ Final classes by default (immutability)
- ✅ No mixed types allowed
- ✅ Strict comparison operators

### Code Quality Tools

#### PHPStan (Level 9)
Configured with maximum strictness:
- No mixed types
- All properties must have type declarations
- Checks for always-true conditions
- Validates return types in protected and public methods
- Reports uninitialized properties
- Dynamic properties disabled

#### Laravel Pint
Code style enforcement with:
- Strict type declarations
- Strict comparison
- Native function invocation optimization
- Ordered imports
- Final class enforcement
- No superfluous PHPDoc tags

#### Pest Testing
Modern testing with:
- Type-safe test cases
- Feature and unit testing support
- Integration with Laravel Zero

## Installation

```bash
composer install
cp .env.example .env
```

## Usage

```bash
# Run the deploy command
./deployer deploy

# List all commands
./deployer list
```

## Development

```bash
# Run code style checks
composer format:check

# Fix code style
composer format

# Run static analysis
composer analyse

# Run tests
composer test
```

## Continuous Integration

GitHub Actions automatically runs:
1. Code style validation (Pint)
2. Static analysis (PHPStan level 9)
3. Tests (Pest)

All checks must pass before merging.

## Strict Rules Applied

1. **Type Safety**: Every method has explicit parameter and return types
2. **Immutability**: Classes are final by default
3. **Strict Comparisons**: Using `===` and `!==` operators
4. **No Mixed Types**: Explicit types required everywhere
5. **Property Types**: All properties must declare types
6. **PHPStan Level 9**: Maximum static analysis strictness
7. **Code Style**: Enforced via Pint with strict rules

## License

MIT