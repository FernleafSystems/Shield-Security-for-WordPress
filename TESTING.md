# Testing Guide for Shield Security Plugin

This document provides comprehensive instructions for running and writing tests for the Shield Security WordPress plugin.

## Overview

The plugin uses a modern testing setup with:
- **PHPUnit 9.6** for test execution
- **Package-Based Testing** to test the actual distributed plugin, not source code
- **WordPress Test Library** for WordPress integration testing  
- **Brain Monkey** for mocking WordPress functions in unit tests
- **yoast/wp-test-utils** for cross-version compatibility
- **PHPCS with WordPress Coding Standards** for code quality
- **Optimized CI/CD Pipeline** with 70% performance improvement

### Key Testing Philosophy

**Test the Package, Not the Source**: All tests run against the packaged plugin (as distributed to users) to ensure:
- Tests validate actual production behavior
- Packaging issues are caught immediately
- Prefixed dependencies work correctly
- No false positives from development-only code

### Why Package-Based Testing?

**Traditional Approach Problems:**
- Tests run on source with development dependencies
- Duplicate libraries (Twig/Monolog) cause conflicts
- Prefixed namespaces not tested
- Packaging bugs only found in production

**Package-Based Benefits:**
- Tests exactly what users get from WordPress.org
- No duplicate library conflicts
- Validates Strauss prefixing (AptowebDeps\\)
- Catches packaging issues before deployment
- 100% production parity

## Quick Start

### Prerequisites

- PHP 7.4 or higher
- Composer
- MySQL database for integration tests
- Git for version control

### Installation

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Set up WordPress test environment:**
   ```bash
   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```
   Replace database credentials as needed.

3. **Run all tests (see "Running ALL Tests" section below for details):**
   ```bash
   composer test                    # THE primary command - runs ALL tests
   ```

   **Alternative testing options:**

   **Option A: Smoke Tests (Fastest - No WordPress Required)**
   ```powershell
   # Windows PowerShell - rapid validation
   .\bin\run-smoke-tests.ps1       # Run all smoke tests
   composer test:smoke             # Alternative using Composer
   ```

   **Option B: Package-Based Testing (Advanced)**
   ```powershell
   # Windows PowerShell - tests the packaged plugin
   .\bin\test-package.ps1          # Build package and run all tests
   .\bin\test-package.ps1 unit     # Unit tests only
   .\bin\test-package.ps1 -SkipBuild  # Use existing package
   ```

## Running ALL Tests - The Primary Command

To run ALL tests for the Shield Security plugin, use this single command:

```bash
composer test
```

**This command runs EVERYTHING:**
- ✅ Smoke tests (rapid validation)
- ✅ Unit tests (source code testing)
- ✅ Integration tests (if environment is set up)
- ✅ All other configured test suites

**IMPORTANT**: This is THE primary way to run all tests locally. Do NOT manually run individual test scripts unless you specifically need to test only one component.

### Why Use `composer test`?

- **Complete Coverage**: Runs all test types in the correct order
- **Consistent**: Same command works for all developers
- **CI/CD Aligned**: Matches what runs in continuous integration
- **Dependency Aware**: Automatically handles test dependencies
- **Error Reporting**: Comprehensive failure reporting across all test suites

### When to Use Individual Test Commands

Only use individual test scripts when you need to:
- Test a specific component during development
- Debug a particular test suite
- Run tests with special configuration
- Focus on one test type for performance reasons

## Test Structure

