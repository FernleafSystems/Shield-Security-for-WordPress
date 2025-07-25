# Shield Security Plugin Test Suite

## Overview

This directory contains the test suite for the Shield Security WordPress plugin. Tests are divided into:
- **Unit Tests**: Fast tests that don't require WordPress
- **Integration Tests**: Tests that require WordPress test framework

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

## Continuous Integration

Tests run automatically on GitHub Actions for:
- Multiple PHP versions (7.4, 8.0, 8.1, 8.2, 8.3)
- Multiple WordPress versions (latest, 6.4, 6.3)
- Code quality checks (PHPCS)

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

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)
- [Brain Monkey Documentation](https://brain-wp.github.io/BrainMonkey/)
