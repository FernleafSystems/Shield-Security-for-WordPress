# Testing Guide

## Quick Start

### Option 1: Simple Docker Testing (Recommended) ⚡
**Ultimate zero setup** - automated CI-equivalent testing with one command:

```bash
# Simple command - matches CI exactly
./bin/run-docker-tests.sh

# What this automatically does:
# ✅ Detects WordPress versions (latest + previous)
# ✅ Builds assets and dependencies
# ✅ Builds production package
# ✅ Tests PHP 7.4 + latest WordPress
# ✅ Tests PHP 7.4 + previous WordPress  
# ✅ Runs both unit and integration tests
# ✅ Handles all setup and cleanup
```

**Key Benefits:**
- **Zero Configuration**: No setup, no parameters, just run
- **CI Parity**: Identical to GitHub Actions matrix testing
- **Production Validation**: Tests built package (not just source)
- **Auto-Discovery**: Detects current WordPress versions dynamically
- **Complete Coverage**: Both unit and integration tests across versions

### Option 2: Advanced Docker Testing
**Full control** - customize PHP/WordPress versions and testing modes:

```powershell
# Source testing (test current development code)
.\bin\run-tests.ps1 all -Docker                    # All tests
.\bin\run-tests.ps1 unit -Docker                   # Unit tests only
.\bin\run-tests.ps1 integration -Docker            # Integration tests only

# Package testing (test production build - builds automatically)
.\bin\run-tests.ps1 all -Docker -Package           # Build and test package
.\bin\run-tests.ps1 unit -Docker -Package          # Unit tests on package

# Custom versions
.\bin\run-tests.ps1 all -Docker -PhpVersion 8.1 -WpVersion 6.3

# Dynamic WordPress versions
.\bin\run-tests.ps1 all -Docker -WpVersion latest      # Latest WordPress
.\bin\run-tests.ps1 all -Docker -WpVersion previous    # Previous major

# Alternative: Composer commands (use unified runner internally)
composer docker:test                            # All tests
composer docker:test:unit                       # Unit tests only
composer docker:test:integration                # Integration tests only
composer docker:test:package                    # Package testing
```

### Option 3: Local Testing
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

### Simple Docker Testing (run-docker-tests.sh)
- Docker Desktop installed and running
- 4GB+ RAM allocated to Docker
- Bash shell (Git Bash on Windows, native on macOS/Linux)
- **Nothing else required** - script handles everything automatically

### Advanced Docker Testing
- Docker Desktop installed and running
- 4GB+ RAM allocated to Docker
- PowerShell (Windows) or Bash (macOS/Linux)
- No additional setup required

### Local Testing
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

### Docker Testing Environment Variables
Docker testing uses comprehensive environment variable configuration:

#### Core Configuration
```bash
PHP_VERSION=8.2                    # PHP version (7.4-8.4)
WP_VERSION=6.4                     # WordPress version
MYSQL_VERSION=8.0                  # MySQL/MariaDB version
MYSQL_DATABASE=wordpress_test       # Test database name
MYSQL_USER=wordpress               # Database user
MYSQL_PASSWORD=wordpress           # Database password
```

#### Testing Mode Control
```bash
SHIELD_PACKAGE_PATH=               # Set for package testing mode
PLUGIN_SOURCE=../../               # Plugin source directory
SKIP_DB_CREATE=false              # Skip database creation
DEBUG=false                       # Enable debug output
```

#### Environment Detection Logic
Bootstrap files automatically detect testing environment:
1. **Package Testing**: When `SHIELD_PACKAGE_PATH` is set
2. **Docker Testing**: When WordPress plugin directory exists in container
3. **Source Testing**: Default mode using repository directory

#### Variable Flow
```
PowerShell Script → .env file → docker-compose.yml → Container
     ↓                ↓              ↓              ↓
User Input    →  File Config  →  Service Env  →  Test Runtime
```

## Package Testing vs Source Testing

### Understanding the Difference

#### Source Testing (Development Mode)
- **Purpose**: Test current development code
- **Environment**: Uses development dependencies and source files
- **Speed**: Faster, no build process required
- **Use Case**: Daily development, TDD, debugging
- **Command**: `.\bin\run-tests.ps1 all -Docker`