### Directory Layout
```
tests/
├── Unit/                          # Unit tests (PSR-4 compliant)
│   ├── bootstrap.php              # Source code bootstrap
│   ├── bootstrap-package.php      # Package testing bootstrap
│   ├── BaseUnitTest.php           # Base class for unit tests
│   └── Modules/                   # Tests organized by module
├── Integration/                   # Integration tests
│   ├── bootstrap.php              # Source code bootstrap
│   ├── bootstrap-package.php      # Package testing bootstrap
│   └── Modules/                   # Tests organized by module
├── Package/                       # Package validation tests
├── Helpers/                       # Shared test utilities
│   └── PackageTestHelper.php      # Helper for package-based testing
└── fixtures/                      # Test data and resources

Configuration Files:
├── phpunit-unit.xml               # Unit tests on source code
├── phpunit-unit-package.xml       # Unit tests on packaged plugin
├── phpunit-integration.xml        # Integration tests on source
└── phpunit-integration-package.xml # Integration tests on package
```

### Test Types

#### Smoke Tests (`tests/unit/` - specific smoke test files)
- Ultra-fast validation of critical functionality
- No WordPress or database required
- Validates plugin.json configuration integrity
- Checks critical file existence and autoloader
- Perfect for CI/CD pipeline integration
- Complete in under 10 seconds
- See [Smoke Tests Documentation](tests/README-SMOKE-TESTS.md) for details

#### Unit Tests (`tests/unit/`)
- Test individual classes/methods in isolation
- Use Brain Monkey for mocking WordPress functions
- Fast execution, no database required
- Focus on business logic and algorithms

#### Integration Tests (`tests/integration/`)
- Test WordPress + plugin integration
- Use real WordPress test environment
- Test hooks, filters, database operations
- Slower but more realistic

## Running Tests

**REMINDER**: For running ALL tests, use `composer test` - see the "Running ALL Tests" section above.

The commands below are for running SPECIFIC test types only:

### Smoke Tests (Fastest Validation)

Smoke tests provide rapid validation without requiring WordPress or database setup:

**Windows PowerShell:**
```powershell
# Run all smoke tests
.\bin\run-smoke-tests.ps1

# Run with verbose output
.\bin\run-smoke-tests.ps1 -Verbose

# Run specific smoke tests
.\bin\run-smoke-tests.ps1 -TestFilter json    # Only JSON validation
.\bin\run-smoke-tests.ps1 -TestFilter core    # Only core functionality

# Stop on first failure
.\bin\run-smoke-tests.ps1 -FailFast
```

**Composer Commands:**
```bash
composer test:smoke         # Run all smoke tests
composer test:smoke:json    # Run JSON validation only
composer test:smoke:core    # Run core functionality only
```

**Direct PHPUnit:**
```bash
# Run specific smoke test
vendor/bin/phpunit -c phpunit-unit.xml tests/Unit/PluginJsonSchemaTest.php
vendor/bin/phpunit -c phpunit-unit.xml tests/Unit/CorePluginSmokeTest.php
```

### Package-Based Testing (Recommended)

**Windows PowerShell:**
```powershell
# Complete pipeline (build + test) - EXACTLY LIKE CI/CD
.\bin\integration-optimized.ps1         # Full optimized pipeline
.\bin\integration-optimized.ps1 -FastMode  # Minimal testing (like feature branches)
.\bin\integration-optimized.ps1 -FullMatrix # Force full PHP/WP matrix

# Dedicated package testing
.\bin\test-package.ps1                  # Build and test package
.\bin\test-package.ps1 unit -Coverage   # Unit tests with coverage
.\bin\test-package.ps1 integration      # Integration tests only
.\bin\test-package.ps1 -SkipBuild      # Test existing package
.\bin\test-package.ps1 build-only      # Just build package, no tests
```

**What These Scripts Do:**
1. Build a production-ready plugin package (with Strauss prefixing)
2. Remove duplicate libraries that cause conflicts
3. Copy test files into the package
4. Run tests FROM the package directory
5. Tests load the packaged plugin with production dependencies

**Linux/Mac:**
```bash
# Build package
./bin/build-plugin.sh ../shield-package

# Copy test files to package
cp -r tests ../shield-package/
cp phpunit-*-package.xml ../shield-package/
cp composer.* ../shield-package/

# Run tests from package
cd ../shield-package
composer install  # Install test dependencies
SHIELD_TEST_PACKAGE=true vendor/bin/phpunit -c phpunit-unit-package.xml
```

