# Docker Matrix Testing Optimization

## Overview
This specification outlines the enhancement of Shield Security's Docker testing infrastructure to support matrix testing across multiple PHP versions (7.4, 8.0, 8.1, 8.2, 8.3, 8.4) and dynamically determined WordPress versions, with a focus on build optimization and performance.

## Context
Shield Security currently has a working Docker testing infrastructure that runs on push to main branches (develop, main, master). However, it currently:
- Tests against a single PHP version (8.2) and WordPress version (6.4)
- Does not dynamically determine the latest WordPress versions
- Does not leverage matrix testing for comprehensive compatibility validation
- May face performance challenges when scaling to matrix testing

## Problem Statement
1. **Outdated WordPress Testing**: Currently testing against WordPress 6.4, while the latest is 6.7.x
2. **Limited PHP Coverage**: Only testing default PHP version, not the full supported range
3. **Static Version Configuration**: WordPress versions are hardcoded, not dynamically determined
4. **Performance Concerns**: Matrix testing (6 PHP versions × 2 WordPress versions = 12 test runs) could significantly increase CI/CD time and costs
5. **Build Redundancy**: Docker images may be rebuilt unnecessarily for each matrix combination

## Goals
1. Implement matrix testing for PHP versions 7.4, 8.0, 8.1, 8.2, 8.3, and 8.4
2. Dynamically determine and test against:
   - The absolute latest WordPress version (e.g., 6.7.2)
   - The latest patch of the previous major version (e.g., 6.6.3)
3. Optimize Docker builds to minimize redundancy and execution time
4. Research and implement best practices from major WordPress plugins
5. Maintain backward compatibility with existing test infrastructure

## Non-Goals
1. Testing every minor/patch version of WordPress
2. Testing WordPress versions older than the previous major release
3. Implementing custom caching infrastructure beyond GitHub Actions capabilities
4. Modifying the existing test suite structure

## Research Requirements

### Phase 1: Industry Best Practices Analysis
Research how established WordPress plugins handle matrix testing:

#### 1.1 WooCommerce Analysis
- Repository: https://github.com/woocommerce/woocommerce
- Research focus:
  - Matrix testing implementation in `.github/workflows/`
  - Docker usage patterns (if any)
  - Build optimization strategies
  - WordPress version testing approach
  - Caching strategies for dependencies

#### 1.2 Yoast SEO Analysis
- Repository: https://github.com/Yoast/wordpress-seo
- Research focus:
  - PHP version matrix configuration
  - WordPress compatibility testing
  - Performance optimization techniques
  - Dependency caching patterns

#### 1.3 Easy Digital Downloads Analysis
- Repository: https://github.com/awesomemotive/easy-digital-downloads
- Research focus:
  - Docker testing patterns (they use docker-compose-phpunit.yml)
  - Matrix testing approach
  - Build time optimization

### Phase 2: WordPress Version Detection Strategy

#### 2.1 Dynamic Version Discovery
- Research WordPress.org API for version information
- Design mechanism to determine:
  - Latest stable WordPress version
  - Latest patch of previous major version
- Consider fallback strategies if API is unavailable

#### 2.2 Version Matrix Definition
- Current requirement: Test latest two major versions (latest patches only)
- Example at time of writing:
  - WordPress 6.7.2 (latest)
  - WordPress 6.6.3 (previous major, latest patch)

## Technical Design

### Matrix Configuration Strategy

#### Option 1: GitHub Actions Native Matrix
```yaml
strategy:
  matrix:
    php: ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4']
    wordpress: ['latest', 'previous-major']
```

#### Option 2: Docker-Based Matrix
- Single Docker image with multiple PHP versions
- Environment variable-based PHP switching
- Pros: Faster builds, shared layers
- Cons: More complex Dockerfile

### Build Optimization Strategies

#### 1. Docker Layer Caching
- **Base Image Optimization**: Use multi-stage builds with shared base layers
- **Dependency Caching**: Cache Composer and npm dependencies in Docker layers
- **Registry Caching**: Push base images to GitHub Container Registry

#### 2. GitHub Actions Optimization
- **Parallel Execution**: Run matrix combinations in parallel
- **Conditional Testing**: Only run full matrix on main branches
- **Smart Caching**: 
  - Cache Docker layers between builds
  - Cache Composer dependencies
  - Cache npm dependencies
  - Cache built assets

#### 3. Test Execution Optimization
- **Test Splitting**: Divide tests across matrix jobs
- **Fail-Fast Strategy**: Stop remaining matrix jobs on first failure
- **Selective Testing**: Run minimal tests on PRs, full matrix on main branches

### WordPress Version Detection Implementation