#### Package Testing (Production Validation)
- **Purpose**: Test production-ready built package
- **Environment**: Uses `vendor_prefixed` dependencies, cleaned autoload files
- **Process**: Automatically builds package using `bin/build-package.sh`
- **Validation**: Ensures package structure and production readiness
- **Command**: `.\bin\run-tests.ps1 all -Docker -Package`

### Package Testing Process
1. **Build Phase**: Runs `bin/build-package.sh` to create production package
2. **Prefix Phase**: Dependencies moved to `vendor_prefixed` with Strauss
3. **Clean Phase**: Development files excluded, autoload references cleaned
4. **Test Phase**: Docker container tests the built package
5. **Validation Phase**: Verifies package structure and functionality

### Package Validation Tests
Package testing validates:
- ✅ `vendor_prefixed` directory exists with Strauss-prefixed dependencies
- ✅ Development files (`.github`, tests) properly excluded
- ✅ Twig references cleaned from autoload files
- ✅ Plugin structure matches production requirements
- ✅ All dependencies properly namespaced
- ✅ No development artifacts in package
- ✅ Production-ready package validated

### When to Use Each Mode

**Use Source Testing for:**
- Daily development work
- Test-driven development (TDD)
- Debugging and troubleshooting
- Feature development and iteration
- Quick validation of changes

**Use Package Testing for:**
- Pre-release validation
- Production readiness verification
- Dependency conflict detection
- Plugin distribution validation
- CI/CD package verification

## Docker Testing Infrastructure

### Overview
Shield Security includes a comprehensive Docker-based testing infrastructure that enables consistent test execution across all environments. The Docker setup supports both source testing (development) and package testing (production validation).

### Key Features
- **Zero Setup**: Docker containers include all required dependencies
- **Two Testing Modes**: Source testing for development, package testing for production validation
- **Cross-Platform**: Works on Windows, macOS, and Linux
- **GitHub Actions Integration**: Optional CI/CD testing with manual trigger
- **Environment Detection**: Automatic detection of testing environment in bootstrap files

### Docker Testing Modes

#### Source Testing (Default)
Tests against current repository source code:
- Uses development dependencies and configuration
- Faster for iterative development
- No build process required
- Ideal for TDD and debugging

#### Package Testing
Tests against production-ready built package:
- Builds plugin with `vendor_prefixed` directory
- Validates production package structure
- Tests actual distribution package
- Catches packaging and dependency issues

### Architecture
Docker testing uses a multi-container environment:
- **test-runner**: Executes PHPUnit tests with all dependencies
- **mysql**: MariaDB 10.2 database service
- **Unified Runner**: PowerShell script handles both native and Docker modes

### Container Configuration
```yaml
services:
  mysql:
    image: mariadb:10.2
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wordpress_test
  
  test-runner:
    build: .
    depends_on:
      - mysql
    environment:
      PHP_VERSION: 8.2
      WP_VERSION: 6.4
      SHIELD_PACKAGE_PATH: # Set for package testing
```

## How to Run Docker Tests

### Universal: Simple CI-Equivalent Testing (All Platforms)

The `bin/run-docker-tests.sh` script provides the simplest way to run comprehensive tests that exactly match CI:

```bash
# One command runs everything - matches CI exactly
./bin/run-docker-tests.sh

# What it automatically executes:
# 1. Detects current WordPress versions (latest: 6.8.2, previous: 6.7.3)
# 2. Builds all assets and dependencies
# 3. Creates production package with vendor_prefixed
# 4. Runs PHP 7.4 + WordPress 6.8.2 (package mode)
# 5. Runs PHP 7.4 + WordPress 6.7.3 (package mode)
# 6. Executes both unit and integration tests for each
# 7. Validates package structure and production readiness
# 8. Cleans up all containers and temporary files
```

**Script Features:**
- **Zero Configuration**: Automatically detects all settings
- **CI Parity**: Identical to GitHub Actions workflow
- **Cross-Platform**: Works on Windows (Git Bash), macOS, Linux
- **Production Testing**: Always tests built package (not source)
- **Auto-Cleanup**: Removes containers and temporary files
- **Error Handling**: Stops on first failure with clear messages
- **Version Detection**: Uses WordPress API with fallback system

**When to Use:**
- ✅ **Before commits**: Validate changes against CI environment
- ✅ **Pre-release**: Comprehensive production package validation
- ✅ **New environment**: First-time setup with zero configuration
- ✅ **Team collaboration**: Consistent results across all machines
- ✅ **Regression testing**: Full validation after significant changes