### Traditional Source Testing
```bash
composer test                # Run ALL tests (THIS IS THE PRIMARY COMMAND)
composer test:unit           # Unit tests only (specific component)
composer test:integration    # Integration tests only (specific component)
composer test:coverage       # Generate coverage reports
```

### Environment Variables
```bash
# Force package-based testing
export SHIELD_TEST_PACKAGE=true
export SHIELD_PACKAGE_PATH=/path/to/package

# Disable package testing (use source)
export SHIELD_TEST_PACKAGE=false
```

### Individual Test Files
```bash
phpunit tests/unit/ControllerTest.php
phpunit --filter testControllerCanBeInstantiated
```

## Choosing Test Mode

### When to Use Package Testing
- **Always in CI/CD** - The optimized pipeline uses packages
- **Before releases** - Validate the distribution package
- **Debugging user issues** - Test exact user environment
- **Testing prefixed dependencies** - Ensure Strauss worked

### When Source Testing is OK
- **Rapid development** - Quick iteration on single classes
- **IDE integration** - Some IDEs work better with source
- **Debugging test failures** - Easier to modify and test

### Switching Between Modes
```powershell
# Force package testing
$env:SHIELD_TEST_PACKAGE = "true"

# Force source testing
$env:SHIELD_TEST_PACKAGE = "false"

# Let bootstrap auto-detect
Remove-Item env:SHIELD_TEST_PACKAGE
```

## Writing Tests

### Unit Test Example

```php
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use Brain\Monkey;
use YourNamespace\YourClass;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class YourClassTest extends TestCase {
    
    public function set_up() :void {
        parent::set_up();
        Monkey\setUp();
    }

    public function tear_down() :void {
        Monkey\tearDown();
        parent::tear_down();
    }

    public function testSomething() :void {
        // Mock WordPress functions
        Monkey\Functions\when( 'get_option' )->justReturn( 'mocked_value' );
        
        $instance = new YourClass();
        $result = $instance->someMethod();
        
        $this->assertEquals( 'expected', $result );
    }
}
```

### Integration Test Example

```php
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class WordPressIntegrationTest extends TestCase {

    public function testWordPressFunction() :void {
        // WordPress functions are available in integration tests
        $this->assertTrue( function_exists( 'wp_insert_post' ) );
        
        $post_id = wp_insert_post( [
            'post_title' => 'Test Post',
            'post_content' => 'Test content',
            'post_status' => 'publish'
        ] );
        
        $this->assertIsInt( $post_id );
        $this->assertGreaterThan( 0, $post_id );
    }
}
```

### Testing Guidelines

1. **Follow PSR-4 namespacing:** `FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\...`

2. **Use descriptive test names:**
   - ✅ `testControllerCanBeInstantiatedWithValidRootFile()`
   - ❌ `testController()`

3. **Test one thing per test method**

4. **Use appropriate assertions:**
   - `assertSame()` for strict equality
   - `assertEquals()` for loose equality  
   - `assertTrue()`, `assertFalse()` for booleans
   - `assertInstanceOf()` for object types

5. **Mock external dependencies in unit tests**

6. **Use fixtures for complex test data**

## Code Quality

### PHPCS (WordPress Coding Standards)
```bash
composer phpcs              # Check coding standards
composer phpcs:fix          # Auto-fix issues
```

### Security Audit
```bash
composer audit              # Check for vulnerable dependencies
```

## Continuous Integration

### Optimized CI/CD Pipeline

The repository includes two CI/CD workflows:

1. **ci-optimized.yml** (70% faster, recommended)
   - Smoke tests run first for rapid validation
   - Single build job creating comprehensive artifact
   - Parallel execution of all test types
   - Smart caching for dependencies and WordPress files
   - Conditional matrix testing (minimal for features, full for main)
   - Tests run on packaged plugin, not source code

