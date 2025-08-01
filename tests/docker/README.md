# Docker Testing Infrastructure for Shield Security

This directory contains Docker configuration for running Shield Security tests in containerized environments.

## Philosophy: Minimal Scripts, Maximum Simplicity

We use Docker Compose directly and leverage Composer for testing commands. The only scripts provided are minimal convenience wrappers for starting containers.

## Quick Start

### Prerequisites
- Docker Desktop installed and running
- Docker Compose (included with Docker Desktop)
- 4GB+ RAM allocated to Docker

### No Setup Required - Just Run!

```bash
# Start containers (works immediately with defaults)
docker-compose up -d

# Or use the minimal convenience script:
./docker-up.sh       # Linux/Mac
.\docker-up.ps1      # Windows
```

**That's it!** No configuration files to copy, no manual setup needed.

### Running Tests

**Using Composer (Recommended):**
```bash
# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run integration tests only
composer test:integration
```

**Using Docker Compose directly:**
```bash
# Run all tests
docker-compose exec test-runner phpunit

# Run specific test file
docker-compose exec test-runner phpunit tests/Unit/PluginJsonSchemaTest.php

# Run with specific configuration
docker-compose exec test-runner phpunit -c phpunit-unit.xml
```

## Architecture

### Services
- **wordpress**: WordPress 6.4 with PHP 8.2 and test dependencies
- **mysql**: MySQL 8.0 database server
- **test-runner**: Dedicated container for running tests

### Key Files
- `Dockerfile`: Builds the test environment with PHPUnit and dependencies
- `docker-compose.yml`: Defines the multi-container test environment (works without configuration)
- `.env.example`: Shows available customization options (optional)
- `docker-up.sh/ps1`: Minimal convenience scripts to start containers

## Optional Customization

The Docker setup works out of the box with sensible defaults:
- PHP 8.2
- WordPress 6.4
- MySQL 8.0

### Customizing Versions (Optional)

To use different versions, create a `.env` file (see `.env.example` for all options):

```bash
# Example: Test with PHP 8.3 and WordPress 6.5
echo "PHP_VERSION=8.3" > .env
echo "WP_VERSION=6.5" >> .env
docker-compose build --no-cache
docker-compose up -d
```

### Available Environment Variables

All variables have defaults, so `.env` is completely optional:

| Variable | Default | Description |
|----------|---------|-------------|
| `PHP_VERSION` | 8.2 | PHP version |
| `WP_VERSION` | 6.4 | WordPress version |
| `MYSQL_VERSION` | 8.0 | MySQL version |
| `MYSQL_DATABASE` | wordpress_test | Database name |
| `MYSQL_USER` | wordpress | Database user |
| `MYSQL_PASSWORD` | wordpress | Database password |

## Troubleshooting

### Database Connection Issues
If tests fail with database errors:
1. Ensure MySQL container is healthy: `docker-compose ps`
2. Check logs: `docker-compose logs mysql`
3. Restart containers: `docker-compose down && docker-compose up -d`

### Permission Issues (Linux/Mac)
If you encounter permission errors:
```bash
sudo chown -R $(whoami):$(whoami) .
```

### Windows-Specific Issues
- Ensure Docker Desktop is using WSL2 backend
- Allocate sufficient resources in Docker Desktop settings
- Use PowerShell or Git Bash, not Command Prompt

## Direct Docker Commands Reference

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f

# Run commands in container
docker-compose exec test-runner bash

# Rebuild images
docker-compose build --no-cache

# Remove everything (including volumes)
docker-compose down -v
```

## Integration with Composer

The project's `composer.json` includes commands for both native and Docker testing. When running inside Docker containers, use the standard Composer commands.

## Maintenance

### Updating Images
```bash
docker-compose pull
docker-compose build --no-cache
```

### Cleaning Up
```bash
docker-compose down -v  # Removes containers and volumes
docker system prune     # Removes unused images/containers
```

## Next Steps
- See main project README for non-Docker testing options
- Check `.github/workflows/` for CI/CD integration examples
- Report issues in the project's issue tracker