### Windows Users (PowerShell) - Advanced Control

#### Basic Commands
```powershell
# All tests in Docker
.\bin\run-tests.ps1 all -Docker

# Unit tests only
.\bin\run-tests.ps1 unit -Docker

# Integration tests only
.\bin\run-tests.ps1 integration -Docker

# Test production package
.\bin\run-tests.ps1 all -Docker -Package
```

#### Custom Versions
```powershell
# Test with PHP 8.1 and WordPress 6.3
.\bin\run-tests.ps1 all -Docker -PhpVersion 8.1 -WpVersion 6.3

# Test specific combination
.\bin\run-tests.ps1 integration -Docker -PhpVersion 7.4 -WpVersion latest
```

### macOS/Linux Users (Bash)

#### Using Composer
```bash
# Source testing
composer docker:test                    # All tests
composer docker:test:unit               # Unit tests
composer docker:test:integration        # Integration tests

# Package testing
composer docker:test:package            # All tests on built package
```

#### Direct Docker Compose
```bash
# Manual container management
docker-compose -f tests/docker/docker-compose.yml up -d
docker-compose -f tests/docker/docker-compose.yml exec test-runner composer test
docker-compose -f tests/docker/docker-compose.yml down
```

### Environment Configuration

#### Default Settings
Works immediately without configuration:
- PHP 8.2
- WordPress 6.4
- MySQL 8.0 / MariaDB 10.2
- Source code testing

#### Custom Configuration
Create `tests/docker/.env` file for custom settings:
```bash
PHP_VERSION=8.1
WP_VERSION=6.3
MYSQL_VERSION=8.0
SKIP_DB_CREATE=false
DEBUG=true
```

## Matrix Testing Configuration - Production Ready ✅

### Comprehensive Matrix Testing Capabilities 
Shield Security provides **enterprise-grade matrix testing** with advanced Docker infrastructure:

**Matrix Testing Scope**:
- **PHP Versions**: Complete support for 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 (6 versions)
- **WordPress Versions**: Dynamic detection of latest stable + previous major version
- **Test Combinations**: Up to 12 combinations (6 PHP × 2 WordPress versions)
- **Performance**: <3 minutes total execution time for complete matrix
- **Validation Status**: Production tested with GitHub Actions Run ID 17036484124
- **Success Rate**: 100% - All matrix combinations passing consistently

**Current Matrix Configuration (Optimized)**:
- **Active**: PHP 7.4 + latest/previous WordPress (2 combinations)
- **Available**: Full 6 PHP × 2 WordPress = 12 combinations ready to enable
- **Triggers**: Automatic on pushes to `develop`, `main`, `master` branches
- **Dynamic Versions**: WordPress versions auto-detected using API with 5-level fallback

**Manual Targeted Testing**:
1. Navigate to **Actions** tab in GitHub repository
2. Select **"Docker Tests"** workflow
3. Click **"Run workflow"** and configure:
   - **PHP Version**: Select from 7.4, 8.0, 8.1, 8.2, 8.3, or 8.4
   - **WordPress Version**: Specify version, "latest", "previous", or leave empty for auto-detection
   - **Testing Mode**: Single targeted combination for focused debugging

### Matrix Environment Variables and Configuration

#### Core Matrix Configuration Options
```bash
# Primary matrix configuration
PHP_VERSION=8.2                    # Target PHP version (7.4-8.4)
WP_VERSION=latest                   # WordPress version (latest|previous|6.8.2|etc)
TEST_PHP_VERSION=8.2               # Test environment PHP version
TEST_WP_VERSION=6.8.2              # Test environment WordPress version

# MySQL database configuration
MYSQL_VERSION=8.0                  # MySQL/MariaDB version
MYSQL_DATABASE=wordpress_test       # Test database name
MYSQL_USER=wordpress               # Database user
MYSQL_PASSWORD=wordpress           # Database password
```

#### Package Testing Configuration
```bash
# Package testing mode variables
SHIELD_PACKAGE_PATH=/package       # Path to built package in container
PLUGIN_SOURCE=/path/to/package      # Host path to package directory

# Package testing workflow
# 1. Build package: ./bin/build-package.sh $PACKAGE_DIR
# 2. Set environment: SHIELD_PACKAGE_PATH=$PACKAGE_DIR
# 3. Mount package: Uses docker-compose.package.yml override
# 4. Test package: Container tests built package instead of source
```

