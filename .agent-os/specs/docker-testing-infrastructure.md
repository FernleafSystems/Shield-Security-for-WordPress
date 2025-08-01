# Docker-Based Testing Infrastructure

## Overview
This specification outlines the implementation of a Docker-based testing infrastructure for Shield Security WordPress plugin. The approach maintains 100% backward compatibility with the existing testing setup while enabling identical test execution locally and in CI/CD environments.

## Context
Shield Security currently has a mature testing infrastructure that was recently modernized in 2025. This Docker implementation will enhance (not replace) the existing setup by providing containerized testing capabilities that ensure consistency across all environments.

## Goals
1. Enable identical test execution locally and in CI/CD environments
2. Maintain full backward compatibility with existing testing infrastructure
3. Simplify environment setup for new developers (< 5 minutes)
4. Support future matrix testing across PHP/WordPress/MySQL versions
5. Leverage existing packaging and testing knowledge

## Non-Goals
1. Replacing the existing testing infrastructure
2. Modifying current test files or structure
3. Changing developer workflows (Docker remains optional)
4. Implementing complex orchestration beyond basic needs

## Architecture

### Container Structure
```yaml
# Docker Services Architecture
services:
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

### File Structure
```
tests/
├── docker/
│   ├── Dockerfile                    # Main test runner image
│   ├── Dockerfile.wordpress          # Custom WordPress image if needed
│   ├── docker-compose.yml            # Full test environment
│   ├── docker-compose.unit.yml       # Unit tests only
│   ├── docker-compose.integration.yml # Integration tests only
│   ├── .env.example                  # Environment configuration
│   └── scripts/
│       ├── setup.sh                  # Initial setup script
│       ├── setup.ps1                 # Windows setup script
│       ├── run-tests.sh              # Test execution wrapper
│       ├── run-tests.ps1             # Windows test wrapper
│       └── wait-for-db.sh            # Database readiness check
```

## Technical Decisions

### 1. Base Images
- **Decision**: Use official WordPress Docker images
- **Rationale**: Well-maintained, security updates, community support
- **Alternative considered**: Custom Ubuntu/PHP images (rejected for complexity)

### 2. Database Choice
- **Decision**: MySQL 8.0 to match CI/CD
- **Rationale**: Production parity, existing test compatibility
- **Alternative considered**: MariaDB (postponed for future consideration)

### 3. Volume Strategy
- **Decision**: Mount plugin source as volume
- **Rationale**: Live code updates, no rebuild needed
- **Trade-off**: Slightly slower than COPY, but better DX

### 4. Test Isolation
- **Decision**: Separate configs for unit vs integration
- **Rationale**: Different resource requirements, faster unit tests
- **Implementation**: docker-compose.unit.yml excludes WordPress/MySQL

### 5. Networking
- **Decision**: Use Docker Compose default network
- **Rationale**: Simplicity, automatic DNS resolution
- **Security**: Isolated from host network

## Implementation Plan

### Phase 1: Basic Docker Setup (Week 1)

#### Task 1.1: Create Docker Directory Structure
- **Agent**: `file-creator`
- **Description**: Create tests/docker/ directory structure with all subdirectories
- **Dependencies**: None
- **Deliverables**: Complete directory structure ready for files

#### Task 1.2: Design Base Dockerfile
- **Agent**: `software-engineer-expert`
- **Description**: Create Dockerfile for test runner with PHP, Composer, PHPUnit
- **Dependencies**: Task 1.1
- **Deliverables**: tests/docker/Dockerfile with all test dependencies

#### Task 1.3: Create Docker Compose Configuration
- **Agent**: `software-engineer-expert`
- **Description**: Design docker-compose.yml with WordPress and MySQL services
- **Dependencies**: Task 1.1
- **Deliverables**: tests/docker/docker-compose.yml with service definitions

#### Task 1.4: Implement Setup Scripts
- **Agent**: `powershell-script-developer`
- **Description**: Create cross-platform setup scripts (setup.sh and setup.ps1)
- **Dependencies**: Tasks 1.2, 1.3
- **Deliverables**: Executable setup scripts for both platforms

#### Task 1.5: Write Initial Documentation
- **Agent**: `documentation-architect`
- **Description**: Create Docker setup guide and README
- **Dependencies**: Tasks 1.1-1.4
- **Deliverables**: tests/docker/README.md with setup instructions

### Phase 2: Test Integration (Week 2)

#### Task 2.1: Analyze Existing Test Infrastructure
- **Agent**: `general-purpose`
- **Description**: Deep analysis of current bootstrap files and test configuration
- **Dependencies**: Phase 1 complete
- **Deliverables**: Technical report on integration requirements

#### Task 2.2: Create Docker-Compatible Bootstrap
- **Agent**: `software-engineer-expert`
- **Description**: Adapt test bootstraps to work in Docker environment
- **Dependencies**: Task 2.1
- **Deliverables**: Docker-compatible bootstrap files

#### Task 2.3: Configure Volume Mappings
- **Agent**: `software-engineer-expert`
- **Description**: Set up proper volume mounts for code and test data
- **Dependencies**: Task 2.2
- **Deliverables**: Updated docker-compose with volume configuration

#### Task 2.4: Implement Test Runner Scripts
- **Agent**: `php-runtime-executor`
- **Description**: Create wrapper scripts that execute tests inside containers
- **Dependencies**: Tasks 2.2, 2.3
- **Deliverables**: run-tests.sh and run-tests.ps1 scripts

#### Task 2.5: Validate Test Execution
- **Agent**: `test-runner`
- **Description**: Run all existing tests in Docker environment
- **Dependencies**: Tasks 2.1-2.4
- **Deliverables**: Test execution report, any necessary fixes

### Phase 3: CI/CD Integration (Week 3)

#### Task 3.1: Analyze Current GitHub Actions
- **Agent**: `cicd-testing-engineer`
- **Description**: Review .github/workflows/minimal.yml for integration points
- **Dependencies**: Phase 2 complete
- **Deliverables**: CI/CD integration plan

#### Task 3.2: Create Docker CI Workflow
- **Agent**: `cicd-testing-engineer`
- **Description**: Design GitHub Actions workflow using Docker
- **Dependencies**: Task 3.1
- **Deliverables**: .github/workflows/docker-tests.yml

#### Task 3.3: Implement Parallel Testing
- **Agent**: `cicd-testing-engineer`
- **Description**: Configure matrix testing for PHP/WP versions
- **Dependencies**: Task 3.2
- **Deliverables**: Updated workflow with matrix strategy

#### Task 3.4: Update CI Documentation
- **Agent**: `documentation-architect`
- **Description**: Document CI/CD Docker integration
- **Dependencies**: Tasks 3.1-3.3
- **Deliverables**: Updated CI/CD documentation

### Phase 4: Package Testing (Week 4)

#### Task 4.1: Extract Packaging Logic
- **Agent**: `general-purpose`
- **Description**: Analyze bin/build-plugin-package.ps1 for packaging logic
- **Dependencies**: Phases 1-3 complete
- **Deliverables**: Packaging requirements document

#### Task 4.2: Dockerize Package Building
- **Agent**: `software-engineer-expert`
- **Description**: Implement package building inside Docker
- **Dependencies**: Task 4.1
- **Deliverables**: Docker-based packaging scripts

#### Task 4.3: Test Packaged Plugin
- **Agent**: `test-runner`
- **Description**: Validate packaged plugin installation and tests
- **Dependencies**: Task 4.2
- **Deliverables**: Package testing report

#### Task 4.4: Complete Documentation
- **Agent**: `documentation-architect`
- **Description**: Finalize all Docker testing documentation
- **Dependencies**: All tasks complete
- **Deliverables**: Comprehensive Docker testing guide

## Success Criteria

### Functional Requirements
- [ ] All existing unit tests pass in Docker environment
- [ ] All existing integration tests pass in Docker environment
- [ ] Package building works inside Docker containers
- [ ] Tests produce identical results locally and in CI/CD
- [ ] No modifications to existing test files

### Performance Requirements
- [ ] Initial setup completes in < 5 minutes
- [ ] Test execution time within 10% of native execution
- [ ] Container startup time < 30 seconds

### Developer Experience
- [ ] Single command to run all tests
- [ ] Clear error messages and debugging options
- [ ] Works on Windows, macOS, and Linux
- [ ] Optional usage (existing methods still work)

## Risk Mitigation

### Risk 1: Windows Docker Performance
- **Mitigation**: Provide WSL2 setup guide, optimize volume mounts
- **Fallback**: Existing PowerShell scripts remain available

### Risk 2: Database Compatibility
- **Mitigation**: Use same MySQL version as CI/CD
- **Fallback**: Document any edge cases, provide workarounds

### Risk 3: Learning Curve
- **Mitigation**: Comprehensive documentation, video tutorials
- **Fallback**: Docker remains optional, not required

## Future Enhancements

### Phase 5: Matrix Testing (Future)
- Automated testing across multiple PHP versions
- WordPress version compatibility matrix
- MySQL/MariaDB version testing

### Phase 6: Advanced Features (Future)
- Visual regression testing
- Performance benchmarking
- Security scanning integration
- Debugging with Xdebug

## Monitoring and Maintenance

### Metrics to Track
- Setup success rate
- Test execution time
- Developer adoption rate
- CI/CD reliability

### Maintenance Tasks
- Update base images monthly
- Review security advisories
- Update documentation based on feedback
- Monitor for deprecations

## Appendix

### Example Commands
```bash
# Run all tests
./tests/docker/scripts/run-tests.sh

