# Testing Guide

## Quick Start

### Option 1: Docker Testing (Recommended)
**Zero setup required** - runs in isolated containers:

```bash
# One-time setup (< 5 minutes)
./tests/docker/scripts/setup.sh

# Run tests
./tests/docker/scripts/run-tests.sh unit        # Unit tests only
./tests/docker/scripts/run-tests.sh integration # Integration tests only
./tests/docker/scripts/run-tests.sh all         # All tests
```

**Windows PowerShell:**
```powershell
.\tests\docker\scripts\setup.ps1
.\tests\docker\scripts\run-tests.ps1 unit
```

See [Docker Testing Documentation](tests/docker/README.md) for details.

### Option 2: Local Testing
Run tests with these **3 commands only**:

```bash
composer test                # All tests (recommended)
composer test:unit           # Unit tests only
composer test:integration    # Integration tests only
```

**Optional PowerShell wrapper:**
```powershell
.\bin\run-tests.ps1             # All tests
.\bin\run-tests.ps1 unit        # Unit tests only
.\bin\run-tests.ps1 integration # Integration tests only
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

```bash
WP_TESTS_DB_NAME=wordpress_test
WP_TESTS_DB_USER=root
WP_TESTS_DB_PASSWORD=root
WP_TESTS_DB_HOST=127.0.0.1
```

## Docker vs Local Testing

**Docker Testing Benefits:**
- ✅ Zero local setup required
- ✅ Consistent environment across all machines
- ✅ Parallel PHP/WordPress version testing
- ✅ No conflicts with local development
- ✅ Identical to CI/CD environment

**Local Testing Benefits:**
- ✅ Faster execution (no container overhead)
- ✅ Direct debugging with local tools
- ✅ No Docker dependency

## Troubleshooting

**Tests not found**: Run `composer install`
**Database errors**: Verify MySQL is running and test database exists
**WordPress test library missing**: Re-run setup script above
**Docker issues**: See [Docker Testing Documentation](tests/docker/README.md)

---

**That's it.** If you need more than this page explains, the approach is too complex.