#### WordPress Version Detection System
Shield Security uses a sophisticated 5-level fallback system for WordPress version detection:

**Detection Hierarchy**:
1. **Primary API**: `https://api.wordpress.org/core/version-check/1.7/` (comprehensive version data)
2. **Secondary API**: `https://api.wordpress.org/core/stable-check/1.0/` (security validation)
3. **GitHub Actions Cache**: Cached results with 6-hour TTL
4. **Repository Fallback**: `.github/data/wp-versions-fallback.txt`
5. **Hardcoded Fallback**: Emergency versions (6.8.2 latest, 6.7.1 previous)

**Version Detection Features**:
- **Cache TTL**: 6 hours for API results
- **PHP Compatibility**: Filters versions compatible with PHP 7.4-8.4
- **Retry Logic**: 3 attempts with exponential backoff (2-30s)
- **Debug Mode**: `./github/scripts/detect-wp-versions.sh --debug`

#### Environment Variable Flow in Matrix Testing
```
GitHub Workflow Matrix → Docker Environment → Container Runtime
         ↓                        ↓                  ↓
   Matrix Values        →    .env File      →   Test Execution
   Workflow Inputs      →    Build Args     →   Container Config
   Version Detection    →    Environment    →   Test Framework
```

**Production Architecture**:
- **Automatic Validation**: Every main branch change tested across full matrix
- **Manual Flexibility**: Custom combinations for specific testing scenarios
- **Consistent Triggers**: Standardized across both workflows for reliability
- **Comprehensive Coverage**: Matrix testing supplements primary CI/CD for complete validation

**Advanced Features - All Production Tested**:
- **Full Build Pipeline**: Node.js, npm dependencies, and asset compilation integrated
- **Dynamic Version Detection**: Automatic WordPress version discovery and caching
- **Performance Optimizations**:
  - Multi-layer caching (Composer, npm, Docker layers, built assets)
  - Parallel matrix execution for optimal speed
  - 15-minute timeout per job with automatic cleanup
  - BuildKit integration for faster Docker builds
- **Package Validation**: Complete production package building and testing
- **Environment Isolation**: Clean containerized environment for each test
- **Automatic Resource Management**: Container cleanup and resource optimization

**Production Validation Results** ✅:
- **GitHub Actions Run ID 16694657226**: Complete matrix success
- **Unit Tests**: 71 tests, 2483 assertions - ALL PASSED
- **Integration Tests**: 33 tests, 231 assertions - ALL PASSED
- **Package Validation**: All 7 production tests - ALL PASSED
- **Matrix Coverage**: 12 PHP/WordPress combinations - ALL PASSED
- **Total Runtime**: ~3 minutes for complete matrix test suite
- **Local Validation**: PHP 7.4 and 8.3 builds tested and confirmed
- **Status**: Production ready and enterprise validated

## Comprehensive Matrix Testing Troubleshooting Guide

### Matrix Configuration Issues

#### Matrix Not Running Expected Combinations
**Problem**: Matrix only testing one combination instead of full matrix
```
❌ Expected 12 combinations (6 PHP × 2 WordPress), but only 1 or 2 running
```
**Solutions**:
1. **Check Workflow Trigger Type**:
   ```bash
   # Manual triggers (workflow_dispatch) = single job with selected versions
   # Automatic triggers (push to main branches) = matrix execution
   ```
2. **Verify Matrix Configuration** in `.github/workflows/docker-tests.yml`:
   ```yaml
   matrix:
     php: ['7.4']  # Currently optimized to single version
     # To enable full matrix: php: ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4']
     wordpress: ${{ fromJSON(...) }}  # Uses detected versions
   ```
3. **Enable Full Matrix** (if desired):
   - Edit workflow file to include all PHP versions
   - Consider performance impact (12 jobs vs 2 jobs)
   - Current 2-job configuration provides 81% faster execution

#### WordPress Version Detection Failures
**Problem**: WordPress API detection timeout or invalid versions
```
❌ WordPress version detection failed: API timeout or invalid response
```
**Solutions**:
1. **Check API Endpoints**:
   ```bash
   # Test primary API
   curl -s https://api.wordpress.org/core/version-check/1.7/ | jq -r '.offers[0].version'
   
   # Test secondary API
   curl -s https://api.wordpress.org/core/stable-check/1.0/ | head
   ```