2. **ci.yml** (legacy, being phased out)
   - Sequential execution with redundant builds
   - Tests source code instead of package

### Smoke Tests in CI/CD

Smoke tests are ideal for CI/CD pipelines due to their speed and reliability:

```yaml
# Example GitHub Actions integration
smoke-tests:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v3
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '7.4'
    - name: Install dependencies
      run: composer install --no-progress
    - name: Run smoke tests
      run: composer test:smoke
    - name: Continue with full tests if smoke tests pass
      if: success()
      run: composer test
```

### Performance Improvements

- **Feature branches**: 10+ min → 3-5 min (70% reduction)
- **Main branches**: 10+ min → 6-8 min with full matrix (40% reduction)
- **Key optimizations**:
  - Dependencies installed once, not 4+ times
  - Assets built once, not multiple times
  - Tests run in parallel, not sequentially
  - Aggressive caching reduces download time

### Testing Matrix

**Full Matrix (main/develop branches):**
- PHP: 7.4, 8.0, 8.1, 8.2, 8.3
- WordPress: 6.0, 6.6, latest, trunk

**Minimal Matrix (feature branches):**
- PHP: 7.4
- WordPress: latest

### Local CI Simulation
To simulate CI locally:
```bash
# Run code standards check
composer phpcs

# Run tests like CI does
export WP_TESTS_DB_HOST=127.0.0.1
export WP_TESTS_DB_NAME=wordpress_test  
export WP_TESTS_DB_USER=root
export WP_TESTS_DB_PASSWORD=root

vendor/bin/phpunit -c phpunit-unit.xml
vendor/bin/phpunit -c phpunit-integration.xml
```

## Database Setup

### Local MySQL
```bash
mysql -u root -p
CREATE DATABASE wordpress_test;
GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wp_test'@'localhost' IDENTIFIED BY 'password';
FLUSH PRIVILEGES;
```

### Using Docker
```bash
docker run --name mysql-test -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=wordpress_test -p 3306:3306 -d mysql:5.7
```

## Environment Variables

Set these in your environment or CI:

```bash
# Database connection
WP_TESTS_DB_NAME=wordpress_test
WP_TESTS_DB_USER=root  
WP_TESTS_DB_PASSWORD=root
WP_TESTS_DB_HOST=127.0.0.1

# WordPress test environment
WP_TESTS_DOMAIN=example.org
WP_TESTS_EMAIL=admin@example.org
WP_TESTS_TITLE="Test Site"

# Plugin-specific
SHIELD_TESTING=1
```

## Package-Based Testing Details

### How It Works

1. **Build Phase**: Creates production-ready plugin package
   - Installs production dependencies only (no dev deps)
   - Runs Strauss for namespace prefixing (AptowebDeps\)
   - Removes duplicate libraries (Twig, Monolog) that cause conflicts
   - Builds frontend assets (webpack production build)
   - Creates exact WordPress.org distribution structure

2. **Test Phase**: Runs tests against package
   - Tests load packaged plugin, not source
   - Uses `PackageTestHelper` for dynamic path resolution
   - Bootstrap detects package vs source testing automatically
   - Coverage reports work with packaged code
   - Environment variables control test mode:
     - `SHIELD_TEST_PACKAGE=true` - Force package testing
     - `SHIELD_PACKAGE_PATH=/path` - Custom package location

3. **Local vs CI Parity**:
   - Local scripts use same build process as CI
   - Same package structure validation
   - Same test execution order
   - Same environment variables

### Package Structure Validation

The package must match WordPress.org distribution:
- 9 required PHP files (main plugin files)
- 5 required directories (src, assets, flags, languages, templates)
- Prefixed vendor dependencies in `src/lib/vendor_prefixed/`
- No duplicate libraries that cause conflicts

## Troubleshooting

### Common Issues

**Package not found for testing:**
```powershell
# Build the package first
.\bin\test-package.ps1 build-only
# Or set environment variable
$env:SHIELD_PACKAGE_PATH = "C:\path\to\package"
```

