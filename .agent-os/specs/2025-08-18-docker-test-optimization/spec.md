# Docker Test Optimization Specification

## Problem Statement

### Phase 1 Complete: Build Separation ✅
Shield Security's Docker-based testing infrastructure has undergone Phase 1 optimization. Key improvements achieved:

1. **Sequential Execution Pattern**: The current script `/mnt/d/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/bin/run-docker-tests.sh` executes tests sequentially, testing WordPress version 6.8.2 completely before beginning WordPress version 6.7.3 testing.

2. **Redundant Build Operations**: ✅ RESOLVED - Plugin package now built once and reused across WordPress versions (Phase 1 complete).

3. **Container Startup Overhead**: ✅ IMPROVED - Version-specific Docker images with pre-installed WordPress test framework eliminate runtime installation issues.

4. **Limited Matrix Coverage**: Currently tests only PHP 7.4, leaving PHP versions 8.0, 8.1, 8.2, 8.3, and 8.4 untested locally (target for Phase 5).

5. **Developer Feedback Delay**: ✅ IMPROVED - Phase 1 build-once pattern provides performance foundation for further optimization.

### Impact Assessment
- **Development Velocity**: Slow feedback loops reduce developer productivity and encourage batching changes rather than incremental testing
- **Bug Discovery**: Delayed feedback increases the cost of bug discovery and resolution
- **CI/Local Parity**: Limited local PHP matrix testing means issues may only surface in CI, increasing debugging complexity
- **Resource Utilization**: Multi-core development machines are underutilized during test execution

## Requirements

### Functional Requirements

#### FR1: Performance Optimization
- **FR1.1**: Reduce full test execution time from 10+ minutes to under 2 minutes
- **FR1.2**: Achieve sub-minute execution for single PHP/WordPress combinations
- **FR1.3**: Maintain current test coverage (71 unit tests, 33 integration tests)
- **FR1.4**: Support parallel execution of up to 12 test combinations (6 PHP × 2 WordPress)

#### FR2: PHP Version Matrix Support  
- **FR2.1**: Support PHP versions 7.4, 8.0, 8.1, 8.2, 8.3, 8.4
- **FR2.2**: Prepare architecture for PHP 8.5 when released
- **FR2.3**: Allow selective PHP version testing for focused debugging
- **FR2.4**: Maintain version-specific compatibility checks

#### FR3: WordPress Version Matrix Support
- **FR3.1**: Continue testing latest WordPress version (currently 6.8.2)
- **FR3.2**: Continue testing previous major WordPress version (currently 6.7.3)  
- **FR3.3**: Support dynamic version detection using existing `.github/scripts/detect-wp-versions.sh`
- **FR3.4**: Handle WordPress version changes without manual intervention

#### FR4: CI Parity Maintenance
- **FR4.1**: Local test results must match GitHub Actions CI results exactly
- **FR4.2**: Test the same plugin package structure as deployed to production
- **FR4.3**: Use identical PHP extensions, WordPress test framework, and database configuration as CI
- **FR4.4**: Maintain package testing mode (SHIELD_PACKAGE_PATH environment variable support)

### Non-Functional Requirements

#### NFR1: Maintainability
- **NFR1.1**: Preserve single script architecture (`bin/run-docker-tests.sh`)
- **NFR1.2**: Maintain incremental evolution capability - each phase must be testable
- **NFR1.3**: Ensure rollback capability for each optimization phase
- **NFR1.4**: Document all configuration changes and their impact

#### NFR2: Resource Efficiency
- **NFR2.1**: Optimize for typical development machine resources (8 cores, 16GB RAM)
- **NFR2.2**: Reuse Docker base images to minimize rebuild overhead
- **NFR2.3**: Implement "build once, test many" pattern for plugin packages
- **NFR2.4**: Minimize disk space usage through smart layer caching

#### NFR3: Reliability
- **NFR3.1**: Ensure database isolation between parallel tests
- **NFR3.2**: Handle test failures gracefully without affecting other parallel tests
- **NFR3.3**: Provide clear failure reporting across all parallel execution streams
- **NFR3.4**: Maintain test result aggregation and summary reporting

#### NFR4: Development Experience
- **NFR4.1**: Preserve zero-configuration usage (`./bin/run-docker-tests.sh`)
- **NFR4.2**: Provide clear progress indicators during parallel execution
- **NFR4.3**: Enable selective testing (e.g., only PHP 8.2 with latest WordPress)
- **NFR4.4**: Maintain comprehensive logging and debugging capabilities

## Constraints

### Technical Constraints
- **TC1**: No external container registry usage initially - all optimization must work with local Docker images
- **TC2**: Must preserve existing Docker Compose configuration structure in `tests/docker/`
- **TC3**: Cannot modify existing PHPUnit test files or test logic
- **TC4**: Must maintain compatibility with existing CI/CD pipeline