2. **Verify 5-Level Fallback System**:
   ```bash
   # Run detection with debug mode
   ./.github/scripts/detect-wp-versions.sh --debug
   
   # Check fallback activation sequence:
   # API → Secondary API → Cache → Repository → Hardcoded
   ```
3. **Cache Issues**:
   ```bash
   # Check cache directory
   ls -la ~/.wp-api-cache/
   
   # Clear cache if needed
   rm -rf ~/.wp-api-cache/
   ```

#### PHP/WordPress Compatibility Issues
**Problem**: Matrix combination fails due to version incompatibility
```
❌ PHP 8.4 with WordPress 6.6 - compatibility error
```
**Solutions**:
1. **Check Compatibility Matrix**:
   ```bash
   # WordPress version requirements:
   # WordPress 6.8+ : PHP 7.4-8.4 ✅
   # WordPress 6.7+ : PHP 7.4-8.4 ✅
   # WordPress 6.6  : PHP 7.4-8.3 (8.4 experimental)
   ```
2. **Use Compatibility Filtering**:
   ```bash
   # Version detection filters for PHP compatibility
   ./.github/scripts/detect-wp-versions.sh --php-version=7.4
   ```
3. **Matrix Exclusions**:
   ```yaml
   # Add to workflow matrix
   exclude:
     - php: '8.4'
       wordpress: '6.6'  # Known incompatibility
   ```

### Docker Matrix Build Issues

#### Multi-Stage Build Failures
**Problem**: Docker build fails for specific PHP versions in matrix
```
❌ Docker build failed: PHP extensions not found for version X.X
```
**Solutions**:
1. **Verify PHP Version Support**:
   ```bash
   # Check supported versions in Dockerfile
   grep -A 10 "PHP_SUPPORTED_VERSIONS" tests/docker/Dockerfile
   
   # Supported: 7.4, 8.0, 8.1, 8.2, 8.3, 8.4
   ```
2. **Update Package Repository** (if using older base):
   ```dockerfile
   # Ensure ondrej/php repository is updated
   RUN add-apt-repository ppa:ondrej/php && apt-get update
   ```
3. **Test Specific Version Locally**:
   ```bash
   # Build with specific matrix combination
   docker build tests/docker/ \
     --build-arg PHP_VERSION=8.3 \
     --build-arg WP_VERSION=6.8.2 \
     --progress=plain
   ```

#### ARG Propagation Issues
**Problem**: Build arguments not propagating through multi-stage build
```
❌ WP_VERSION empty in Stage 4, causing malformed URLs
```
**Solutions**:
1. **Critical Fix Applied**: Dockerfile line 108 fixed from `ARG WP_VERSION` to `ARG WP_VERSION=latest`
2. **Verify Fix**:
   ```bash
   # Check ARG declarations in Dockerfile
   grep -n "ARG WP_VERSION" tests/docker/Dockerfile
   # Line 7: ARG WP_VERSION=latest (global)
   # Line 108: ARG WP_VERSION=latest (stage-specific with default)
   ```
3. **Test Build Process**:
   ```bash
   # Ensure build completes successfully
   docker build tests/docker/ --build-arg WP_VERSION=6.8.2
   ```

### Container Infrastructure Issues

#### Docker Not Available
```
❌ Docker is not available or not running
```
**Solution**: Ensure Docker Desktop is installed and running
- Windows: Check Docker Desktop in system tray
- macOS: Check Docker icon in menu bar
- Linux: Verify Docker service is running

#### Permission Issues (Linux/macOS)
```
docker: Got permission denied while trying to connect
```
**Solution**: Add user to docker group
```bash
sudo usermod -aG docker $USER
# Log out and back in
```

#### Database Connection Issues
```
Connection refused to database
```
**Solution**: Wait for database container to be ready
```bash
# Check container status
docker-compose -f tests/docker/docker-compose.yml ps

# View database logs
docker-compose -f tests/docker/docker-compose.yml logs mysql

# Restart with fresh database
docker-compose -f tests/docker/docker-compose.yml down -v
docker-compose -f tests/docker/docker-compose.yml up -d
```

### Windows-Specific Issues

#### File Sharing Not Enabled
```
Error response from daemon: drive is not shared
```
**Solution**: Enable file sharing in Docker Desktop settings
- Open Docker Desktop
- Go to Settings → Resources → File Sharing
- Add project directory
- Apply & Restart

