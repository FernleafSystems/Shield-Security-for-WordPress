# Shield Security Smoke Tests Runner

A PowerShell script for running fast validation tests on critical plugin functionality.

## Overview

The `run-smoke-tests.ps1` script provides a quick way to validate that the Shield Security WordPress plugin's core functionality is working correctly. These tests are designed to be fast and focused on essential validation.

## Features

- **Fast Execution**: Smoke tests complete in seconds, not minutes
- **Centralized Logging**: All test artifacts stored in `D:\Work\Dev\Tests\WP_Plugin-Shield\artifacts\`
- **Color-Coded Output**: Clear visual feedback with ✅ for passed tests and ❌ for failures
- **JSON Summary Reports**: Machine-readable test results for CI/CD integration
- **Test Filtering**: Run specific tests using pattern matching
- **Verbose Mode**: See detailed test output when debugging
- **Fail-Fast Mode**: Stop on first failure for rapid feedback
- **Execution Time Tracking**: Monitor test performance

## Usage

### Run All Smoke Tests
```powershell
.\bin\run-smoke-tests.ps1
```

### Run Specific Tests
```powershell
# Run only tests matching 'json'
.\bin\run-smoke-tests.ps1 -TestFilter json

# Run only tests matching 'core'
.\bin\run-smoke-tests.ps1 -TestFilter core
```

### Debug Mode
```powershell
# Run with verbose output
.\bin\run-smoke-tests.ps1 -Verbose

# Stop on first failure
.\bin\run-smoke-tests.ps1 -FailFast

# Combine options
.\bin\run-smoke-tests.ps1 -TestFilter json -Verbose -FailFast
```

## Current Smoke Tests

1. **Plugin Configuration Schema Validation** (`PluginJsonSchemaTest.php`)
   - Validates plugin.json structure and content
   - Ensures all required configuration fields exist
   - Verifies cross-references between sections

2. **Core Plugin Functionality Check** (`CorePluginSmokeTest.php`)
   - Verifies critical plugin files exist
   - Tests autoloader functionality
   - Validates basic plugin initialization

## Output Files

All test artifacts are stored in `D:\Work\Dev\Tests\WP_Plugin-Shield\artifacts\`:

- `smoke-tests-YYYYMMDD-HHMMSS.log` - Detailed test execution log
- `smoke-tests-summary-YYYYMMDD-HHMMSS.json` - JSON summary report

## Exit Codes

- `0` - All tests passed
- `1` - One or more tests failed

## Requirements

- Laravel Herd installed with PHP 8.3
- PowerShell 5.1 or higher
- Shield Security plugin development environment

## CI/CD Integration

The script is designed for easy CI/CD integration:

```yaml
# Example GitHub Actions usage
- name: Run Smoke Tests
  run: powershell -ExecutionPolicy Bypass -File bin\run-smoke-tests.ps1
  shell: pwsh
```

## Adding New Smoke Tests

To add a new smoke test:

1. Create a test file in `tests\Unit\` directory
2. Add the test to the `$smokeTests` array in the script:
   ```powershell
   @{
       File = "tests\Unit\YourNewTest.php"
       Name = "Your Test Description"
   }
   ```

## Performance

Smoke tests are optimized for speed:
- No database required
- No coverage analysis
- Minimal bootstrapping
- Typical execution time: < 2 seconds