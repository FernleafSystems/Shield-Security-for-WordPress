# Shield Security CI/CD Documentation

## Overview

Shield Security uses a simplified, industry-standard CI/CD approach following the WooCommerce pattern.

## Workflow Structure

### Single Workflow File
- **File**: `.github/workflows/tests.yml`
- **Jobs**: source analysis, unit tests, integration runtime, package build, package validation

### Test Matrix
- **PHP Versions**: 7.4 and latest supported PHP in CI
- **WordPress**: Latest (via WordPress Test Suite)
- **Database**: MySQL 8.0

## Simplified Commands

The CI uses the standardized composer commands:

```bash
# Run all tests (unit + integration)
composer test

# Run individual test suites
composer test:unit
composer test:integration

# Source static analysis
composer analyze
```

## Job Breakdown

### 1. Test Job (`test`)
- Runs on the configured PHP matrix
- Includes MySQL service for integration tests
- Uses `composer test` for all testing
- Builds plugin package once, tests against it

**Steps:**
1. Checkout code
2. Setup PHP & Node.js
3. Install dependencies (Composer + npm)
4. Build assets (npm run build)
5. Build plugin package
6. Install WordPress Test Suite
7. Run tests via `composer test`

### 2. Source Analysis Job
- Runs source static analysis under the CI PHP target
- Uses `composer analyze`
- PHPCS is not part of the active verification surface

## Key Improvements

### Before (Complex Approach)
- 231 lines across multiple jobs
- Separate jobs for unit and integration tests
- Manual PHPUnit command construction
- Duplicate build logic
- Complex debug output

### After (Simplified Approach) 
- 89 lines total
- Single test job with matrix
- Uses `composer test` command
- Single build process
- Clean, readable structure

## Environment Variables

- `SHIELD_PACKAGE_PATH`: Points to built plugin package
- Automatically set during build process
- Used by test suite to locate plugin files

## Performance Benefits

- **Faster builds**: Single package build
- **Less complexity**: Fewer jobs and steps
- **Better reliability**: Standardized commands
- **Easier maintenance**: Industry-standard pattern

## Integration

The workflow integrates with:
- **Package building**: Uses `composer package-plugin`
- **WordPress Test Suite**: Uses existing `bin/install-wp-tests.sh`
- **Asset building**: Uses existing npm build process
- **Composer scripts**: Leverages project's composer.json

## Monitoring

CI status is visible through:
- GitHub Actions tab
- Branch protection rules
- Pull request checks
- Commit status indicators

## Troubleshooting

If tests fail:
1. Check composer.json scripts are correctly defined
2. Verify PHPUnit configurations (phpunit-unit.xml, phpunit-integration.xml)
3. Ensure WordPress Test Suite installs correctly
4. Check SHIELD_PACKAGE_PATH environment variable

## Maintenance

This simplified approach requires minimal maintenance:
- Update PHP versions in matrix as needed
- Adjust branches in workflow triggers
- No complex script management required
