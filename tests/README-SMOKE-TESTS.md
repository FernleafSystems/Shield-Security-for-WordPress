# Shield Security Plugin - Smoke Tests Documentation

## Overview

Smoke tests are a critical first line of defense in the Shield Security plugin's quality assurance process. These tests perform rapid validation of essential plugin functionality and configuration integrity, ensuring that critical components are working correctly before more comprehensive testing begins.

## Purpose and Benefits

### Why Smoke Tests?

1. **Early Detection**: Catch critical failures within seconds rather than waiting for full test suites
2. **CI/CD Integration**: Lightweight tests perfect for pipeline integration
3. **Developer Confidence**: Quick validation after code changes
4. **Production Safety**: Verify package integrity before deployment
5. **Configuration Validation**: Ensure the 6,673-line plugin.json remains valid

### Key Benefits

- **Speed**: Complete validation in under 10 seconds
- **Isolation**: No WordPress installation required
- **Reliability**: Deterministic tests with no external dependencies
- **Coverage**: Validates critical paths and configuration
- **Automation**: Fully scriptable for CI/CD pipelines

## Test Suites

### 1. Plugin Configuration Schema Validation (`PluginJsonSchemaTest`)

This comprehensive test suite validates the entire `plugin.json` configuration file structure.

#### What It Validates:

- **Structure Integrity**
  - All required top-level keys exist
  - Data types match expectations
  - No unexpected keys present

- **Properties Section**
  - Version follows semantic versioning (X.Y.Z)
  - Build format matches YYYYMM.DDNN pattern
  - Text domain contains only valid characters
  - Autoupdate values are within allowed set

- **Requirements**
  - PHP version >= 7.4
  - WordPress version >= 5.7
  - MySQL version format validation

- **Module Definitions**
  - All 10 security modules properly defined
  - Required fields present for each module
  - Database requirements correctly specified

- **Cross-References**
  - Sections reference valid modules
  - Options reference existing sections
  - Events have valid severity levels

- **File Size Handling**
  - Validates parsing of 6,673+ line configuration
  - Ensures deep nested structures are accessible

### 2. Core Plugin Functionality Check (`CorePluginSmokeTest`)

This test suite validates that critical plugin components exist and can be loaded.

#### What It Validates:

- **Critical Files**
  - Main plugin file (`icwp-wpsf.php`)
  - Plugin initialization files
  - Autoloader functionality
  - Configuration file presence

- **Autoloader Verification**
  - Core classes can be autoloaded
  - Namespace resolution works correctly
  - No fatal errors during class loading

- **Plugin Initialization**
  - Plugin can initialize without errors
  - Plugin header contains correct metadata
  - WordPress constants handled gracefully

- **Database Requirements**
  - Database-related traits exist
  - Module database dependencies documented
  - Table schema structures available

- **WordPress Integration Points**
  - Hook registration structure present
  - Action routing controller exists
  - Critical WordPress actions registered

- **Asset Structure**
  - Assets directory exists
  - Common subdirectories present
  - Resource paths correctly configured

- **Module Architecture**
  - All 10 expected modules defined
  - Module directories exist (when applicable)
  - Module configuration consistent

## Running the Tests

### Method 1: PowerShell Script (Recommended)

```powershell
# Run all smoke tests
.\bin\run-smoke-tests.ps1

# Run with verbose output
.\bin\run-smoke-tests.ps1 -Verbose

# Run only specific tests
.\bin\run-smoke-tests.ps1 -TestFilter json
.\bin\run-smoke-tests.ps1 -TestFilter core

# Stop on first failure
.\bin\run-smoke-tests.ps1 -FailFast
```

### Method 2: Composer Scripts

```bash
# Run all smoke tests
composer test:smoke

# Run only JSON validation tests
composer test:smoke:json

# Run only core plugin tests
composer test:smoke:core
```

### Method 3: Direct PHPUnit Execution

```bash
# Run all smoke tests
vendor/bin/phpunit -c phpunit-unit.xml --filter "PluginJsonSchemaTest|CorePluginSmokeTest"

# Run specific test class
vendor/bin/phpunit -c phpunit-unit.xml tests/Unit/PluginJsonSchemaTest.php
```

## Understanding Test Output

### Successful Test Run

```
============================================
Shield Security - Smoke Tests Runner
============================================

Running: Plugin Configuration Schema Validation
✅ PASSED in 2.34 seconds
   Tests: 15, Assertions: 847

Running: Core Plugin Functionality Check
✅ PASSED in 1.89 seconds
   Tests: 8, Assertions: 42

============================================
🎉 ALL SMOKE TESTS PASSED! 🎉
============================================
```

### Failed Test Output

```
Running: Plugin Configuration Schema Validation
❌ FAILED in 0.45 seconds
Test Output:
   FAILURES!
   Tests: 15, Assertions: 230, Failures: 1
   
   1) testRequiredTopLevelKeysExist
   Required top-level key 'config_spec' is missing
```

### Test Result Files