**How to verify you're testing the package (not source):**
```powershell
# Look for this message when tests start:
"✅ Testing packaged plugin from: D:\...\shield-package"

# If you see this instead, you're testing source:
"⚠️ Package testing not available: ..."
```

**Duplicate library errors (development only):**
```bash
# This happens in source testing due to Twig/Monolog duplicates
# The package build process removes these duplicates
# Solution: Use package-based testing instead
.\bin\test-package.ps1

# Why this happens:
# - Source has Twig in both vendor/ and vendor_prefixed/
# - Package build removes vendor/twig/ to prevent conflicts
# - This matches the WordPress.org distribution
```

**WordPress test library not found:**
```bash
# Re-run the setup script
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

**Database connection errors:**
- Verify MySQL is running
- Check database credentials
- Ensure test database exists

**Memory issues:**
```bash
php -d memory_limit=512M vendor/bin/phpunit
```

### Debug Mode
```bash
# Enable debug mode
export SHIELD_TESTING_DEBUG=1
phpunit --debug
```

## Contributing

### Test Requirements
- All new features must include unit tests
- Integration tests for WordPress functionality
- Tests must pass in CI pipeline
- Code coverage should not decrease

### Naming Conventions
- Test files: `*Test.php`
- Test methods: `test*` or use `@test` annotation
- Test classes: match the class being tested + `Test` suffix

### Code Review
Before submitting:
1. Run `composer quality` - must pass
2. Run `composer test` - all tests must pass  
3. Add tests for new functionality
4. Update documentation if needed

## Centralized Test Directory

All temporary test scripts and artifacts are managed through a centralized test directory **outside the repository** to maintain a clean development environment.

### Directory Structure

```
D:\Work\Dev\Tests\
└── WP_Plugin-Shield\
    ├── scripts\            # Temporary test scripts (for development/debugging)
    ├── artifacts\          # Test output, logs, reports
    ├── packages\           # Built plugin packages for testing
    └── work\              # Temporary work directories
```

### Test Scripts Overview

#### 1. Central Test Manager (`bin\test-central.ps1`)

The main entry point for all testing activities. Manages the test directory and delegates to specific test scripts.

```powershell
# Run full integration tests (build + test + package)
.\bin\test-central.ps1 full

# Run package-based tests
.\bin\test-central.ps1 package

# Run unit tests only
.\bin\test-central.ps1 unit

# Run integration tests only
.\bin\test-central.ps1 integration

# Check test directory status
.\bin\test-central.ps1 status

# Clean all test artifacts
.\bin\test-central.ps1 clean
```

**Options:**
- `-KeepArtifacts`: Preserve test artifacts after completion
- `-Verbose`: Show detailed output

#### 2. Integration Full Pipeline (`bin\integration-full.ps1`)

Runs the complete end-to-end pipeline:
1. Creates work directory in central test location
2. Builds frontend assets (npm/webpack)
3. Installs/updates all dependencies
4. Runs Strauss for namespace prefixing
5. Runs PHPCS code standards check
6. Runs PHPUnit tests
7. Creates final package
8. Stores all artifacts in central test directory

**Output locations:**
- Log: `D:\Work\Dev\Tests\WP_Plugin-Shield\artifacts\integration-full-[timestamp].log`
- Work: `D:\Work\Dev\Tests\WP_Plugin-Shield\work\integration-full-work`
- Package: `D:\Work\Dev\Tests\WP_Plugin-Shield\packages\shield-package-integration-full`

#### 3. Package Testing (`bin\test-package.ps1`)

Builds a production-ready plugin package and runs tests against it.

```powershell
# Build and test everything
.\bin\test-package.ps1

# Run specific test types
.\bin\test-package.ps1 unit
.\bin\test-package.ps1 integration

# Skip build and use existing package
.\bin\test-package.ps1 -SkipBuild