# Run unit tests only
./tests/docker/scripts/run-tests.sh --unit

# Run specific test file
./tests/docker/scripts/run-tests.sh tests/Unit/PluginJsonSchemaTest.php

# Run with specific PHP version
PHP_VERSION=8.2 ./tests/docker/scripts/run-tests.sh

# Package and test
./tests/docker/scripts/run-tests.sh --package
```

### Environment Variables
```bash
PHP_VERSION=8.2          # PHP version to use
WP_VERSION=latest        # WordPress version
MYSQL_VERSION=8.0        # MySQL version
SKIP_DB_CREATE=false     # Skip database creation
DEBUG=false              # Enable debug output
```

### Troubleshooting Guide
Will be created during implementation based on discovered issues.

## Status Tracking

### Current Status: Phase 1 Complete
- [x] Research completed
- [x] Architecture designed
- [x] Tasks defined and assigned
- [x] Phase 1: Basic Docker Setup (Completed 2025-08-01)
  - [x] Task 1.1: Create Docker Directory Structure
  - [x] Task 1.2: Design Base Dockerfile
  - [x] Task 1.3: Create Docker Compose Configuration
  - [x] Task 1.4: Implement Setup Scripts
  - [x] Task 1.5: Write Initial Documentation
- [ ] Phase 2: Test Integration
- [ ] Phase 3: CI/CD Integration
- [ ] Phase 4: Package Testing

### Next Steps
1. Phase 1 implementation complete - ready for review
2. Test the Docker setup to verify functionality
3. Proceed to Phase 2: Test Integration (pending approval)
4. Create working group for feedback on Phase 1 implementation