#### API Integration
```bash
# Example: Get latest WordPress version
curl -s https://api.wordpress.org/core/version-check/1.7/ | jq -r '.offers[0].version'

# Get all versions and filter for previous major
curl -s https://api.wordpress.org/core/stable-check/1.0/ | jq -r 'to_entries | map(select(.value == "latest" or .value == "outdated")) | .[0:2]'
```

#### Workflow Integration
- Add version detection step before matrix execution
- Pass detected versions as matrix parameters
- Cache version information for workflow duration

## Implementation Plan

### Phase 1: Research and Analysis (Week 1)
1. **Task 1.1**: Analyze WooCommerce workflows
   - Study matrix testing implementation
   - Document optimization techniques
   - Extract applicable patterns

2. **Task 1.2**: Analyze Yoast SEO workflows
   - Review PHP/WordPress testing matrix
   - Document caching strategies
   - Identify performance optimizations

3. **Task 1.3**: Analyze Easy Digital Downloads
   - Study Docker testing patterns
   - Review matrix implementation
   - Document build optimizations

4. **Task 1.4**: Compile Best Practices Report
   - Synthesize findings from all three plugins
   - Identify common patterns
   - Recommend applicable strategies

### Phase 2: WordPress Version Detection (Week 1)
1. **Task 2.1**: Design Version Detection System
   - Research WordPress.org API endpoints
   - Design version parsing logic
   - Plan caching strategy

2. **Task 2.2**: Implement Version Detection
   - Create version detection script
   - Add error handling and fallbacks
   - Test with various scenarios

### Phase 3: Matrix Testing Implementation (Week 2)
1. **Task 3.1**: Update Docker Configuration
   - Modify Dockerfile for multi-PHP support (if needed)
   - Update docker-compose for matrix compatibility
   - Optimize base images

2. **Task 3.2**: Update GitHub Actions Workflows
   - Implement matrix strategy
   - Add version detection integration
   - Configure parallel execution

3. **Task 3.3**: Implement Caching Strategy
   - Set up Docker layer caching
   - Configure dependency caching
   - Implement build artifact caching

### Phase 4: Optimization and Testing (Week 2)
1. **Task 4.1**: Performance Testing
   - Measure baseline execution time
   - Test optimized configuration
   - Document performance improvements

2. **Task 4.2**: Reliability Testing
   - Test failure scenarios
   - Verify fail-fast behavior
   - Ensure consistent results

3. **Task 4.3**: Documentation
   - Update testing documentation
   - Document matrix configuration
   - Create troubleshooting guide

## Success Criteria

### Functional Requirements
- [ ] Matrix testing covers PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4
- [ ] WordPress versions dynamically determined
- [ ] Tests run against latest and previous major WordPress versions
- [ ] All existing tests pass in matrix configuration
- [ ] Backward compatibility maintained

### Performance Requirements
- [ ] Total matrix execution time < 15 minutes
- [ ] Individual matrix job < 5 minutes
- [ ] Build time reduced by >50% through caching
- [ ] Parallel execution utilized effectively

### Reliability Requirements
- [ ] Version detection has fallback mechanism
- [ ] Failed jobs don't block entire workflow
- [ ] Clear error reporting for failures
- [ ] Reproducible results across runs

## Risk Mitigation

### Risk 1: Excessive CI/CD Time
- **Mitigation**: Implement aggressive caching, parallel execution, and conditional matrix testing
- **Fallback**: Reduce matrix on PRs, full matrix on main branches only

### Risk 2: API Reliability
- **Mitigation**: Cache version information, implement fallbacks
- **Fallback**: Hardcoded version list updated quarterly

### Risk 3: Complexity Overhead
- **Mitigation**: Gradual implementation, thorough documentation
- **Fallback**: Maintain simple non-matrix option

## Monitoring and Maintenance

### Metrics to Track
- Matrix execution time
- Cache hit rates
- Failure frequency by matrix combination
- Cost impact on GitHub Actions

### Maintenance Tasks
- Quarterly review of PHP version support
- Monitor WordPress release cycle
- Update optimization strategies based on metrics
- Keep documentation current

## Appendix: Research Notes

### WordPress Version API
- Endpoint: `https://api.wordpress.org/core/version-check/1.7/`
- Returns current stable version and download links
- Can parse for major.minor.patch information

### GitHub Actions Best Practices
- Use `actions/cache@v3` for dependency caching
- Leverage `docker/setup-buildx-action` for advanced caching
- Consider `matrix.exclude` for incompatible combinations
- Use `continue-on-error` for experimental versions

### Docker Optimization Techniques
- Multi-stage builds to share common layers
- BuildKit cache mounts for package managers
- Minimal base images (alpine where possible)
- Layer ordering optimization (least-changing first)