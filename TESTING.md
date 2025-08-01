# Testing Guide

## Quick Start

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

## Troubleshooting

**Tests not found**: Run `composer install`
**Database errors**: Verify MySQL is running and test database exists
**WordPress test library missing**: Re-run setup script above

---

**That's it.** If you need more than this page explains, the approach is too complex.