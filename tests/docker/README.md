# Docker Testing Infrastructure for Shield Security

This directory contains Docker configuration for running Shield Security tests in containerized environments with support for both source and package testing.

## Philosophy: Unified Test Runner

Following WordPress plugin best practices (Yoast, EDD, WooCommerce), we use a unified test runner (`bin/run-tests.ps1`) that supports both native and Docker testing with environment detection in bootstrap files. This maintains 100% backward compatibility while adding Docker support.

## Quick Start

### Prerequisites
- Docker Desktop installed and running
- Docker Compose (included with Docker Desktop)
- 4GB+ RAM allocated to Docker

### Source Testing (Default)

Test against current source code - unified runner approach:

```bash
# Using unified test runner (recommended)
.\bin\run-tests.ps1 all -Docker                    # All tests
.\bin\run-tests.ps1 unit -Docker                   # Unit tests only
.\bin\run-tests.ps1 integration -Docker            # Integration tests only

# Using Composer commands
composer docker:test                           # All tests
composer docker:test:unit                      # Unit tests only
composer docker:test:integration               # Integration tests only
```

### Package Testing

Test against built, production-ready plugin package:

```bash
# Using unified test runner (builds package automatically)
.\bin\run-tests.ps1 all -Docker -Package           # All tests against package
.\bin\run-tests.ps1 unit -Docker -Package          # Unit tests against package

# Using Composer commands
composer docker:test:package                   # All tests against package
```

## Unified Test Runner

### PowerShell: `bin/run-tests.ps1`

The unified test runner supports both native and Docker testing:

```powershell
# Native testing (default)
.\bin\run-tests.ps1 all                            # All tests
.\bin\run-tests.ps1 unit                           # Unit tests only  
.\bin\run-tests.ps1 integration                    # Integration tests only

# Docker testing (add -Docker flag)
.\bin\run-tests.ps1 all -Docker                    # All tests in Docker
.\bin\run-tests.ps1 unit -Docker                   # Unit tests in Docker
.\bin\run-tests.ps1 integration -Docker            # Integration tests in Docker

# Package testing (add -Package flag)
.\bin\run-tests.ps1 all -Docker -Package           # Test built package
.\bin\run-tests.ps1 unit -Docker -Package          # Unit tests on package

# Custom versions
.\bin\run-tests.ps1 all -Docker -PhpVersion 8.1 -WpVersion 6.3
```

## Architecture

### Services
- **wordpress**: WordPress with PHP and test dependencies
- **mysql**: MySQL 8.0 database server  
- **test-runner**: Dedicated container for running tests

### Key Files
- `Dockerfile`: Builds test environment with PHPUnit, Composer, and WordPress test suite
- `docker-compose.yml`: Multi-container environment with flexible volume mapping
- `.env.example`: Available customization options (all optional)
- `../../bin/run-tests.ps1`: Unified test runner with Docker support and package building

### Environment Detection

Bootstrap files automatically detect the testing environment:

1. **Package testing**: When `SHIELD_PACKAGE_PATH` environment variable is set
2. **Docker testing**: When `/var/www/html/wp-content/plugins/wp-simple-firewall` directory exists  
3. **Source testing**: Default mode using current repository directory

This follows the same pattern as Yoast WordPress SEO, Easy Digital Downloads, and WooCommerce. The unified test runner (`bin/run-tests.ps1`) handles both native and Docker environments seamlessly.

## Configuration Options

### Default Settings

Works immediately without any configuration:
- PHP 8.2
- WordPress 6.4  
- MySQL 8.0
- Source code testing

### Environment Variables

All variables have defaults - `.env` file is completely optional:

| Variable | Default | Description |
|----------|---------|-------------|
| `PHP_VERSION` | 8.2 | PHP version |
| `WP_VERSION` | 6.4 | WordPress version |
| `MYSQL_VERSION` | 8.0 | MySQL version |
| `MYSQL_DATABASE` | wordpress_test | Database name |
| `MYSQL_USER` | wordpress | Database user |
| `MYSQL_PASSWORD` | wordpress | Database password |
| `PLUGIN_SOURCE` | `../../` | Plugin source directory (for volume mounting) |
| `SHIELD_PACKAGE_PATH` | (empty) | Set to enable package testing mode |

