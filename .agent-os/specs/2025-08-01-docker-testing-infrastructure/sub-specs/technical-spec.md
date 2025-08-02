# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-08-01-docker-testing-infrastructure/spec.md

## Technical Requirements

- **Container Architecture**: Multi-service Docker Compose setup with WordPress, MySQL, and test-runner services
- **Base Images**: Official WordPress Docker images with PHP versions 7.4, 8.0, 8.1, 8.2, 8.3, 8.4
- **Database**: MySQL 8.0 to match CI/CD environment with health checks for readiness
- **Volume Strategy**: Plugin source code mounted as volume for live code updates without rebuilds
- **Test Runner**: Custom Docker image with PHPUnit, Composer, and test frameworks
- **Network Architecture**: Docker Compose default network with automatic DNS resolution
- **File Structure**: Docker files organized in `tests/docker/` directory with minimal script approach
- **Bootstrap Integration**: Environment detection in existing bootstrap files rather than Docker-specific bootstraps
- **Package Testing**: Integration with existing `bin/build-package.sh` for production package validation
- **Matrix Testing**: Support for 6 PHP versions × 2 WordPress versions = 12 combinations with parallel execution
- **Performance Optimization**: Multi-layer caching (Composer, npm, Docker layers, assets)
- **Cross-Platform**: Windows PowerShell and Unix bash compatibility with Docker Compose override patterns

## Architecture Details

### Container Services
```yaml
wordpress:
  - Based on official WordPress images
  - PHP versions: 7.4, 8.0, 8.1, 8.2, 8.3, 8.4
  - Volume mount: plugin source code
  - Environment: test configuration

mysql:
  - MySQL 8.0 (matching current CI/CD)
  - Persistent volume for test data
  - Health checks for readiness

test-runner:
  - Custom image with test dependencies
  - PHPUnit, Composer, test frameworks
  - Shares volumes with WordPress
```

### Volume Management
- Repository mounted to `/app` following established patterns
- Package-specific volumes for production testing
- Docker Compose override pattern for Windows compatibility
- Environment variable-based volume configuration

### Environment Detection
- Smart detection of testing mode (source vs package)
- `SHIELD_PACKAGE_PATH` environment variable for package testing
- Automatic dependency installation skipping in package mode
- Enhanced debug output for troubleshooting

## Implementation Specifics

### Docker Configuration
- **Dockerfile**: Custom test runner with git safe.directory configuration
- **docker-compose.yml**: MariaDB 10.2 + test-runner service pattern
- **docker-compose.package.yml**: Override file for package testing on Windows
- **Environment Variables**: TEST_PHP_VERSION, TEST_WP_VERSION, SHIELD_PACKAGE_PATH

### Script Architecture
- **Minimal Scripts**: 2 convenience wrappers (docker-up.sh, docker-up.ps1)
- **Test Execution**: Extended existing `bin/run-tests.ps1` with Docker support
- **Integration Script**: `bin/run-tests-docker.sh` following EDD pattern
- **Package Building**: Leverages existing `bin/build-package.sh` process

### CI/CD Integration
- **GitHub Actions**: `.github/workflows/docker-tests.yml` with dual trigger strategy
- **Automatic Triggers**: Push to develop, main, master branches
- **Manual Triggers**: `workflow_dispatch` for custom PHP/WordPress combinations
- **Matrix Strategy**: Dynamic WordPress version detection with caching
- **Build Optimization**: Node.js setup, npm dependencies, asset building

### Test Validation
- **Unit Tests**: 71 tests, 2483 assertions across matrix
- **Integration Tests**: 33 tests, 231 assertions across matrix
- **Package Validation**: 7 tests ensuring production readiness
- **Performance**: ~3 minutes for complete 12-combination matrix

## External Dependencies

- **Docker Engine**: Required for containerized testing
- **Docker Compose**: v2 syntax for service orchestration
- **WordPress.org API**: For dynamic version detection (`https://api.wordpress.org/core/version-check/1.7/`)
- **GitHub Container Registry**: For Docker layer caching (optional optimization)

**Justification**: Docker provides isolated, reproducible testing environments that ensure consistency across development and CI/CD environments. WordPress.org API enables dynamic version testing without hardcoded versions.

## Performance Criteria

- **Initial Setup**: Complete in < 5 minutes
- **Container Startup**: < 30 seconds with caching
- **Matrix Execution**: ~3 minutes for 12 combinations (parallel)
- **Individual Job**: < 5 minutes per matrix combination
- **Cache Hit Rate**: > 80% for optimized builds
- **Memory Usage**: 34MB peak for integration tests
- **Disk Usage**: Optimized with multi-layer caching

## Security Considerations

- **Network Isolation**: Containers isolated from host network
- **Volume Permissions**: Git safe.directory configuration for container access
- **Environment Variables**: Secure handling of sensitive configuration
- **Base Image Security**: Regular updates to official WordPress images
- **Package Validation**: Production package security through build process

## Monitoring and Validation

- **Success Metrics**: GitHub Actions Run ID 16694657226 validates complete success
- **Test Coverage**: 100% compatibility with existing test infrastructure
- **Cross-Platform**: Validated on Windows, macOS, and Linux environments
- **Production Readiness**: All package validation tests passing
- **Performance Tracking**: Execution time monitoring and cache effectiveness