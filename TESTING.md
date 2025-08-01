# Testing Guide

## Quick Start

### Option 1: Docker Testing (Recommended)
**Zero setup required** - runs in isolated containers using unified test runner:

```powershell
# Source testing (test current code)
.\bin\run-tests.ps1 all -Docker                    # All tests
.\bin\run-tests.ps1 unit -Docker                   # Unit tests only
.\bin\run-tests.ps1 integration -Docker            # Integration tests only

# Package testing (test production build - builds automatically)
.\bin\run-tests.ps1 all -Docker -Package           # Build and test package
.\bin\run-tests.ps1 unit -Docker -Package          # Unit tests on package

# Alternative: Composer commands (use unified runner internally)
composer docker:test                            # All tests
composer docker:test:unit                       # Unit tests only
composer docker:test:integration                # Integration tests only
composer docker:test:package                    # Package testing
```

See [Docker Testing Documentation](tests/docker/README.md) for complete details.

### Option 2: Local Testing
Run tests using unified test runner (native mode) or direct Composer commands:

```powershell
# Unified test runner (recommended - native mode)
.\bin\run-tests.ps1 all                        # All tests
.\bin\run-tests.ps1 unit                       # Unit tests only
.\bin\run-tests.ps1 integration                # Integration tests only

# Direct Composer commands
composer test                                   # All tests
composer test:unit                              # Unit tests only
composer test:integration                       # Integration tests only
```

## Prerequisites

- PHP 7.4+, Composer, MySQL
- Run once: `composer install`
- For integration tests: `bash bin/install-wp-tests.sh wordpress_test root '' localhost latest`


## Test Structure

```
tests/
├── Unit/           # Fast unit tests (Brain Monkey)
├── Integration/    # WordPress integration tests
└── fixtures/       # Test data
```

- **Unit tests**: Business logic, no WordPress required
- **Integration tests**: Full WordPress environment with database

## Environment Variables

### Native Testing
```bash
WP_TESTS_DB_NAME=wordpress_test
WP_TESTS_DB_USER=root
WP_TESTS_DB_PASSWORD=root
WP_TESTS_DB_HOST=127.0.0.1
```

### Docker Testing
Docker testing automatically configures environment variables. The unified test runner creates `.env` files as needed:

```bash
PHP_VERSION=8.2                    # PHP version (configurable)
WP_VERSION=6.4                     # WordPress version (configurable)
SHIELD_PACKAGE_PATH=               # Set for package testing mode
PLUGIN_SOURCE=                     # Plugin source directory path
```

## GitHub Actions Docker Workflow

Shield Security includes **optional GitHub Actions Docker CI/CD testing** following evidence-based patterns:

**Accessing the Workflow**:
1. Navigate to the **Actions** tab in the GitHub repository
2. Select **"Shield Security Docker CI"** workflow
3. Click **"Run workflow"** button
4. Configure options:
   - **PHP Version**: 7.4, 8.0, 8.1, 8.2, 8.3, or 8.4 (default: 8.2)
   - **WordPress Version**: Any valid version (default: 6.4)
   - **Test Package**: Option to test built production package

**Evidence-Based Design**:
- **Manual Trigger Only**: Based on Easy Digital Downloads pattern - prevents CI/CD overhead
- **Simple Architecture**: MariaDB + test-runner following EDD's `docker-compose-phpunit.yml`
- **Standard Integration**: Uses existing `bin/install-wp-tests.sh` and PHPUnit configurations
- **Proven Pattern**: Minimal script approach based on EDD's `run-tests-internal-only.sh`

**Workflow Features**:
- Configurable PHP and WordPress versions
- Uses MariaDB 10.2 (following EDD pattern)
- Mounts entire repository to `/app` container
- Automatic environment configuration
- Clean container cleanup after execution

**Why Manual-Only?**
Research of established WordPress plugins (Yoast SEO, EDD, WooCommerce) showed that most successful plugins use native GitHub Actions without automated Docker CI, reserving Docker for optional/manual testing scenarios.

## Docker vs Local Testing

**Docker Testing Benefits:**
- ✅ Zero local setup required
- ✅ Consistent environment across all machines
- ✅ Parallel PHP/WordPress version testing
- ✅ No conflicts with local development
- ✅ Identical to CI/CD environment
- ✅ Automatic package building and testing
- ✅ Environment detection in bootstrap files
- ✅ GitHub Actions integration for CI/CD testing

**Local Testing Benefits:**
- ✅ Faster execution (no container overhead)
- ✅ Direct debugging with local tools
- ✅ No Docker dependency
- ✅ Same unified test runner works for both modes

## Troubleshooting

**Tests not found**: Run `composer install`
**Database errors**: Verify MySQL is running and test database exists
**WordPress test library missing**: Re-run setup script above
**Docker issues**: See [Docker Testing Documentation](tests/docker/README.md)

---

**That's it.** If you need more than this page explains, the approach is too complex.