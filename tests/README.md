# Shield Security Plugin Test Suite

## Overview

This directory contains the test suite for the Shield Security WordPress plugin. Tests are divided into:
- **Unit Tests**: Fast tests that don't require WordPress
- **Integration Tests**: Tests that require WordPress test framework

## Testing Options

### Option 1: Docker Matrix Testing (Recommended)

**Enterprise-grade testing** - Matrix testing across PHP/WordPress versions with zero setup:

```powershell
# Source testing (current code) - unified runner
.\bin\run-tests.ps1 all -Docker                # All tests
.\bin\run-tests.ps1 unit -Docker               # Unit tests only
.\bin\run-tests.ps1 integration -Docker        # Integration tests only

# Package testing (production build - validated)
.\bin\run-tests.ps1 all -Docker -Package       # All tests on built package
.\bin\run-tests.ps1 unit -Docker -Package      # Unit tests on built package

# Matrix testing with specific versions (all validated)
.\bin\run-tests.ps1 all -Docker -PhpVersion 8.1 -WpVersion 6.3
.\bin\run-tests.ps1 all -Docker -PhpVersion 7.4 -WpVersion latest

# Alternative: Composer commands
composer docker:test                        # All tests
composer docker:test:unit                   # Unit tests only  
composer docker:test:integration            # Integration tests only
composer docker:test:package                # Package testing
```

**Matrix Testing**: Supports PHP 7.4-8.4 and WordPress versions with automatic CI/CD integration.

See `tests/docker/README.md` for complete Docker documentation.

### Option 2: Native Testing

**Full control** - Uses your local PHP/MySQL setup:

```powershell
# Unified test runner (native mode - default)
.\bin\run-tests.ps1 all                        # All tests
.\bin\run-tests.ps1 unit                       # Unit tests only
.\bin\run-tests.ps1 integration                # Integration tests only

# Alternative: Direct Composer commands
composer test                               # All tests
composer test:unit                          # Unit tests only
composer test:integration                   # Integration tests only
```

## Requirements

- PHP 8.3+ (Important: Not PHP 8.2)
- Composer
- MySQL/MariaDB (for integration tests)
- SVN (for downloading WordPress test framework)

## Quick Start

### 1. Install Dependencies
```bash
composer install
```

### 2. Run Unit Tests
Unit tests can be run immediately without any setup:
```bash
vendor/bin/phpunit --testsuite=unit
```

### 3. Setup for Integration Tests

#### Option A: Using PowerShell (Windows)
```powershell
# Run the PowerShell setup script
.\bin\install-wp-tests.ps1 -DB_NAME wordpress_test -DB_USER root -DB_PASS your_password
```

#### Option B: Using Bash (Linux/macOS/WSL)
```bash
# Run the bash setup script
bash bin/install-wp-tests.sh wordpress_test root 'your_password' localhost latest
```

### 4. Run Integration Tests
```bash
vendor/bin/phpunit --testsuite=integration
```

### 5. Run All Tests
```bash
vendor/bin/phpunit
```

## Test Structure

```
tests/
├── bootstrap.php          # Test bootstrap file
├── fixtures/             # Test fixtures and base classes
│   └── TestCase.php     # Base test case
├── unit/                # Unit tests (no WordPress required)
│   ├── BasicFunctionalityTest.php
│   ├── ConfigurationValidationTest.php
│   └── ControllerTest.php
└── integration/         # Integration tests (WordPress required)
    ├── FilesHaveJsonFormatTest.php
    ├── PluginActivationTest.php
    └── WordPressHooksTest.php
```

## Writing Tests

### Unit Tests
- Extend `Yoast\PHPUnitPolyfills\TestCases\TestCase`
- Don't use WordPress functions
- Focus on isolated functionality
- Use Brain\Monkey for mocking WordPress functions if needed

### Integration Tests
- Extend `Yoast\PHPUnitPolyfills\TestCases\TestCase`
- Require WordPress test environment
- Can use WordPress functions and database
- Test actual plugin integration with WordPress

## Code Coverage

Generate code coverage report:
```bash
# Requires Xdebug or PCOV
composer run test:coverage
```

## Continuous Integration - Matrix Testing

Tests run automatically on GitHub Actions with comprehensive matrix testing:

**Matrix Coverage** (GitHub Actions Run ID 16694657226 - 100% Success):
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 (complete matrix)
- **WordPress Versions**: Dynamic detection of latest (6.8.2) + previous major (6.7.2)
- **Total Combinations**: 12 test combinations run in parallel
- **Code Quality**: PHPCS, PHPStan, and package validation
- **Production Ready**: All tests passing across matrix combinations

**Automatic Triggers**: Matrix testing runs on all pushes to main branches (develop, main, master)
**Manual Testing**: Custom PHP/WordPress combinations available via workflow dispatch

## Troubleshooting

### "WordPress test library not found"
Run the install script to download WordPress test framework.

### MySQL Connection Issues
- Ensure MySQL/MariaDB is running
- Check database credentials
- Create test database manually if needed:
  ```sql
  CREATE DATABASE wordpress_test;
  ```

### PHP Version Issues
Make sure you're using PHP 8.3, not PHP 8.2:
```bash
php -v
```

## Docker Matrix Testing Features - Production Validated

The unified test runner provides enterprise-grade matrix testing capabilities:

### Matrix Testing Environment Detection
- **Package Testing**: Set via `SHIELD_PACKAGE_PATH` environment variable with production validation
- **Docker Environment**: Detected when plugin mounted at `/var/www/html/wp-content/plugins/wp-simple-firewall`
- **Source Testing**: Default mode using current repository directory
- **Matrix Support**: PHP 7.4-8.4 and WordPress version combinations

### Production-Validated Bootstrap Files
- Single bootstrap files work for native, Docker, package, and matrix testing
- No separate Docker-specific bootstrap files needed
- Follows WordPress plugin patterns from Yoast, EDD, WooCommerce
- Matrix testing validated with GitHub Actions Run ID 16694657226

### Comprehensive Matrix Package Testing
```powershell
# Package testing builds and validates across matrix
.\\bin\\run-tests.ps1 unit -Docker -Package          # Test built package
.\\bin\\run-tests.ps1 all -Docker -PhpVersion 8.1     # Test specific PHP version
```

### Enterprise Resource Management
- Automatic cleanup across all matrix combinations
- Multi-layer caching for optimal performance
- Parallel execution of matrix combinations
- Resource optimization and automatic container management

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)
- [Brain Monkey Documentation](https://brain-wp.github.io/BrainMonkey/)
- [Docker Testing Documentation](docker/README.md)