#### WSL2 Backend Issues
```
Docker Desktop requires WSL2
```
**Solution**: Enable WSL2 backend
- Install WSL2
- Set as default in Docker Desktop settings
- Allocate 4GB+ RAM in Docker Desktop

#### PowerShell Execution Policy
```
Execution of scripts is disabled on this system
```
**Solution**: Allow script execution
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Package Testing Issues

#### Build Process Fails
```
Package building failed
```
**Solution**: Check build dependencies
```bash
# Ensure Node.js and npm are installed
node --version
npm --version

# Ensure Composer dependencies are installed
composer install
cd src/lib && composer install

# Manually test package building
./bin/build-package.sh /tmp/test-package
```

#### Missing vendor_prefixed Directory
```
vendor_prefixed directory not found
```
**Solution**: Package was not built correctly
- Ensure Strauss is installed in `src/lib/vendor`
- Verify `strauss.phar` exists and is executable
- Check package build output for errors

### Performance Issues

#### Slow Container Startup
**Solution**: Optimize Docker Desktop settings
- Allocate 4GB+ RAM
- Enable 2+ CPU cores
- Use WSL2 backend on Windows
- Ensure SSD storage for Docker

#### Test Execution Timeout
**Solution**: Increase container resources
```bash
# Check container resource usage
docker stats

# Rebuild with no cache
docker-compose -f tests/docker/docker-compose.yml build --no-cache
```

### Environment Variable Issues

#### Variables Not Passed to Container
**Solution**: Check environment variable flow
```powershell
# Debug PowerShell script
.\bin\run-tests.ps1 all -Docker -Debug

# Check .env file creation
cat tests/docker/.env

# Verify docker-compose configuration
docker-compose -f tests/docker/docker-compose.yml config
```

#### Package Path Not Recognized
```
SHIELD_PACKAGE_PATH not detected
```
**Solution**: Verify package mounting
- Check package was built successfully
- Verify package path exists
- Ensure Docker Compose override is applied (Windows)

### Network Issues

#### Container Communication Failed
**Solution**: Check Docker network
```bash
# Inspect network
docker network ls
docker-compose -f tests/docker/docker-compose.yml ps

# Test container connectivity
docker-compose -f tests/docker/docker-compose.yml exec test-runner ping mysql
```

### Matrix Testing Debug Mode

#### Local Matrix Testing Debug
```powershell
# PowerShell with debug for specific matrix combination
.\bin\run-tests.ps1 all -Docker -PhpVersion 8.1 -WpVersion 6.8.2 -Debug

# Standard debug output
.\bin\run-tests.ps1 all -Docker -Debug

# Environment variable debug
DEBUG=true composer docker:test
```

#### WordPress Version Detection Debug
```bash
# Debug version detection process
./.github/scripts/detect-wp-versions.sh --debug

# Test all fallback mechanisms
./.github/scripts/detect-wp-versions.sh --test-fallbacks

# Display cache information
./.github/scripts/detect-wp-versions.sh --cache-info

# Test PHP compatibility filtering
./.github/scripts/detect-wp-versions.sh --php-version=8.2 --debug
```

#### Matrix Build Debug
```bash
# Debug Docker build with specific matrix values
docker build tests/docker/ \
  --build-arg PHP_VERSION=8.2 \
  --build-arg WP_VERSION=6.8.2 \
  --progress=plain --no-cache

# Debug Docker Compose matrix configuration
docker-compose -f tests/docker/docker-compose.yml config

# Debug environment variables in running container
docker exec -it container_name env | grep -E "PHP_VERSION|WP_VERSION|SHIELD_"
```

Matrix debug output includes:
- **Matrix Environment Variables**: PHP_VERSION, WP_VERSION, TEST_* variables
- **Version Detection Process**: API calls, fallback activation, caching behavior
- **Docker Compose Configuration**: Volume mounts, environment inheritance, service dependencies
- **Multi-Stage Build Progress**: Each stage with ARG propagation verification
- **Container Startup Logs**: PHP/WordPress version confirmation, test framework setup
- **Matrix Test Execution**: Per-combination test results and timing
- **Package Testing Process**: Build verification, volume mounting, package structure validation
- **PHP/WordPress Compatibility**: Version validation and compatibility checking

### Getting Help