### Package Testing Configuration

Package testing is handled automatically by the test runner scripts, but you can also configure manually:

```bash
# Create .env for manual package testing
cat > tests/docker/.env << EOF
PLUGIN_SOURCE=/tmp/my-package-dir
SHIELD_PACKAGE_PATH=/var/www/html/wp-content/plugins/wp-simple-firewall
EOF
```

## Manual Docker Commands

For advanced usage or debugging:

```bash
# Start containers manually
docker-compose -f tests/docker/docker-compose.yml up -d

# Stop containers
docker-compose -f tests/docker/docker-compose.yml down

# View logs
docker-compose -f tests/docker/docker-compose.yml logs -f

# Run commands in container
docker-compose -f tests/docker/docker-compose.yml exec test-runner bash

# Run specific test file
docker-compose -f tests/docker/docker-compose.yml exec test-runner composer test:unit -- tests/Unit/PluginJsonSchemaTest.php

# Rebuild images
docker-compose -f tests/docker/docker-compose.yml build --no-cache

# Remove everything (including volumes)
docker-compose -f tests/docker/docker-compose.yml down -v
```

## Composer Integration

The project's `composer.json` includes Docker testing commands that use the unified test runner:

```bash
# Source testing
composer docker:test                    # All tests in Docker
composer docker:test:unit               # Unit tests in Docker
composer docker:test:integration        # Integration tests in Docker

# Package testing (builds package automatically)
composer docker:test:package            # Build package and run all tests in Docker
```

All commands use `bin/run-tests.ps1` internally with appropriate flags.

## Troubleshooting

### Test Runner Script Issues
```bash
# Make scripts executable (Linux/Mac)
chmod +x tests/docker/run-tests.sh

# Check script permissions
ls -la tests/docker/run-tests.*
```

### Database Connection Issues
```bash
# Check service health
docker-compose -f tests/docker/docker-compose.yml ps

# View MySQL logs
docker-compose -f tests/docker/docker-compose.yml logs mysql

# Restart with fresh database
docker-compose -f tests/docker/docker-compose.yml down -v
docker-compose -f tests/docker/docker-compose.yml up -d
```

### Package Build Issues
```bash
# Test package building manually
./bin/build-package.sh /tmp/test-package

# Check package structure
ls -la /tmp/test-package/
ls -la /tmp/test-package/src/lib/vendor_prefixed/
```

### Permission Issues (Linux/Mac)
```bash
# Fix ownership
sudo chown -R $(whoami):$(whoami) .

# Check Docker group membership
groups $USER
```

### Windows-Specific Issues
- Ensure Docker Desktop uses WSL2 backend
- Allocate 4GB+ RAM in Docker Desktop settings
- Use PowerShell or Git Bash, not Command Prompt
- Ensure file sharing is enabled for the project directory

## Development Workflow

### Typical Development Cycle
```powershell
# 1. Make code changes
# 2. Test changes with source testing
.\bin\run-tests.ps1 unit -Docker

# 3. Test full integration
.\bin\run-tests.ps1 integration -Docker

# 4. Before release: test packaged plugin
.\bin\run-tests.ps1 all -Docker -Package
```

### CI/CD Integration
The Docker infrastructure is designed to work in CI/CD environments:
- No interactive prompts
- Proper exit codes
- Comprehensive error reporting
- Automatic cleanup

## Maintenance

### Keeping Images Updated
```bash
# Update base images
docker-compose -f tests/docker/docker-compose.yml pull
docker-compose -f tests/docker/docker-compose.yml build --no-cache

# Clean up old images
docker system prune -f
```

### Performance Optimization
```bash
# Use Docker BuildKit for faster builds
export DOCKER_BUILDKIT=1
docker-compose -f tests/docker/docker-compose.yml build --no-cache

# Allocate more resources in Docker Desktop settings:
# - Memory: 4GB+ recommended
# - CPUs: 2+ recommended  
# - Disk space: 20GB+ recommended
```

## Next Steps
- See main project README for non-Docker testing options
- Check `.github/workflows/` for CI/CD integration examples  
- View unified test runner: `.\bin\run-tests.ps1` supports both native and Docker testing
- Report issues in the project's issue tracker