The PowerShell runner generates artifacts in `D:\Work\Dev\Tests\WP_Plugin-Shield\artifacts\`:

- `smoke-tests-YYYYMMDD-HHMMSS.log` - Full test output log
- `smoke-tests-summary-YYYYMMDD-HHMMSS.json` - Structured test results

## Validation Rules Reference

### Plugin.json Schema Rules

#### Top-Level Structure
- Required keys: `properties`, `requirements`, `paths`, `includes`, `menu`, `labels`, `meta`, `plugin_meta`, `action_links`, `config_spec`

#### Properties Validation
- `version`: Semantic versioning format (e.g., "21.0.7")
- `build`: YYYYMM.DDNN format (e.g., "202501.2901")
- `text_domain`: Lowercase letters, numbers, hyphens only
- `autoupdate`: One of `immediate`, `confidence`, `stable`, `manual`
- `release_timestamp`: Valid Unix timestamp
- Boolean fields: `wpms_network_admin_only`, `logging_enabled`, `show_dashboard_widget`, `show_admin_bar_menu`, `enable_premium`

#### Module Requirements
Each module must have:
- `slug`: Matching the module key
- `name`: Human-readable name
- `tagline`: Brief description (optional for some)
- `show_central`: Boolean visibility flag

#### Database Dependencies
Modules with database requirements:
- `audit_trail`: at_logs, at_meta, ips, req_logs
- `hack_protect`: scans, scanitems, resultitems, resultitem_meta, scanresults
- `integrations`: botsignal, ips
- `ips`: ips
- `login_protect`: botsignal
- `comments_filter`: botsignal

### Core Plugin Rules

#### Critical Files
Must exist:
- `icwp-wpsf.php` - Main plugin file
- `plugin_init.php` - Initialization logic
- `plugin_autoload.php` - Class autoloader
- `plugin.json` - Configuration

#### Autoloadable Classes
Must be loadable:
- `\FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller`
- `\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController`

#### Plugin Header Requirements
- Plugin Name: Shield Security
- Version: Must match plugin.json
- Text Domain: wp-simple-firewall

## Troubleshooting Common Issues

### Issue: PHP Not Found

**Error**: "PHP executable not found at: C:\Users\[username]\.config\herd\bin\php83\php.exe"

**Solution**: 
1. Ensure Laravel Herd is installed
2. Verify PHP 8.3 is configured in Herd
3. Check the path matches your username

### Issue: Tests Timeout

**Error**: "⏱️ TIMEOUT after 60 seconds"

**Solution**:
1. Check for syntax errors in plugin files
2. Verify autoloader is not in infinite loop
3. Increase timeout in PowerShell script if needed

### Issue: JSON Parsing Fails

**Error**: "plugin.json must be valid JSON"

**Solution**:
1. Validate JSON syntax with external tool
2. Check for trailing commas
3. Ensure proper UTF-8 encoding

### Issue: Missing Module

**Error**: "Module 'firewall' should be defined"

**Solution**:
1. Verify plugin.json contains all required modules
2. Check module slug matches expected name
3. Ensure no modules were accidentally removed

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Smoke Tests

on: [push, pull_request]

jobs:
  smoke-tests:
    runs-on: windows-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        
    - name: Install dependencies
      run: composer install --no-progress --no-interaction
      
    - name: Run smoke tests
      run: composer test:smoke
      
    - name: Upload test artifacts
      if: failure()
      uses: actions/upload-artifact@v3
      with:
        name: smoke-test-results
        path: D:\Work\Dev\Tests\WP_Plugin-Shield\artifacts\
```

### GitLab CI Example

```yaml
smoke_tests:
  stage: test
  script:
    - composer install --no-progress --no-interaction
    - composer test:smoke
  artifacts:
    when: on_failure
    paths:
      - tests/artifacts/
    expire_in: 1 week
  only:
    - merge_requests
    - main
```

### Pre-commit Hook

```bash
#!/bin/sh
# .git/hooks/pre-commit

echo "Running smoke tests..."
composer test:smoke

if [ $? -ne 0 ]; then
    echo "Smoke tests failed! Commit aborted."
    exit 1
fi
```

## Best Practices

### When to Run Smoke Tests

1. **Before Every Commit**: Catch issues early
2. **In CI/CD Pipelines**: First test stage
3. **After Major Changes**: Validate configuration updates
4. **Before Releases**: Final safety check
5. **During Development**: Quick validation cycles

### Maintaining Smoke Tests

1. **Keep Tests Fast**: Target < 10 seconds total
2. **High-Value Checks**: Focus on critical failures
3. **No External Dependencies**: Tests must be isolated
4. **Clear Failure Messages**: Aid quick debugging
5. **Regular Updates**: Maintain as plugin evolves

### Adding New Smoke Tests

When adding new smoke tests:
1. Place in `tests/Unit/` directory
2. Follow naming convention: `*SmokeTest.php`
3. Extend appropriate base test class
4. Add to PowerShell runner configuration
5. Document validation rules here

## Performance Benchmarks

Expected execution times on modern hardware:

- **PluginJsonSchemaTest**: 2-4 seconds
  - JSON parsing: ~0.5s
  - Structure validation: ~1.5s
  - Cross-reference checks: ~1s

- **CorePluginSmokeTest**: 1-3 seconds
  - File existence: ~0.5s
  - Autoloader tests: ~1s
  - Configuration parsing: ~0.5s

- **Total Suite**: 3-7 seconds

## Future Enhancements

Planned improvements for smoke test suite:

1. **Asset Validation**: Verify critical CSS/JS files
2. **License Checks**: Validate Pro features configuration
3. **Compatibility Matrix**: PHP/WordPress version checks
4. **Performance Metrics**: Track test execution trends
5. **Security Signatures**: Validate firewall patterns

## Conclusion

Smoke tests provide a critical safety net for the Shield Security plugin development process. By validating essential functionality and configuration integrity in seconds, they enable confident development and reliable deployments. Integrate them into your workflow to catch issues early and maintain plugin quality.