### Process Constraints  
- **PC1**: Implementation must follow evolutionary approach - modify, test, verify, proceed
- **PC2**: Each phase must be independently verifiable and rollback-capable
- **PC3**: Cannot create multiple script variants - must modify single script incrementally
- **PC4**: Must validate each phase against both unit and integration test suites

### Resource Constraints
- **RC1**: Development machine limitations (typically 8 cores, 16GB RAM)
- **RC2**: Docker Desktop resource allocation constraints on Windows/macOS
- **RC3**: Disk space considerations for multiple PHP base images
- **RC4**: Network bandwidth for WordPress version downloads

## Solution Architecture

### Core Architectural Principles

#### Build Once, Test Many Pattern
The fundamental optimization strategy centers on separating the build phase from the test execution phase:

1. **Single Package Build**: Plugin package built once to `/tmp/shield-package-local` containing:
   - Production code with `vendor_prefixed` dependencies via Strauss
   - All necessary assets compiled via `npm run build`  
   - Cleaned autoload files and proper directory structure

2. **Package Reuse**: Same package mounted to all test containers via Docker volume mapping using `-v /tmp/shield-package-local:/package` parameter
3. **Environment Isolation**: Each test container receives identical package mounted at `/package` but tests against different PHP/WordPress combinations
4. **Version Matrix**: Package remains constant while test environments vary through environment variables (PHP_VERSION, WP_VERSION)

#### Container Optimization Strategy
Implement multi-stage Docker image hierarchy to minimize redundant operations:

1. **Base Images**: Create reusable images per PHP version (`shield-php{VERSION}-base`) containing:
   - Ubuntu 22.04 base system with security updates
   - PHP version with all required extensions (mysql, xml, mbstring, curl, zip, gd, intl, bcmath, soap)
   - PHPUnit with version-appropriate compatibility (9.6 for PHP 7.x, 10.5 for PHP 8.0-8.1, 11.0 for PHP 8.2+)
   - System dependencies (git, subversion, curl, mysql-client)
   - Composer 2.x for dependency management

2. **Runtime Flexibility**: WordPress test framework downloaded at runtime based on environment variables
3. **Package Mounting**: Plugin package mounted as volume rather than copied into image
4. **Database Isolation**: Each test container connects to dedicated database instance

#### Parallel Execution Strategy
Implement progressive parallelization using industry-standard approaches:

1. **Phase 1-2**: Bash built-in parallelization (`&` and `wait` commands)
2. **Phase 3**: Docker Compose service multiplication  
3. **Phase 6+**: GNU parallel for advanced job distribution
4. **Database Strategy**: Multiple MySQL instances or database-per-test-run isolation

### Implementation Phases Overview

#### Phase 1: Build Separation (30% speedup)
- **Objective**: Move plugin package build outside test loop
- **Method**: Build once, reuse across WordPress versions
- **Expected Time Reduction**: 10+ minutes → 7 minutes

#### Phase 2: WordPress Version Parallelization (2x speedup)  
- **Objective**: Test both WordPress versions simultaneously
- **Method**: Bash background processes with `&` and `wait`
- **Expected Time Reduction**: 7 minutes → 3.5 minutes

#### Phase 3: Test Type Splitting (2x speedup)
- **Objective**: Run unit and integration tests in parallel
- **Method**: Separate Docker Compose services for test types
- **Expected Time Reduction**: 3.5 minutes → 1.75 minutes  

#### Phase 4: Base Image Caching (20% speedup)
- **Objective**: Pre-build PHP environments for instant startup
- **Method**: Create and reuse `shield-php{VERSION}-base` images
- **Expected Time Reduction**: 1.75 minutes → 1.4 minutes

#### Phase 5: PHP Matrix Expansion (maintain performance)
- **Objective**: Add PHP 8.0, 8.1, 8.2, 8.3, 8.4 support
- **Method**: Parallel execution across PHP versions  
- **Expected Time**: Maintain 1.4 minutes despite more tests

#### Phase 6: GNU Parallel Integration (30% speedup)
- **Objective**: Advanced job distribution and resource optimization
- **Method**: Replace bash parallelization with GNU parallel
- **Expected Time Reduction**: 1.4 minutes → 1 minute

#### Phase 7: Container Pooling (20% speedup)  
- **Objective**: Eliminate container startup overhead
- **Method**: Pre-start container pool for immediate test execution
- **Expected Time Reduction**: 1 minute → 45 seconds

#### Phase 8: Result Aggregation Enhancement (maintain performance)
- **Objective**: Unified reporting across parallel streams
- **Method**: Centralized result collection and summary generation
- **Expected Time**: Maintain 45 seconds with enhanced reporting

## Acceptance Criteria

### Phase-Specific Success Criteria

#### Phase 1 Completion Criteria
- **AC1.1**: ✅ Plugin package built exactly once per test run
- **AC1.2**: ✅ Same package used for both WordPress 6.8.2 and 6.7.3 tests
- **AC1.3**: ✅ Execution time improvement achieved with build-once pattern
- **AC1.4**: ✅ All existing tests pass with identical results to sequential execution
- **AC1.5**: ✅ Version-specific Docker images eliminate WordPress framework runtime installation
- **AC1.6**: ✅ Zero runtime WordPress test framework installation issues

