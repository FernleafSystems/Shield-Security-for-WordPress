# Shield Security Testing Infrastructure Knowledge Base

**Last Updated**: 2025-01-27  
**Status**: Definitive Reference Document  
**Scope**: Complete testing strategy, tools, and lessons learned

## Executive Summary

Shield Security has evolved a sophisticated testing infrastructure through extensive experimentation and learning. This document consolidates all critical knowledge gained from abandoning PHPStan in favor of WordPress-ecosystem-appropriate testing strategies, implementing dual testing approaches, and creating robust CI/CD pipelines.

## Table of Contents

1. [Testing Philosophy](#testing-philosophy)
2. [Why PHPStan Doesn't Work with WordPress](#why-phpstan-doesnt-work-with-wordpress)
3. [PHPCS vs PHPStan Decision](#phpcs-vs-phpstan-decision)
4. [PHPUnit Polyfills Evolution](#phpunit-polyfills-evolution)
5. [Dual Testing Strategy](#dual-testing-strategy)
6. [Test Bootstrap Architecture](#test-bootstrap-architecture)
7. [Matrix Testing Configuration](#matrix-testing-configuration)
8. [Docker Testing Implementation](#docker-testing-implementation)
9. [Package Testing Approach](#package-testing-approach)
10. [Windows PowerShell Scripts](#windows-powershell-scripts)
11. [Centralized Testing Directory](#centralized-testing-directory)
12. [Lessons from Failed Approaches](#lessons-from-failed-approaches)
13. [Best Practices and Guidelines](#best-practices-and-guidelines)

---

## Testing Philosophy

Shield Security follows a pragmatic testing approach aligned with WordPress ecosystem best practices:

- **Test what matters**: Focus on business logic, security features, and integration points
- **Use appropriate tools**: WordPress-specific tools over generic PHP tools
- **Fast feedback loops**: Unit tests for rapid development, integration tests for confidence
- **Industry alignment**: Follow patterns from successful plugins (Yoast SEO, Easy Digital Downloads)
- **Maintainability over perfection**: Practical testing that developers will actually maintain

## Why PHPStan Doesn't Work with WordPress

After extensive experimentation, we definitively concluded PHPStan is unsuitable for WordPress development. Here's why:

### 1. Dynamic Properties and Magic Methods

WordPress extensively uses dynamic properties and magic methods that PHPStan cannot analyze:

```php
// WordPress pattern that breaks PHPStan
class WP_User {
    // Properties are dynamically added at runtime
    public function __get($name) {
        // PHPStan sees this as undefined property access
    }
}

// Shield Security usage
$user->custom_meta; // PHPStan: Property does not exist
```

### 2. Third-Party Plugin Classes

WordPress's plugin architecture means classes from other plugins are unknowable at static analysis time:

```php
// PHPStan cannot know if these classes exist
if (class_exists('WooCommerce')) {
    $order = new WC_Order(); // PHPStan: Class not found
}
```

### 3. Hook-Based Architecture

WordPress's event-driven architecture with filters and actions is opaque to static analysis:

```php
// PHPStan cannot trace through WordPress hooks
$data = apply_filters('shield_security_data', $data);
// What is $data now? PHPStan has no idea
```

### 4. Global State and Side Effects

WordPress relies heavily on global state that static analysis cannot track:

```php
// WordPress globals that PHPStan struggles with
global $wpdb, $wp_query, $post;
// These are dynamically populated and modified
```

### 5. False Positive Explosion

The combination of these factors creates an overwhelming number of false positives:
- **Initial run**: 500+ errors in Shield Security
- **After extensive configuration**: Still 200+ unfixable errors
- **Time investment**: Days spent on configuration with minimal ROI
- **Maintenance burden**: Constant baseline updates for non-issues

## PHPCS vs PHPStan Decision

### Why We Chose PHPCS

**PHPCS (PHP CodeSniffer)** with WordPress Coding Standards was chosen because:

1. **WordPress-Aware**: Understands WordPress patterns and conventions
2. **Security Focused**: Catches actual security issues (SQL injection, XSS, nonces)
3. **Style Consistency**: Enforces WordPress coding standards
4. **Low False Positives**: Designed for WordPress, minimal false alarms
5. **Industry Standard**: Used by WordPress.org plugin review team

### Configuration Example

```xml
<!-- .phpcs.xml.dist -->
<?xml version="1.0"?>
<ruleset name="Shield Security">
    <description>Shield Security coding standards</description>
    
    <!-- WordPress Coding Standards -->
    <rule ref="WordPress-Core"/>
    <rule ref="WordPress-Extra"/>
    <rule ref="WordPress.Security"/>
    <rule ref="WordPress.WP.I18n"/>
    
    <!-- Exclude vendor and test files -->
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/tests/*</exclude-pattern>
</ruleset>
```

### Real Issues PHPCS Catches

```php
// PHPCS catches security issues PHPStan misses
$wpdb->query("SELECT * FROM users WHERE id = $_GET['id']"); // SQL injection
echo $_POST['data']; // XSS vulnerability
delete_option('important'); // Missing nonce verification
```

## PHPUnit Polyfills Evolution

### The Journey: 1.1 → 4.0

Our testing infrastructure evolved through several stages:

#### Stage 1: Initial Setup (PHPUnit Polyfills 1.1)
- Basic compatibility layer
- Limited PHPUnit version support (5-9)
- Required wp-test-utils wrapper

#### Stage 2: Yoast Integration (Brief Experiment)
- Added `yoast/wp-test-utils` as middleware
- Discovered it was just a thin wrapper around BrainMonkey
- Added unnecessary complexity without benefits

#### Stage 3: Direct Approach (PHPUnit Polyfills 4.0)
- **Removed wp-test-utils entirely**
- **Upgraded to PHPUnit Polyfills 4.0**
- **Direct BrainMonkey integration**
- **Support for PHPUnit 7-12**

### Why We Removed wp-test-utils

```json
// Before: Unnecessary wrapper
{
    "require-dev": {
        "yoast/wp-test-utils": "^1.2",
        "yoast/phpunit-polyfills": "^1.1"
    }
}

// After: Direct and clean
{
    "require-dev": {
        "yoast/phpunit-polyfills": "^4.0",
        "brain/monkey": "^2.6"
    }
}
```

Benefits of the direct approach:
- **Fewer dependencies**: Removed unnecessary abstraction layer
- **Better compatibility**: PHPUnit 7-12 support (was limited to 9)
- **Direct control**: No middleman between our tests and tools
- **Future-proof**: Better long-term maintenance prospects

## Dual Testing Strategy

Shield Security implements a sophisticated dual testing approach:

### 1. Unit Tests (Fast, No Database)

**Tool**: BrainMonkey for WordPress function mocking  
**Bootstrap**: `tests/bootstrap/brain-monkey.php`  
**Config**: `phpunit-unit.xml`

```php
// Example unit test with BrainMonkey
class SecurityModuleTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }
    
    public function testFirewallPattern() {
        // Mock WordPress functions
        Functions\expect('apply_filters')
            ->once()
            ->with('shield_firewall_patterns', \Mockery::type('array'))
            ->andReturnFirstArg();
        
        // Test business logic without WordPress
        $firewall = new FirewallModule();
        $this->assertTrue($firewall->detectSQLInjection("'; DROP TABLE--"));
    }
    
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
}
```

### 2. Integration Tests (Full WordPress)

**Tool**: WordPress Test Suite with WP_UnitTestCase  
**Bootstrap**: `tests/bootstrap/integration.php`  
**Config**: `phpunit-integration.xml`

```php
// Example integration test with WordPress
class PluginActivationTest extends WP_UnitTestCase {
    public function testPluginActivation() {
        // Real WordPress environment
        activate_plugin('shield-security/icwp-wpsf.php');
        
        // Test against actual database
        $option = get_option('shield_security_activated');
        $this->assertTrue($option);
        
        // Verify tables created
        global $wpdb;
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}shield_security'");
        $this->assertNotNull($table);
    }
}
```

### When to Use Each

**Unit Tests**:
- Algorithm testing
- Business logic validation
- Utility functions
- Pattern matching
- Data transformation

**Integration Tests**:
- Database operations
- WordPress hook integration
- Plugin activation/deactivation
- Admin interface functionality
- REST API endpoints

## Test Bootstrap Architecture

### Separate Bootstrap Files

The key to our dual testing strategy is maintaining separate bootstrap files:

#### Unit Test Bootstrap (`tests/bootstrap/brain-monkey.php`)

```php
<?php
// Load composer autoloader
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Define WordPress constants for unit tests
if (!defined('ABSPATH')) {
    define('ABSPATH', '/wordpress/');
}
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

// Mock WordPress functions globally
\Brain\Monkey\Functions\stubs([
    'wp_parse_args',
    'wp_json_encode',
    'wp_remote_get',
    'get_option',
    'update_option',
    '__',
    'esc_html__',
    'wp_nonce_field',
    'wp_verify_nonce'
]);

// Load plugin files needed for testing
require_once dirname(__DIR__, 2) . '/plugin_autoload.php';
```

#### Integration Test Bootstrap (`tests/bootstrap/integration.php`)

```php
<?php
// Load WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Load test functions
require_once $_tests_dir . '/includes/functions.php';

// Load Shield Security plugin
function _manually_load_plugin() {
    require dirname(__DIR__, 2) . '/icwp-wpsf.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start WordPress test suite
require $_tests_dir . '/includes/bootstrap.php';
```

### Key Design Decisions

1. **No shared bootstrap**: Each test type has its own isolated bootstrap
2. **Explicit mocking**: Unit tests explicitly mock what they need
3. **Real environment**: Integration tests use actual WordPress installation
4. **Fast feedback**: Developers can run unit tests without WordPress setup

## Matrix Testing Configuration

### GitHub Actions Matrix

Our CI/CD pipeline tests across multiple versions:

```yaml
strategy:
  matrix:
    php: ['7.4', '8.0', '8.1', '8.2', '8.3']
    wordpress: ['6.0', '6.6', 'latest', 'trunk']
    exclude:
      # PHP 7.4 not compatible with WP trunk
      - php: '7.4'
        wordpress: 'trunk'
```

### Why This Matrix

- **PHP 7.4**: Minimum supported version
- **PHP 8.0-8.2**: Current stable versions in production
- **PHP 8.3**: Latest version for future compatibility
- **WordPress 6.0**: Minimum supported version
- **WordPress 6.6**: LTS-like stable version
- **WordPress latest**: Current release
- **WordPress trunk**: Future compatibility testing

## Docker Testing Implementation

### Modern Approach: SVN-Free

We discovered Ubuntu 24.04 removed SVN from GitHub Actions runners, forcing modernization:

```yaml
# Old approach (broken)
- name: Install WordPress Test Suite
  run: |
    bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
    # This fails - requires SVN which is no longer available

# New approach (working)
- name: Setup WordPress Test Suite
  run: |
    # Download via HTTPS instead of SVN
    curl -o /tmp/wordpress.tar.gz https://wordpress.org/wordpress-latest.tar.gz
    tar -xzf /tmp/wordpress.tar.gz -C /tmp
    
    # Get test suite from GitHub
    git clone --depth=1 https://github.com/WordPress/wordpress-develop.git /tmp/wordpress-tests-lib
```

### Docker-Based Testing

For local development, we use Docker containers:

```yaml
# docker-compose.test.yml
version: '3.8'
services:
  wordpress:
    image: wordpress:6.6-php8.3
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress_test
    volumes:
      - .:/var/www/html/wp-content/plugins/shield-security
      
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wordpress_test
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
```

## Package Testing Approach

### Build Process with Strauss

Shield Security uses Strauss for dependency prefixing to avoid conflicts:

```powershell
# Package building process
function Build-Package {
    # Install dependencies
    composer install --no-dev --optimize-autoloader
    
    # Run Strauss prefixing
    composer strauss
    
    # Create package directory
    $PackageDir = "shield-security-$Version"
    
    # Copy files excluding development
    Copy-Item -Path . -Destination $PackageDir -Recurse -Exclude @(
        '.git*',
        'tests',
        'bin',
        '*.md',
        'composer.json',
        'package.json',
        'webpack.config.js'
    )
    
    # Verify package
    Test-PackageStructure $PackageDir
}
```

### Package Validation Tests

```php
class PackageValidationTest extends TestCase {
    public function testPackageStructure() {
        $requiredFiles = [
            'icwp-wpsf.php',
            'plugin.json',
            'vendor_prefixed/autoload.php',
            'src/lib/vendor/autoload.php'
        ];
        
        foreach ($requiredFiles as $file) {
            $this->assertFileExists($this->packageDir . '/' . $file);
        }
    }
    
    public function testNoDevDependencies() {
        $this->assertDirectoryDoesNotExist($this->packageDir . '/vendor/phpunit');
        $this->assertDirectoryDoesNotExist($this->packageDir . '/vendor/brain');
    }
}
```

## Windows PowerShell Scripts

### Critical Discoveries

Through extensive debugging, we discovered critical issues with Windows development:

#### 1. The Herd PHP Wrapper Problem

```powershell
# PROBLEM: Using Herd wrapper causes hanging
$PhpPath = "php"  # This is actually a bash script wrapper
& $PhpPath --version  # Causes: "/c: /c: Is a directory" error

# SOLUTION: Use direct PHP executable
$PhpPath = "C:\Users\paulg\.config\herd\bin\php83\php.exe"
& $PhpPath --version  # Works perfectly
```

#### 2. Non-Interactive Execution

```powershell
# PROBLEM: Interactive prompts cause hanging
composer update  # May prompt for authentication

# SOLUTION: Always use non-interactive flags
composer update --no-interaction --no-progress
```

### Complete Working Script

```powershell
# integration-full.ps1 - 100% working end-to-end pipeline
param(
    [switch]$SkipCleanup = $false
)

$ErrorActionPreference = "Stop"
$PhpPath = "C:\Users\paulg\.config\herd\bin\php83\php.exe"

# Create isolated work directory
$WorkDir = "test-run-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
New-Item -ItemType Directory -Force -Path $WorkDir | Out-Null

try {
    # Copy plugin to work directory
    Copy-Item -Path . -Destination $WorkDir -Recurse -Force -Exclude @('.git', $WorkDir)
    Set-Location $WorkDir
    
    # Install dependencies
    Write-Host "Installing dependencies..." -ForegroundColor Green
    & $PhpPath (Get-Command composer).Path update --no-interaction --no-progress
    
    Set-Location src/lib
    & $PhpPath (Get-Command composer).Path update --no-interaction --no-progress
    Set-Location ../..
    
    # Build package with Strauss
    Write-Host "Building package..." -ForegroundColor Green
    & $PhpPath (Get-Command composer).Path install --no-dev --optimize-autoloader --no-interaction
    & $PhpPath (Get-Command composer).Path strauss
    
    # Run PHPStan (with timeout)
    Write-Host "Running static analysis..." -ForegroundColor Green
    $Job = Start-Job -ScriptBlock {
        param($PhpPath, $WorkDir)
        Set-Location $WorkDir
        & $PhpPath vendor/bin/phpstan analyse --no-interaction
    } -ArgumentList $PhpPath, $PWD
    
    Wait-Job $Job -Timeout 300 | Out-Null
    if ($Job.State -eq 'Running') {
        Stop-Job $Job
        Write-Host "PHPStan timeout (expected)" -ForegroundColor Yellow
    }
    
    # Run tests
    Write-Host "Running tests..." -ForegroundColor Green
    & $PhpPath vendor/bin/phpunit --no-interaction
    
    Write-Host "Pipeline completed successfully!" -ForegroundColor Green
    
} finally {
    Set-Location ..
    if (-not $SkipCleanup) {
        Remove-Item -Path $WorkDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}
```

## Centralized Testing Directory

### Architecture

To prevent repository pollution, we implemented a centralized testing directory:

```
D:\Work\Dev\Tests\WP_Plugin-Shield\
├── scripts\          # Temporary test scripts
├── artifacts\        # Test logs and outputs
├── packages\         # Built plugin packages
└── work\            # Temporary work directories
```

### Benefits

1. **Clean Repository**: No test artifacts in git
2. **Easy Cleanup**: Single location for all test data
3. **Parallel Testing**: Multiple test runs without conflicts
4. **Audit Trail**: Timestamped artifacts for debugging

### Implementation

```powershell
# test-central.ps1 - Central test management
param(
    [Parameter(Mandatory=$false)]
    [ValidateSet('list', 'clean', 'clean-old')]
    [string]$Action = 'list'
)

$TestRoot = "D:\Work\Dev\Tests\WP_Plugin-Shield"

switch ($Action) {
    'list' {
        Get-ChildItem $TestRoot -Recurse | 
            Select-Object FullName, CreationTime, Length
    }
    
    'clean' {
        Remove-Item "$TestRoot\*" -Recurse -Force
        Write-Host "Cleaned all test artifacts" -ForegroundColor Green
    }
    
    'clean-old' {
        Get-ChildItem $TestRoot -Recurse | 
            Where-Object { $_.CreationTime -lt (Get-Date).AddDays(-7) } |
            Remove-Item -Recurse -Force
        Write-Host "Cleaned artifacts older than 7 days" -ForegroundColor Green
    }
}
```

## Lessons from Failed Approaches

### 1. Component-Based Testing Success

Our breakthrough came from systematic component isolation:

```powershell
# Instead of one monolithic script, we created:
component-1-dependencies.ps1    # Just install dependencies
component-2-package-build.ps1   # Just build package
component-3-phpstan.ps1        # Just run analysis
component-4-phpunit.ps1        # Just run tests

# Then progressively integrated:
integration-1-2.ps1      # Dependencies + Package
integration-1-2-3.ps1    # + PHPStan
integration-full.ps1     # Everything
```

This approach enabled:
- **Precise debugging**: Identify exactly where failures occur
- **Incremental validation**: Verify each step works before combining
- **Faster iteration**: Test individual components without full pipeline

### 2. Batch Files Don't Work

Initial attempts with batch files failed due to:
- Path resolution issues with spaces
- Poor error handling
- No proper variable scoping
- Difficult debugging

PowerShell provided:
- Robust error handling with `$ErrorActionPreference`
- Proper path handling with quotes
- Clear variable scoping
- Better debugging with `-Verbose` and transcripts

### 3. PHPStan Baseline Trap

We wasted significant time trying to maintain PHPStan baselines:

```yaml
# phpstan-baseline.neon - A maintenance nightmare
parameters:
  ignoreErrors:
    - '#Property .* does not exist#' # 200+ instances
    - '#Class .* not found#'         # 150+ instances
    - '#Call to undefined method#'   # 100+ instances
```

Every WordPress update, plugin change, or refactor required baseline updates.

### 4. Over-Engineering Test Infrastructure

Initial attempts created overly complex test setups:
- Custom test runners
- Complex Docker orchestration
- Multiple configuration layers
- Excessive abstraction

The successful approach was simpler:
- Standard PHPUnit
- Simple bootstrap files
- Direct tool usage
- Minimal configuration

## Best Practices and Guidelines

### 1. Test Organization

```
tests/
├── Unit/           # Fast, mocked tests
│   ├── Modules/    # Module-specific tests
│   ├── Security/   # Security feature tests
│   └── Utilities/  # Helper function tests
├── Integration/    # Full WordPress tests
│   ├── Admin/      # Admin interface tests
│   ├── REST/       # API endpoint tests
│   └── Database/   # Database operation tests
└── bootstrap/      # Separate bootstrap files
    ├── brain-monkey.php
    └── integration.php
```

### 2. Writing Effective Tests

```php
class EffectiveTest extends TestCase {
    /**
     * Test names should clearly describe what they test
     */
    public function testFirewallBlocksSQLInjectionPatterns() {
        // Arrange - Set up test data
        $maliciousInput = "'; DROP TABLE users--";
        
        // Act - Execute the code
        $result = $this->firewall->scan($maliciousInput);
        
        // Assert - Verify the result
        $this->assertTrue($result->isBlocked());
        $this->assertEquals('sql_injection', $result->getThreatType());
    }
}
```

### 3. CI/CD Pipeline Optimization

```yaml
# Optimized workflow - 70% faster than original
name: Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['7.4', '8.3']  # Test min and max only for PRs
        wordpress: ['6.0', 'latest']
    
    steps:
      # Cache dependencies for speed
      - uses: actions/cache@v3
        with:
          path: vendor
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
      
      # Run tests in parallel
      - name: Unit Tests
        run: composer test:unit
      
      - name: Integration Tests
        if: matrix.wordpress == 'latest'  # Only on one WP version
        run: composer test:integration
```

### 4. Local Development Workflow

```bash
# Quick unit test during development
composer test:unit -- --filter=FirewallTest

# Full test before commit
composer test

# With coverage
composer test:coverage

# Windows PowerShell
.\bin\test-unit.ps1 -Filter "FirewallTest"
```

### 5. Debugging Test Failures

```php
// Add debugging output
public function testComplexScenario() {
    $data = $this->setupTestData();
    
    // Debug output only when test fails
    if ($data === null) {
        var_dump($this->lastError);
        print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
    }
    
    $this->assertNotNull($data, 'Data setup failed: ' . $this->lastError);
}
```

## Conclusion

Shield Security's testing infrastructure represents months of learning and refinement. Key takeaways:

1. **WordPress is different**: Generic PHP tools don't work well with WordPress
2. **Industry alignment matters**: Follow successful plugins, not generic PHP projects
3. **Pragmatism over purity**: Perfect static analysis isn't worth the cost
4. **Developer experience**: Fast tests developers will actually run
5. **Systematic debugging**: Component isolation solves complex problems
6. **Platform awareness**: Windows/PowerShell needs special consideration

This testing strategy provides:
- **Fast feedback** with unit tests (< 5 seconds)
- **High confidence** with integration tests
- **Maintainable** infrastructure developers understand
- **Industry-standard** practices from successful plugins
- **CI/CD ready** with matrix testing and optimizations

The journey from broken PHPStan attempts to a fully functional dual testing strategy demonstrates the importance of choosing tools appropriate for your ecosystem rather than following generic best practices blindly.