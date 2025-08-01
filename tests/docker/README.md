# Docker Testing Infrastructure for Shield Security

This directory contains Docker configuration for running Shield Security tests in containerized environments.

## Quick Start

### Prerequisites
- Docker Desktop installed and running
- Docker Compose (included with Docker Desktop)
- 4GB+ RAM allocated to Docker

### Setup (First Time)

**Linux/Mac:**
```bash
cd tests/docker
./scripts/setup.sh
```

**Windows:**
```powershell
cd tests\docker
.\scripts\setup.ps1
```

### Running Tests

**Run all tests:**
```bash
./scripts/run-tests.sh
```

**Run unit tests only:**
```bash
./scripts/run-tests.sh --unit
```

**Run integration tests only:**
```bash
./scripts/run-tests.sh --integration
```

**Run specific test file:**
```bash
./scripts/run-tests.sh tests/Unit/PluginJsonSchemaTest.php
```

**Windows users:** Use `.\scripts\run-tests.ps1` with the same arguments.

## Architecture

### Services
- **wordpress**: WordPress 6.4 with PHP 8.2 and test dependencies
- **mysql**: MySQL 8.0 database server
- **test-runner**: Dedicated container for running tests

### Key Files
- `Dockerfile`: Builds the test environment with PHPUnit and dependencies
- `docker-compose.yml`: Defines the multi-container test environment
- `.env.example`: Environment variables template (copy to `.env`)

### Scripts
- `setup.sh/ps1`: Initial setup and image building
- `run-tests.sh/ps1`: Test execution wrapper
- `wait-for-db.sh`: Database readiness check

## Environment Variables

Copy `.env.example` to `.env` and modify as needed:
```
WORDPRESS_DB_HOST=mysql
WORDPRESS_DB_NAME=wordpress_test
WORDPRESS_DB_USER=root
WORDPRESS_DB_PASSWORD=root
```

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