### Phase 1 Technical Implementation Summary

**Build Separation Achieved:**
The local test script `/mnt/d/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/bin/run-docker-tests.sh` now implements a build-once pattern:

1. **Single Package Build**: Plugin package built once at `/tmp/shield-package-local` using existing `./bin/build-package.sh`
2. **Version-Specific Images**: Docker images built for each WordPress version (`shield-test-runner:wp-6.8.2`, `shield-test-runner:wp-6.7.3`)
3. **WordPress Framework Pre-Installation**: WordPress test framework downloaded during Docker build phase
4. **Package Reuse**: Same package mounted to all test containers via volume mapping
5. **Environment Consistency**: Proper environment variables set for package testing mode

**Performance Foundation Established:**
- Eliminated redundant plugin package builds (was rebuilding for each WordPress version)
- Eliminated runtime WordPress test framework installation (now done at Docker build time)
- Established foundation for Phase 2 parallel execution
- Maintained zero-configuration usage pattern

**Next Phase Ready:**
Phase 2 can now implement parallel WordPress version testing since the package build is separated and both Docker images are available.

#### Phase 2 Completion Criteria  
- **AC2.1**: WordPress 6.8.2 and 6.7.3 tests execute simultaneously
- **AC2.2**: Total execution time reduced to 3.5 minutes or less
- **AC2.3**: Database isolation confirmed - no test interference
- **AC2.4**: Both test streams complete successfully with aggregated results

#### Phase 3 Completion Criteria
- **AC3.1**: Unit and integration tests run in parallel for each WordPress version
- **AC3.2**: Total execution time reduced to 1.75 minutes or less  
- **AC3.3**: 4 parallel test streams (2 WP versions × 2 test types) execute successfully
- **AC3.4**: Test result aggregation provides clear success/failure summary

#### Phase 4 Completion Criteria
- **AC4.1**: PHP 7.4 base image builds and caches successfully
- **AC4.2**: Container startup time reduced to under 10 seconds  
- **AC4.3**: Total execution time reduced to 1.4 minutes or less
- **AC4.4**: Base image reuse confirmed across multiple script runs

#### Phase 5 Completion Criteria
- **AC5.1**: All PHP versions (7.4, 8.0, 8.1, 8.2, 8.3, 8.4) supported
- **AC5.2**: Matrix execution completes within 1.4 minute target
- **AC5.3**: PHP version-specific compatibility issues identified and documented
- **AC5.4**: Selective PHP version testing functional

#### Phase 6 Completion Criteria  
- **AC6.1**: GNU parallel successfully manages all test jobs
- **AC6.2**: Total execution time reduced to 1 minute or less
- **AC6.3**: Resource utilization optimized (CPU, memory, I/O)
- **AC6.4**: Job distribution balanced across available cores

#### Phase 7 Completion Criteria
- **AC7.1**: Container pool pre-creation and reuse functional  
- **AC7.2**: Total execution time reduced to 45 seconds or less
- **AC7.3**: Container cleanup and resource management automated
- **AC7.4**: Pool sizing optimized for typical development machine resources

#### Phase 8 Completion Criteria
- **AC8.1**: Unified test result collection across all parallel streams
- **AC8.2**: Clear success/failure reporting with detailed breakdown
- **AC8.3**: Execution time maintained at 45 seconds with enhanced reporting
- **AC8.4**: Error identification and debugging information readily accessible

### Overall Success Criteria
- **OSC1**: 10x+ performance improvement (10+ minutes → under 1 minute for core cases)
- **OSC2**: 100% test result parity with sequential execution
- **OSC3**: Full PHP version matrix support (6 versions)  
- **OSC4**: Maintained CI compatibility and package testing integrity
- **OSC5**: Single script maintainability preserved throughout evolution
- **OSC6**: Zero-configuration developer experience maintained
- **OSC7**: Rollback capability available at each phase boundary
- **OSC8**: Resource utilization optimized for typical development environments

## Risk Assessment

### Technical Risks
- **TR1**: Database connection conflicts in parallel execution
- **TR2**: Docker resource exhaustion on limited development machines  
- **TR3**: WordPress test framework download failures in parallel
- **TR4**: File system conflicts when multiple containers access same package

### Mitigation Strategies
- **MS1**: Implement dedicated database instances per test stream
- **MS2**: Add resource monitoring and dynamic scaling based on available capacity
- **MS3**: Implement retry logic and caching for WordPress framework downloads  
- **MS4**: Use Docker volume mounts in read-only mode for package sharing

### Rollback Plan
Each phase maintains rollback capability by:
1. Preserving previous script version in git history
2. Testing rollback procedure before proceeding to next phase
3. Documenting rollback steps in task breakdown
4. Maintaining configuration flags to enable/disable optimizations