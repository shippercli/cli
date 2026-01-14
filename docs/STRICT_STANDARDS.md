# Strict Coding Standards Documentation

## Overview

This Laravel Zero application implements Nuno Maduro's strict Laravel coding standards and best practices. Every aspect of the codebase is designed with type safety, immutability, and code quality in mind.

## Strict Rules Applied

### 1. Strict Type Declarations

**Rule**: Every PHP file must start with `declare(strict_types=1);`

**Why**: Enables strict type checking at runtime, preventing type coercion bugs.

**Example**:
```php
<?php

declare(strict_types=1);

namespace App\Commands;
```

### 2. Explicit Type Hints

**Rule**: All method parameters and return types must be explicitly typed.

**Why**: Prevents mixed types and makes code more predictable and maintainable.

**Example**:
```php
public function handle(): int
{
    // Implementation
    return self::SUCCESS;
}
```

### 3. Final Classes

**Rule**: All concrete classes should be marked as `final` unless specifically designed for inheritance.

**Why**: Promotes composition over inheritance, makes code easier to reason about.

**Example**:
```php
final class DeployCommand extends Command
{
    // Implementation
}
```

### 4. No Mixed Types

**Rule**: Never use `mixed` type. Always specify concrete types.

**Why**: Mixed types defeat the purpose of type safety and make code harder to understand.

**Example**:
```php
// ❌ Bad
public function process(mixed $data): mixed

// ✅ Good
public function process(array $data): string
```

### 5. Property Type Declarations

**Rule**: All class properties must have explicit type declarations.

**Why**: Ensures type safety at the property level and documents expected types.

**Example**:
```php
protected string $signature = 'deploy';
protected string $description = 'Deploy the application';
```

### 6. Strict Comparisons

**Rule**: Always use strict comparison operators (`===`, `!==`).

**Why**: Prevents type coercion bugs and makes comparisons predictable.

**Example**:
```php
// ❌ Bad
if ($status == 0)

// ✅ Good  
if ($status === 0)
```

### 7. Return Type Void

**Rule**: Methods that don't return a value must declare `: void` return type.

**Why**: Makes it explicit that no return value is expected.

**Example**:
```php
public function boot(): void
{
    // Bootstrap code
}
```

### 8. Ordered Imports

**Rule**: Import statements must be alphabetically ordered and grouped by type.

**Why**: Improves readability and reduces merge conflicts.

**Example**:
```php
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
```

### 9. Native Function Invocation

**Rule**: Use optimized native function calls with leading backslash.

**Why**: Improves performance by avoiding namespace lookups.

**Example**:
```php
$path = \dirname(__DIR__);
```

### 10. Single Quotes

**Rule**: Use single quotes for strings unless interpolation is needed.

**Why**: Slight performance benefit and clearer intent.

**Example**:
```php
// ✅ Good
$name = 'Shipper';

// ✅ Also good when interpolation needed
$message = "Welcome to {$name}";
```

## Tools and Configuration

### Laravel Pint (Code Style)

**Configuration**: `pint.json`

Enforces:
- Strict type declarations
- Final classes by default
- Native function invocation
- Ordered imports
- No superfluous PHPDoc tags
- Strict comparison

**Usage**:
```bash
composer format        # Fix code style
composer format:check  # Check code style
```

### PHPStan (Static Analysis)

**Configuration**: `phpstan.neon`

**Level**: 9 (Maximum)

Enforces:
- No mixed types
- Explicit return types
- Type safety across the codebase
- Uninitialized property detection
- Dynamic property prevention

**Usage**:
```bash
composer analyse
```

### Pest (Testing)

**Configuration**: `tests/Pest.php`, `phpunit.xml`

Enforces:
- Type-safe test cases
- Explicit expectations
- Integration with Laravel Zero

**Usage**:
```bash
composer test
```

## Continuous Integration

The `.github/workflows/ci.yml` workflow ensures all code:
1. Passes code style checks (Pint)
2. Passes static analysis (PHPStan level 9)
3. Passes all tests (Pest)

No code can be merged without passing all checks.

## Benefits

1. **Type Safety**: Catches type errors at development time
2. **Better IDE Support**: Full autocompletion and error detection
3. **Self-Documenting Code**: Types serve as documentation
4. **Fewer Bugs**: Type errors caught before runtime
5. **Easier Refactoring**: Type system ensures changes are safe
6. **Better Performance**: Strict types and native function calls
7. **Consistent Codebase**: Automated formatting and style checking

## References

- [Nuno Maduro's Blog](https://nunomaduro.com/)
- [Laravel Zero Documentation](https://laravel-zero.com/)
- [PHPStan Documentation](https://phpstan.org/)
- [Laravel Pint Documentation](https://laravel.com/docs/pint)
- [Pest Documentation](https://pestphp.com/)

## Enforcement

These rules are enforced through:
1. Automated tools (Pint, PHPStan)
2. CI/CD pipeline checks
3. Code review requirements
4. Developer guidelines

All developers must ensure their code passes all checks before submitting pull requests.