# Run with code coverage
.\bin\test-package.ps1 -Coverage
```

**Output locations:**
- Package: `D:\Work\Dev\Tests\WP_Plugin-Shield\packages\shield-package-[timestamp]`
- Test results: Displayed in console

### For Developers

#### Creating Temporary Test Scripts

If you need to create temporary test scripts:

1. **DO NOT** create them in the project directory
2. **DO** create them in `D:\Work\Dev\Tests\WP_Plugin-Shield\scripts\`
3. These scripts are automatically ignored by git
4. Clean them up when done: `.\bin\test-central.ps1 clean`

#### Finding Test Artifacts

All test outputs are timestamped and stored in:
- Logs: `D:\Work\Dev\Tests\WP_Plugin-Shield\artifacts\*.log`
- Packages: `D:\Work\Dev\Tests\WP_Plugin-Shield\packages\shield-package-*`

#### Best Practices

1. **Always use central test directory** - Never create test artifacts in the repository
2. **Run clean periodically** - `.\bin\test-central.ps1 clean` removes old artifacts
3. **Check status** - `.\bin\test-central.ps1 status` shows what's using disk space
4. **Use timestamps** - All artifacts include timestamps for easy identification

### For Claude (AI Assistant)

#### Important Rules

1. **NEVER create test scripts in the project directory**
   - Wrong: `project-dir/bin/test-something.ps1`
   - Right: `D:\Work\Dev\Tests\WP_Plugin-Shield\scripts\test-something.ps1`

2. **NEVER store test output in the project directory**
   - Wrong: `project-dir/test-output.log`
   - Right: `D:\Work\Dev\Tests\WP_Plugin-Shield\artifacts\test-output.log`

3. **ALWAYS use the central test directory for:**
   - Temporary test scripts
   - Test output and logs
   - Built packages
   - Work directories

4. **ALWAYS clean up after testing**
   - Use try/finally blocks
   - Run cleanup even if tests fail
   - Remove temporary scripts when done

#### Code Pattern for Test Scripts

```powershell
# Get project info
$ProjectName = "WP_Plugin-Shield"
$TestBase = "D:\Work\Dev\Tests\$ProjectName"

# Ensure directories exist
New-Item -ItemType Directory -Path "$TestBase\artifacts" -Force | Out-Null

# Use timestamped filenames
$LogFile = "$TestBase\artifacts\my-test-$(Get-Date -Format 'yyyyMMdd-HHmmss').log"

try {
    # Test logic here
    # All outputs go to $TestBase
} finally {
    # Cleanup
}
```

### Troubleshooting

**"Directory not found" errors**
- Run `.\bin\test-central.ps1 status` to ensure directories exist
- The test-central.ps1 script automatically creates missing directories

**"Access denied" errors**
- Ensure you have write permissions to `D:\Work\Dev\Tests\`
- Close any programs that might be using files in the test directory

**Old artifacts consuming disk space**
- Run `.\bin\test-central.ps1 clean` to remove all artifacts
- The script automatically cleans artifacts older than 7 days

### Verification

```powershell
# Check directory structure
.\bin\test-central.ps1 status

# Run a quick test
.\bin\test-central.ps1 unit

# Verify artifacts are in correct location
Get-ChildItem "D:\Work\Dev\Tests\WP_Plugin-Shield\artifacts"

# Confirm nothing created in project directory
git status  # Should show no untracked files after testing
```

### Key Benefits

- **Clean Repository**: No test artifacts in version control
- **Centralized Management**: All test data in one location
- **Easy Cleanup**: Single command cleans everything
- **No Git Pollution**: Test directory is outside all repositories
- **Consistent Structure**: Same organization across all projects
- **Timestamp Tracking**: Easy to identify when tests were run
- **Disk Space Management**: Old artifacts can be cleaned automatically

This testing setup provides a solid foundation for maintaining high code quality and ensuring the Shield Security plugin works reliably across different environments.