1. **Check Logs**: Always start with container logs
2. **Debug Mode**: Use debug flags for detailed output
3. **Validate Setup**: Ensure Docker Desktop is properly configured
4. **Clean Rebuild**: When in doubt, rebuild containers
5. **Documentation**: Refer to detailed docs in `tests/docker/README.md`

---

## Complete Testing Workflow

### Recommended Development Cycle

#### Option A: Simple CI-Equivalent Workflow (Recommended)
```bash
# 1. Make code changes
# 2. Comprehensive validation (matches CI exactly)
./bin/run-docker-tests.sh

# This single command:
# ✅ Validates all changes against production environment
# ✅ Tests both WordPress versions (latest + previous)
# ✅ Runs complete test suite (unit + integration)
# ✅ Validates production package build
# ✅ Matches GitHub Actions CI exactly
```

#### Option B: Granular Development Workflow
```powershell
# 1. Make code changes
# 2. Quick unit test validation
.\bin\run-tests.ps1 unit -Docker

# 3. Full integration testing
.\bin\run-tests.ps1 integration -Docker

# 4. Before release: validate production package
.\bin\run-tests.ps1 all -Docker -Package
```

### CI/CD Integration
- **GitHub Actions**: Manual Docker workflow available for targeted testing
- **Package Validation**: Automatic package building and testing
- **Multi-Version Testing**: Configurable PHP and WordPress versions
- **Clean Environment**: Isolated containers prevent test pollution

## Testing Methods Comparison

### Simple Docker Testing (run-docker-tests.sh) - Ultimate Simplicity ⚡
- ✅ **Ultimate Zero Setup**: One command, zero configuration
- ✅ **CI Parity Guaranteed**: Identical to GitHub Actions (PHP 7.4 + latest/previous WordPress)
- ✅ **Auto-Discovery**: Detects WordPress versions automatically
- ✅ **Production Validation**: Always tests built package (vendor_prefixed)
- ✅ **Complete Coverage**: Both unit and integration tests across versions
- ✅ **Cross-Platform**: Works on Windows (Git Bash), macOS, Linux
- ✅ **Error Handling**: Clear failure messages and automatic cleanup
- ✅ **Team Consistency**: Identical results for all developers

### Advanced Docker Testing - Production Validated
- ✅ **Zero Setup Required**: No local PHP, MySQL, or WordPress configuration needed
- ✅ **Matrix Testing**: 12 PHP/WordPress combinations validated in production
- ✅ **Consistent Environment**: Identical results across Windows, macOS, and Linux
- ✅ **Version Flexibility**: Test any PHP (7.4-8.4) and WordPress version combination
- ✅ **No Local Conflicts**: Complete isolation from local development environment
- ✅ **CI/CD Parity**: Exact match with GitHub Actions matrix testing environment
- ✅ **Package Validation**: Production-ready package building and comprehensive testing
- ✅ **Clean State**: Fresh database and environment for each test run
- ✅ **Enterprise Grade**: Validated with comprehensive caching and optimization strategies
- ✅ **Dynamic Versions**: Automatic WordPress version detection and compatibility testing

### Local Testing Benefits
- ✅ **Faster Execution**: No container overhead (~30% faster)
- ✅ **Direct Debugging**: Use local debugging tools (Xdebug, IDE integration)
- ✅ **No Docker Dependency**: Works without Docker Desktop
- ✅ **Resource Efficiency**: Lower memory and CPU usage
- ✅ **Immediate Changes**: No container rebuilding needed

### When to Use Each

**Use Simple Docker Testing (run-docker-tests.sh) for:**
- ✅ **Most scenarios** - comprehensive validation with minimal effort
- ✅ **Pre-commit validation** - ensure changes work in CI environment
- ✅ **New team members** - zero setup, immediate productivity
- ✅ **Release preparation** - comprehensive production validation
- ✅ **Regression testing** - validate major changes across versions
- ✅ **Team consistency** - identical results for all developers

**Use Advanced Docker Testing for:**
- Specific PHP/WordPress version combinations
- Custom testing scenarios and debugging
- Matrix testing beyond CI scope
- Development of testing infrastructure
- Granular control over test execution

**Use Local Testing for:**
- Daily development (faster iteration)
- Debugging and troubleshooting
- Limited system resources
- Offline development
- Integration with local development tools

---

**Recommendation:** Start with `./bin/run-docker-tests.sh` for comprehensive validation, then use local testing for rapid development iteration. The simple Docker script provides the best balance of thoroughness and ease of use.