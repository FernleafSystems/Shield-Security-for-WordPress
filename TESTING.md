# Shield Security WordPress Plugin - Testing Documentation

## Table of Contents

- [Quick Reference Card](#quick-reference-card)
- [Quick Start](#quick-start)
  - [Option 1: Simple CI-Equivalent Testing (Recommended)](#option-1-simple-ci-equivalent-testing-recommended-)
  - [Option 2: Advanced Docker Testing (Custom Control)](#option-2-advanced-docker-testing-custom-control)
  - [Option 3: Local Native Testing](#option-3-local-native-testing)
- [Testing Infrastructure Overview](#testing-infrastructure-overview)
  - [Why Docker?](#why-docker)
  - [Testing Philosophy](#testing-philosophy)
  - [Architecture Components](#architecture-components)
- [Prerequisites & Setup](#prerequisites--setup)
  - [Simple Docker Testing Requirements](#simple-docker-testing-requirements)
  - [Advanced Docker Testing Requirements](#advanced-docker-testing-requirements)
  - [Local Testing Requirements](#local-testing-requirements)
- [Docker Configuration by Environment](#docker-configuration-by-environment)
  - [Configuration Matrix](#configuration-matrix)
  - [Environment Variable Configuration](#environment-variable-configuration)
  - [Key Benefits of Separated Configurations](#key-benefits-of-separated-configurations)
  - [Usage Examples](#usage-examples)
  - [When to Use Each Environment](#when-to-use-each-environment)
  - [Environment Switching](#environment-switching)
- [Running Tests](#running-tests)
  - [Universal: Simple CI-Equivalent Testing](#universal-simple-ci-equivalent-testing)
  - [Windows: PowerShell Testing](#windows-powershell-testing)
  - [macOS/Linux/WSL: Bash Testing](#macoslinuxwsl-bash-testing)
  - [Local Native Testing](#local-native-testing)
- [Writing Tests](#writing-tests)
  - [Test Structure](#test-structure)
  - [Unit Tests](#unit-tests)
  - [Integration Tests](#integration-tests)
  - [Test Dependencies](#test-dependencies)
  - [Code Coverage](#code-coverage)
- [Advanced Topics](#advanced-topics)
  - [Matrix Testing Configuration](#matrix-testing-configuration)
  - [Package Testing vs Source Testing](#package-testing-vs-source-testing)
  - [Environment Variables](#environment-variables)
  - [CI/CD Integration](#cicd-integration)
- [Docker Technical Details](#docker-technical-details)
  - [Container Architecture](#container-architecture)
  - [Multi-Stage Build Process](#multi-stage-build-process)
  - [Environment Detection](#environment-detection)
  - [Docker Compose Files](#docker-compose-files)
  - [Manual Docker Commands](#manual-docker-commands)
- [Troubleshooting](#troubleshooting)
  - [Common Issues](#common-issues)
  - [Docker-Specific Issues](#docker-specific-issues)
  - [Matrix Testing Issues](#matrix-testing-issues)
  - [Windows-Specific Issues](#windows-specific-issues)
  - [Performance Issues](#performance-issues)
  - [Debug Mode](#debug-mode)
- [Appendices](#appendices)
  - [Testing Methods Comparison](#testing-methods-comparison)
  - [Complete Testing Workflow](#complete-testing-workflow)
  - [Maintenance](#maintenance)
  - [Additional Resources](#additional-resources)
  - [Related Documentation](#related-documentation)

---

## Quick Reference Card

### Most Common Commands

| Platform | Command | Time | Purpose |
|----------|---------|------|------|
| **All** 🚀 | `./bin/run-docker-tests.sh` | ~3m total | ✅ **Recommended** - CI-equivalent testing |
| **🪟 Windows** | `.\bin\run-tests.ps1 all -Docker` | ~4m | Full Docker test suite |
| **🐧 Linux/🔧 WSL** | `pwsh ./bin/run-tests.ps1 all -Docker` | ~4m | Full Docker test suite |
| **🍎 macOS** | `pwsh ./bin/run-tests.ps1 all -Docker` | ~4m | Full Docker test suite |
| **Local** | `composer test` | ~2m | ⚡ Fast local testing (requires setup) |

### Decision Tree: Which Testing Method Should I Use?

```
Need to test?
├─ Before commit/PR? → ./bin/run-docker-tests.sh ✅
├─ Daily development?
│  ├─ Have local setup? → composer test ⚡
│  └─ No setup? → ./bin/run-docker-tests.sh ✅
├─ Specific PHP/WP version? → run-tests.ps1 -Docker -PhpVersion X -WpVersion Y 🔧
├─ Debugging test failures? → Local testing with IDE 🔬
└─ Release validation? → ./bin/run-docker-tests.sh ✅
```

### Quick Troubleshooting Checklist

- [ ] **Docker running?** Check Docker Desktop is started
- [ ] **4GB+ RAM allocated?** Docker Desktop → Settings → Resources
- [ ] **Scripts executable?** `chmod +x ./bin/*.sh` (Linux/WSL/macOS)
- [ ] **Line endings correct?** `dos2unix ./bin/*.sh` (Linux/WSL)
- [ ] **In docker group?** `groups | grep docker` (Linux)
- [ ] **WSL2 enabled?** `wsl --set-default-version 2` (Windows)

### Essential Environment Variables

```bash
# Docker Testing
PHP_VERSION=7.4           # PHP version (7.4-8.4)
WP_VERSION=latest         # WordPress version or 'latest'/'previous'
SHIELD_PACKAGE_PATH=      # Set for package testing mode

# Local Testing
WP_TESTS_DB_NAME=wordpress_test
WP_TESTS_DB_USER=root
WP_TESTS_DB_PASSWORD=root
```

### Common File Paths

| Component | Path |
|-----------|------|
| **Test Runner** | `./bin/run-tests.ps1` (PowerShell) |
| **CI Script** | `./bin/run-docker-tests.sh` (Bash) |
| **Tests** | `tests/Unit/`, `tests/Integration/` |
| **Docker Config** | `tests/docker/` |
| **CI Workflow** | `.github/workflows/docker-tests.yml` |
| **Bootstrap** | `tests/bootstrap.php` |

### Jump to Common Tasks

- [Run CI-equivalent tests](#universal-simple-ci-equivalent-testing) → Fastest validation
- [Setup WSL2 for Docker](#wsl2-setup-for-docker) → Windows optimization
- [Fix permission issues](#permission-issues-linuxmacoswsl) → Linux/WSL troubleshooting
- [Test specific versions](#custom-versions) → Matrix testing
- [Debug failing tests](#debug-mode) → Troubleshooting
- [View test logs](#container-environment-debug) → Container debugging

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

---

## Quick Start

### Option 1: Simple CI-Equivalent Testing (Recommended) ⚡

**Ultimate zero setup** - automated parallel CI-equivalent testing with one command:

```bash
# Simple command - matches CI exactly with 40% faster execution
./bin/run-docker-tests.sh

# What this automatically does:
# ✅ Detects WordPress versions (latest + previous)
# ✅ Builds assets and dependencies
# ✅ Builds production package ONCE (Phase 1 optimization)
# ✅ Creates version-specific Docker images (shield-test-runner:php{VERSION}-wp{VERSION})
# ✅ Creates isolated MySQL containers (mysql-latest, mysql-previous)
# ✅ Tests PHP 7.4/8.0 + WordPress latest/previous SIMULTANEOUSLY
# ✅ Runs both unit and integration tests in parallel streams
# ✅ Handles all setup and cleanup with proper error aggregation
```

**Key Benefits:**
- **Zero Configuration**: No setup, no parameters, just run
- **40% Faster**: Parallel execution reduces time from 6m 25s to 3m 28s
- **CI Parity**: Identical to GitHub Actions matrix testing
- **Production Validation**: Tests built package (not just source)
- **Auto-Discovery**: Detects current WordPress versions dynamically
- **Complete Coverage**: Both unit and integration tests across versions
- **Database Isolation**: Separate MySQL containers prevent test interference
- **Smart Health Checks**: MySQL readiness monitoring replaces hardcoded delays (Phase 4)

### Option 2: Advanced Docker Testing (Custom Control)

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

### Option 3: Local Native Testing

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

# Direct PHPUnit (after setup)
vendor/bin/phpunit                             # All tests
vendor/bin/phpunit --testsuite=unit            # Unit tests only
vendor/bin/phpunit --testsuite=integration     # Integration tests only
```

**See also:** [Local Testing Requirements](#local-testing-requirements) for setup instructions

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

---

## Testing Infrastructure Overview

### Why Docker?

Shield Security includes a comprehensive Docker-based testing infrastructure that enables consistent test execution across all environments. Docker provides:

- **Zero Setup**: Containers include all required dependencies (PHP, MySQL, WordPress)
- **Consistency**: Identical test environment across Windows, macOS, and Linux
- **Isolation**: No conflicts with local development environment
- **Matrix Testing**: Test multiple PHP/WordPress version combinations
- **CI/CD Parity**: Exact match with GitHub Actions environment
- **Production Validation**: Test actual packaged plugin (vendor_prefixed)

### Testing Philosophy

Following WordPress plugin best practices (Yoast, EDD, WooCommerce), we use:

1. **Unified Test Runner**: Single `bin/run-tests.ps1` script handles both native and Docker testing
2. **Environment Detection**: Bootstrap files automatically detect testing context
3. **Two Testing Modes**: Source testing for development, package testing for production validation
4. **Matrix Support**: Comprehensive testing across PHP 7.4-8.4 and multiple WordPress versions
5. **Backward Compatibility**: 100% compatibility maintained while adding Docker support

### Architecture Components

```
Shield Security Testing
├── bin/
│   ├── run-tests.ps1           # Unified test runner (PowerShell)
│   ├── run-docker-tests.sh     # Simple CI-equivalent script
│   ├── package-plugin.php      # Package builder (via composer package-plugin)
│   └── install-wp-tests.sh     # WordPress test framework installer
├── tests/
│   ├── Unit/                   # Fast unit tests (Brain Monkey)
│   ├── Integration/            # WordPress integration tests
│   ├── fixtures/               # Test data and base classes
│   ├── bootstrap.php           # Main bootstrap file
│   └── docker/                 # Docker configuration
│       ├── Dockerfile          # Multi-stage test environment
│       ├── docker-compose.yml  # Base container configuration
│       ├── docker-compose.ci.yml  # CI-specific overrides for GitHub Actions
│       └── docker-compose.package.yml  # Package testing configuration
└── .github/
    ├── workflows/
    │   └── docker-tests.yml    # GitHub Actions matrix testing
    └── scripts/
        └── detect-wp-versions.sh  # WordPress version detection
```

**See also:** [Docker Compose Files](#docker-compose-files) for detailed configuration descriptions

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

---

## Prerequisites & Setup

### Simple Docker Testing Requirements

For `bin/run-docker-tests.sh`:
- Docker Desktop installed and running
- 4GB+ RAM allocated to Docker
- Bash shell (Git Bash on Windows, native on macOS/Linux/WSL)
- **Nothing else required** - script handles everything automatically

#### WSL2 Setup for Docker

**Initial WSL2 Installation:**
```powershell
# Windows PowerShell (as Administrator)
wsl --install
# Or update existing WSL1 to WSL2
wsl --set-version Ubuntu 2
wsl --set-default-version 2
```

**Docker Desktop Configuration for WSL2:**
1. Install Docker Desktop for Windows
2. Settings → General → Enable "Use the WSL 2 based engine"
3. Settings → Resources → WSL Integration → Enable integration with your distro
4. Settings → Resources → Advanced → Allocate 4GB+ RAM

**Path Considerations in WSL:**
```bash
# WSL paths automatically mount Windows drives
# Windows: D:\Work\Dev\Repos
# WSL:     /mnt/d/Work/Dev/Repos

# For better performance, clone repos in WSL filesystem
cd ~/projects  # Native Linux filesystem (faster)
git clone https://github.com/your-repo.git

# Or work with Windows filesystem (slower but convenient)
cd /mnt/d/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield
```

**WSL Performance Tips:**
- Keep project files in WSL filesystem (`~/projects`) for 10x faster I/O
- Use Windows filesystem (`/mnt/d`) only when sharing with Windows tools
- Configure Git line endings: `git config --global core.autocrlf input`
- Install Docker CLI in WSL: `sudo apt update && sudo apt install docker.io`

#### Linux Native Setup

**Docker Installation on Linux:**
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install docker.io docker-compose
sudo systemctl start docker
sudo systemctl enable docker

# Add user to docker group (logout/login required)
sudo usermod -aG docker $USER
newgrp docker

# Verify installation
docker --version
docker-compose --version
```

**Docker Permissions on Linux:**
```bash
# Fix socket permissions if needed
sudo chmod 666 /var/run/docker.sock

# Or run with sudo (not recommended)
sudo docker-compose up

# Better: ensure user is in docker group
groups $USER  # Should show 'docker' in the list
```

### Advanced Docker Testing Requirements

For PowerShell/Composer Docker testing:
- Docker Desktop installed and running
- 4GB+ RAM allocated to Docker
- PowerShell (Windows) or PowerShell Core (Linux/macOS) or Bash
- No PHP, MySQL, or WordPress installation required

#### PowerShell Core on Linux/macOS

**Installing PowerShell Core:**
```bash
# Ubuntu/Debian
wget -q https://packages.microsoft.com/config/ubuntu/$(lsb_release -rs)/packages-microsoft-prod.deb
sudo dpkg -i packages-microsoft-prod.deb
sudo apt update
sudo apt install powershell

# Verify PowerShell Core installation
pwsh --version
# Expected output: PowerShell 7.x.x

# Test PowerShell Core is working
pwsh -Command "Write-Host 'PowerShell Core is working'"

# macOS
brew install --cask powershell

# Verify installation on macOS
pwsh --version

# Troubleshooting if PowerShell Core doesn't install:
# Ubuntu/Debian: Ensure packages-microsoft-prod.deb installed correctly
sudo apt-cache policy powershell
# macOS: Ensure Homebrew is up to date
brew update && brew upgrade powershell
```

**Running PowerShell Scripts on Linux/macOS:**
```bash
# Using PowerShell Core
pwsh ./bin/run-tests.ps1 all -Docker

# Or make scripts executable
chmod +x ./bin/run-tests.ps1
pwsh ./bin/run-tests.ps1 all -Docker
```

### Local Testing Requirements

For native testing without Docker:
- PHP 7.4+ (8.3+ recommended for development)
- Composer
- MySQL/MariaDB
- SVN (for downloading WordPress test framework)

**PHP Installation on Linux:**
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php8.3 php8.3-{mysql,xml,mbstring,curl,zip,gd,intl}
sudo apt install composer subversion

# Verify installation
php -v
composer --version
```

**MySQL Installation on Linux:**
```bash
# Ubuntu/Debian
sudo apt install mysql-server
sudo mysql_secure_installation

# Create test database and user
sudo mysql -e "CREATE DATABASE wordpress_test;"
sudo mysql -e "CREATE USER 'wp_test'@'localhost' IDENTIFIED BY 'password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wp_test'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

**Initial Setup for Local Testing:**

**See also:** [Local Native Testing](#local-native-testing) for running tests after setup

```bash
# Install dependencies
composer install
cd src/lib && composer install

# Setup WordPress test framework (one-time)
# Windows PowerShell:
.\bin\install-wp-tests.ps1 -DB_NAME wordpress_test -DB_USER root -DB_PASS your_password

# Linux/macOS/WSL:
bash bin/install-wp-tests.sh wordpress_test root 'your_password' localhost latest

# File permissions on Linux (if needed)
chmod +x bin/*.sh
chmod +x bin/*.ps1
```

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

---

## Docker Configuration by Environment

This section documents the key differences between local and CI testing configurations to help developers understand when to use each approach.

### Configuration Matrix

| Aspect | Local Testing Environment | CI Testing Environment (GitHub Actions) |
|--------|---------------------------|------------------------------------------|
| **Compose Files** | `docker-compose.yml` + `docker-compose.package.yml` (2 files) | `docker-compose.yml` + `docker-compose.ci.yml` + `docker-compose.package.yml` (3 files) |
| **Images Built** | Version-specific (e.g., `shield-test-runner:php7.4-wp6.9`) | Version-specific via matrix (e.g., `shield-test-runner:php7.4-wp6.9`) |
| **MySQL Containers** | `mysql-latest` (port 3309), `mysql-previous` (port 3310) with health checks | `mysql-latest`, `mysql-previous` (workflow-managed) |
| **MySQL Readiness** | Health check monitoring (~38s typical startup) | Health check monitoring |
| **Execution** | Parallel with isolated databases | Matrix-based with GitHub Actions orchestration |
| **Performance** | ~3 minutes total | Varies by CI runner capacity |
| **Script/Workflow** | `./bin/run-docker-tests.sh` (no CI compose file) | `.github/workflows/docker-tests.yml` |

### Environment Variable Configuration

The following environment variables can be used to override Docker image defaults:

| Variable | Purpose | Local Behavior | CI Behavior |
|----------|---------|----------------|-------------|
| `SHIELD_TEST_IMAGE` | Test runner image name | Uses built `shield-test-runner:php{PHP}-wp{WP}` | Set to built image tag |
| `SHIELD_TEST_IMAGE_LATEST` | Latest WordPress test image | Uses built `shield-test-runner:php{PHP}-wp{WP}` | Set to built image tag |
| `SHIELD_TEST_IMAGE_PREVIOUS` | Previous WordPress test image | Uses built `shield-test-runner:php{PHP}-wp{WP}` | Set to built image tag |

**Note**: Both local and CI testing use version-specific images with the format `shield-test-runner:php{PHP_VERSION}-wp{WP_VERSION}`.

### Key Benefits of Separated Configurations

#### Local Development Benefits
- **Fast Feedback**: ~3 minutes total for complete validation
- **No CI Overhead**: Skips CI-specific compose file entirely
- **Parallel Execution**: Tests multiple WordPress versions simultaneously
- **Database Isolation**: Separate MySQL containers prevent test interference
- **Smart MySQL Monitoring**: Health checks ensure database readiness (Phase 4)
- **Simple Command**: Just run `./bin/run-docker-tests.sh`

#### CI Validation Benefits
- **Full Matrix Testing**: Tests across multiple PHP and WordPress version combinations
- **Production Deployment**: Identical to actual CI/CD pipeline
- **Automated Triggers**: Runs automatically on PR/push events
- **Comprehensive Coverage**: Validates all supported configurations
- **GitHub Actions Integration**: Leverages workflow orchestration and caching

### Usage Examples

#### Local Testing (Fast Feedback)
```bash
# Quick local validation before committing
./bin/run-docker-tests.sh

# What happens:
# 1. Builds version-specific Docker images
# 2. Starts isolated MySQL containers with health monitoring
# 3. Waits for MySQL readiness (~38s typical)
# 4. Runs tests in parallel (~3 minutes total)
# 5. No CI compose file involved
```

#### CI Testing (GitHub Actions)
```yaml
# Triggered automatically on PR/push
# Uses all three compose files:
# - docker-compose.yml (base)
# - docker-compose.ci.yml (CI overrides)
# - docker-compose.package.yml (package testing)
```

### When to Use Each Environment

**Use Local Testing When:**
- Developing new features
- Debugging test failures
- Need quick feedback cycles
- Working offline
- Testing specific code changes

**Use CI Testing When:**
- Validating PR readiness
- Testing matrix combinations
- Final release validation
- Need production parity
- Automated workflow triggers

### Environment Switching

There's no configuration needed to switch between environments:

1. **Local**: Simply run `./bin/run-docker-tests.sh`
2. **CI**: Push to repository or create PR - GitHub Actions handles everything

The environments are completely isolated and cannot interfere with each other. The separation ensures that local development remains fast while CI maintains comprehensive coverage.

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

---

## Running Tests

### Universal: Simple CI-Equivalent Testing

The `bin/run-docker-tests.sh` script provides the simplest way to run comprehensive tests:

```bash
# One command runs everything - matches CI exactly
# Works on all platforms: Windows (Git Bash), Linux, macOS, WSL
./bin/run-docker-tests.sh

# WSL/Linux: Ensure script is executable
chmod +x ./bin/run-docker-tests.sh
./bin/run-docker-tests.sh

# Alternative: Use bash explicitly
bash ./bin/run-docker-tests.sh

# What it automatically executes:
# 1. Detects current WordPress versions (latest: 6.8.2, previous: 6.7.3)
# 2. Builds all assets and dependencies
# 3. Creates production package with vendor_prefixed (ONCE - Phase 1 optimization)
# 4. Builds version-specific Docker images (shield-test-runner:php{VERSION}-wp{VERSION})
# 5. Creates isolated MySQL containers (mysql-latest, mysql-previous)
# 6. Monitors MySQL health checks until ready (~38s)
# 7. Runs PHP 7.4 + WordPress 6.8.2 AND 6.7.3 SIMULTANEOUSLY (Phase 2)
# 8. Executes both unit and integration tests for each
# 9. Validates package structure and production readiness
# 10. Cleans up all containers and temporary files
```

**Phase 2 Achievements** ✅
- **Parallel Execution**: WordPress 6.8.2 and 6.7.3 tests run simultaneously
- **Database Isolation**: Separate MySQL containers prevent test interference
- **Performance Improvement**: 40% faster execution (6m 25s → 3m 28s)
- **Matrix-Ready Naming**: Container names ready for PHP matrix expansion
- **Error Handling**: Comprehensive exit code aggregation and failure reporting
- **Output Management**: Clean result presentation with separate log files

**When to Use:**
- ✅ **Before commits**: Validate changes against CI environment
- ✅ **Pre-release**: Comprehensive production package validation
- ✅ **New environment**: First-time setup with zero configuration
- ✅ **Team collaboration**: Consistent results across all machines
- ✅ **Regression testing**: Full validation after significant changes

### Windows: PowerShell Testing

#### Basic Commands
```powershell
# Windows PowerShell or PowerShell Core
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

### macOS/Linux/WSL: Bash Testing

#### Using PowerShell Core on Linux/WSL
```bash
# Install PowerShell Core if not already installed
sudo apt install -y powershell  # Ubuntu/Debian
brew install --cask powershell  # macOS

# Run tests using PowerShell Core
pwsh ./bin/run-tests.ps1 all -Docker
pwsh ./bin/run-tests.ps1 unit -Docker
pwsh ./bin/run-tests.ps1 integration -Docker
pwsh ./bin/run-tests.ps1 all -Docker -Package

# With custom versions
pwsh ./bin/run-tests.ps1 all -Docker -PhpVersion 8.1 -WpVersion 6.3
```

#### Using Composer
```bash
# Source testing
composer docker:test                    # All tests
composer docker:test:unit               # Unit tests
composer docker:test:integration        # Integration tests

# Package testing
composer docker:test:package            # All tests on built package
```

#### Cross-Platform Path Examples
```bash
# Windows paths in different contexts:
# Windows:     D:\Work\Dev\Repos\Shield
# Git Bash:    /d/Work/Dev/Repos/Shield
# WSL:         /mnt/d/Work/Dev/Repos/Shield
# Docker:      /var/www/html (mounted volume)

# Running from WSL with Windows Docker Desktop
cd /mnt/d/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield
./bin/run-docker-tests.sh

# Running from native Linux filesystem in WSL (faster)
cd ~/projects/WP_Plugin-Shield
./bin/run-docker-tests.sh
```

#### Direct Docker Compose
```bash
# Manual container management
docker-compose -f tests/docker/docker-compose.yml up -d
docker-compose -f tests/docker/docker-compose.yml exec test-runner composer test
docker-compose -f tests/docker/docker-compose.yml down
```

### Local Native Testing

```bash
# After initial setup (see Prerequisites)

# Windows: Using unified test runner
.\bin\run-tests.ps1 all                # All tests
.\bin\run-tests.ps1 unit               # Unit tests only
.\bin\run-tests.ps1 integration        # Integration tests only

# Linux/WSL/macOS: Using PowerShell Core
pwsh ./bin/run-tests.ps1 all            # All tests
pwsh ./bin/run-tests.ps1 unit           # Unit tests only
pwsh ./bin/run-tests.ps1 integration    # Integration tests only

# Using Composer
composer test                           # All tests
composer test:unit                      # Unit tests only
composer test:integration               # Integration tests only

# Direct PHPUnit
vendor/bin/phpunit                      # All tests
vendor/bin/phpunit --testsuite=unit     # Unit tests only
vendor/bin/phpunit --testsuite=integration  # Integration tests only
```

**See also:** [Writing Tests](#writing-tests) for creating new test cases

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

---

## Writing Tests

### Test Structure

```
tests/
├── bootstrap.php          # Main test bootstrap file
├── fixtures/             # Test fixtures and base classes
│   └── TestCase.php     # Base test case
├── Unit/                # Unit tests (no WordPress required)
│   ├── BasicFunctionalityTest.php
│   ├── ConfigurationValidationTest.php
│   └── ControllerTest.php
└── Integration/         # Integration tests (WordPress required)
    ├── FilesHaveJsonFormatTest.php
    ├── PluginActivationTest.php
    └── WordPressHooksTest.php
```

### Unit Tests

Unit tests run without WordPress and focus on isolated functionality:

- Extend `Yoast\PHPUnitPolyfills\TestCases\TestCase` (v4.0 - supports PHPUnit 7-12)
- Don't use WordPress functions directly
- Use Brain\Monkey for mocking WordPress functions if needed
- Focus on business logic and isolated components
- Fast execution (no database required)

**Example Unit Test:**
```php
namespace Tests\Unit;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use Brain\Monkey;

class ExampleTest extends TestCase {
    protected function set_up() {
        parent::set_up();
        Monkey\setUp();
        Monkey\Functions\stubTranslationFunctions();
    }

    protected function tear_down() {
        Monkey\tearDown();
        parent::tear_down();
    }

    public function test_example() {
        // Your test code here
        $this->assertTrue(true);
    }
}
```

### Integration Tests

Integration tests require WordPress test framework:

- Extend `WP_UnitTestCase` or custom base class
- Have access to WordPress functions and database
- Test actual plugin integration with WordPress
- Slower execution (database operations)

**Example Integration Test:**
```php
namespace Tests\Integration;

use WP_UnitTestCase;

class PluginActivationTest extends WP_UnitTestCase {
    public function test_plugin_activates() {
        activate_plugin('wp-simple-firewall/icwp-wpsf.php');
        $this->assertTrue(is_plugin_active('wp-simple-firewall/icwp-wpsf.php'));
    }
}
```

### Test Dependencies

#### PHPUnit Polyfills 4.0
- Provides cross-version PHPUnit compatibility (PHPUnit 7-12)
- Used directly (replaced wp-test-utils wrapper)
- Essential for supporting multiple PHP versions

#### BrainMonkey
- WordPress function mocking for unit tests
- Provides translation and escaping stubs
- Custom bootstrap handles WordPress constants

### Code Coverage

Generate code coverage reports:

```bash
# Requires Xdebug or PCOV
composer run test:coverage

# With Docker (if Xdebug installed in container)
docker-compose -f tests/docker/docker-compose.yml exec test-runner composer test:coverage
```

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

---

## Advanced Topics

### Matrix Testing Configuration

Shield Security provides **enterprise-grade matrix testing** with advanced Docker infrastructure:

**Matrix Testing Scope**:
- **PHP Versions**: Complete support for 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 (6 versions)
- **WordPress Versions**: Dynamic detection of latest stable + previous major version
- **Test Combinations**: Up to 12 combinations (6 PHP × 2 WordPress versions)
- **Performance**: <3 minutes total execution time for complete matrix
- **Validation Status**: Production tested with GitHub Actions Run ID 17036484124
- **Success Rate**: 100% - All matrix combinations passing consistently

**Current Matrix Configuration (Full)**:
- **Active**: PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 + latest/previous WordPress (12 combinations)
- **Triggers**: Automatic on pushes to `develop`, `main`, `master` branches
- **Dynamic Versions**: WordPress versions auto-detected using API with 5-level fallback

**Manual Targeted Testing**:
1. Navigate to **Actions** tab in GitHub repository
2. Select **"Docker Tests"** workflow
3. Click **"Run workflow"** and configure:
   - **PHP Version**: Select from 7.4, 8.0, 8.1, 8.2, 8.3, or 8.4
   - **WordPress Version**: Specify version, "latest", "previous", or leave empty for auto-detection
   - **Testing Mode**: Single targeted combination for focused debugging

**WordPress Version Detection System**:

Shield Security uses a sophisticated 5-level fallback system:

1. **Primary API**: `https://api.wordpress.org/core/version-check/1.7/` (comprehensive version data)
2. **Secondary API**: `https://api.wordpress.org/core/stable-check/1.0/` (security validation)
3. **GitHub Actions Cache**: Cached results with 6-hour TTL
4. **Repository Fallback**: `.github/data/wp-versions-fallback.txt`
5. **Hardcoded Fallback**: Emergency versions (6.8.2 latest, 6.7.3 previous)

**Production Validation Results** ✅:
- **GitHub Actions Run ID 17036484124**: Complete matrix success
- **Unit Tests**: 71 tests, 2483 assertions - ALL PASSED
- **Integration Tests**: 33 tests, 231 assertions - ALL PASSED
- **Package Validation**: All 7 production tests - ALL PASSED
- **Matrix Coverage**: 12 PHP/WordPress combinations - ALL PASSED
- **Total Runtime**: ~3 minutes for complete matrix test suite
- **Local Validation**: PHP 7.4 and 8.3 builds tested and confirmed
- **Status**: Production ready and enterprise validated

### Package Testing vs Source Testing

#### Understanding the Difference

**Source Testing (Development Mode)**
- **Purpose**: Test current development code
- **Environment**: Uses development dependencies and source files
- **Speed**: Faster, no build process required
- **Use Case**: Daily development, TDD, debugging
- **Command**: `.\bin\run-tests.ps1 all -Docker`

**Package Testing (Production Validation)**
- **Purpose**: Test production-ready built package
- **Environment**: Uses `vendor_prefixed` dependencies, cleaned autoload files
- **Process**: Automatically builds package using `composer package-plugin` (supersedes deprecated `bin/build-package.sh`)
- **Validation**: Ensures package structure and production readiness
- **Command**: `.\bin\run-tests.ps1 all -Docker -Package`

#### Package Testing Process
1. **Build Phase**: Runs `composer package-plugin` (via `bin/package-plugin.php`) to create production package
2. **Prefix Phase**: Dependencies moved to `vendor_prefixed` with Strauss
3. **Clean Phase**: Development files excluded, autoload references cleaned
4. **Test Phase**: Docker container tests the built package
5. **Validation Phase**: Verifies package structure and functionality

#### Package Validation Tests
Package testing validates:
- ✅ `vendor_prefixed` directory exists with Strauss-prefixed dependencies
- ✅ Development files (`.github`, tests) properly excluded
- ✅ Twig references cleaned from autoload files
- ✅ Plugin structure matches production requirements
- ✅ All dependencies properly namespaced
- ✅ No development artifacts in package
- ✅ Production-ready package validated

#### When to Use Each Mode

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

### Environment Variables

#### Native Testing
```bash
WP_TESTS_DB_NAME=wordpress_test
WP_TESTS_DB_USER=root
WP_TESTS_DB_PASSWORD=root
WP_TESTS_DB_HOST=127.0.0.1
```

#### Docker Testing Environment Variables

##### Core Configuration
```bash
PHP_VERSION=8.2                    # PHP version (7.4-8.4)
WP_VERSION=6.4                     # WordPress version
MYSQL_VERSION=8.0                  # MySQL/MariaDB version
MYSQL_DATABASE=wordpress_test       # Test database name
MYSQL_USER=wordpress               # Database user
MYSQL_PASSWORD=wordpress           # Database password
```

##### Testing Mode Control
```bash
SHIELD_PACKAGE_PATH=               # Set for package testing mode
PLUGIN_SOURCE=../../               # Plugin source directory
SKIP_DB_CREATE=false              # Skip database creation
DEBUG=false                       # Enable debug output
```

##### Matrix-Specific Variables
```bash
SHIELD_DOCKER_PHP_VERSION=         # Container PHP info
SHIELD_DOCKER_WP_VERSION=          # Container WordPress info
SHIELD_TEST_MODE=docker            # Testing mode indicator
CACHE_KEY_PREFIX=                  # Cache differentiation per matrix
TEST_PHP_VERSION=                  # Test environment PHP version
TEST_WP_VERSION=                   # Test environment WordPress version
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

### CI/CD Integration

#### Continuous Integration - Matrix Testing

Tests run automatically on GitHub Actions with comprehensive matrix testing:

**Matrix Coverage** (GitHub Actions Run ID 17036484124 - 100% Success):
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 (complete matrix)
- **WordPress Versions**: Dynamic detection of latest (6.8.2) + previous major (6.7.3)
- **Total Combinations**: 12 test combinations run in parallel
- **Code Quality**: PHPCS, PHPStan, and package validation
- **Production Ready**: All tests passing across matrix combinations

**Automatic Triggers**: Matrix testing runs on all pushes to main branches (develop, main, master)
**Manual Testing**: Custom PHP/WordPress combinations available via workflow dispatch

#### GitHub Actions Features
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

---

## Docker Technical Details

### Container Architecture

#### Phase 2 Parallel Architecture
```yaml
# Parallel test execution with isolated databases
services:
  mysql-latest:        # WordPress latest (6.8.2) database
    image: mysql:8.0
    container_name: mysql-latest
    healthcheck:       # Phase 4: Smart health monitoring
      test: ["CMD", "mysqladmin", "ping"]
      interval: 5s
      timeout: 3s
      retries: 10
    
  mysql-previous:      # WordPress previous (6.7.3) database
    image: mysql:8.0
    container_name: mysql-previous
    healthcheck:       # Phase 4: Smart health monitoring
      test: ["CMD", "mysqladmin", "ping"]
      interval: 5s
      timeout: 3s
      retries: 10
    
  test-runner-latest:  # WordPress latest test runner
    image: shield-test-runner:php${PHP_VERSION}-wp${WP_LATEST}
    container_name: test-runner-latest
    depends_on:
      - mysql-latest
    
  test-runner-previous: # WordPress previous test runner
    image: shield-test-runner:php${PHP_VERSION}-wp${WP_PREVIOUS}
    container_name: test-runner-previous
    depends_on:
      - mysql-previous
```

**Container Components:**
- **MySQL Containers**: Isolated databases for each WordPress version
- **Test Runners**: PHP/WordPress version-specific containers (e.g., `shield-test-runner:php7.4-wp6.9`)
- **Log Files**: `/tmp/shield-test-latest.log`, `/tmp/shield-test-previous.log`
- **Package Location**: `/tmp/shield-package-local` (shared across both test streams)

### Multi-Stage Build Process

The Dockerfile uses a 5-stage optimized build:

1. **Base Stage**: Ubuntu with PHP and system dependencies
2. **PHP Extensions**: Install PHP modules for all supported versions
3. **Composer Stage**: Install Composer and PHPUnit
4. **WordPress Stage**: Download and configure WordPress test framework
5. **Final Stage**: Combine all components with proper permissions

**Critical ARG Propagation Fix**: 
- Dockerfile line 108 fixed from `ARG WP_VERSION` to `ARG WP_VERSION=latest`
- Ensures WordPress version propagates through all build stages

### Environment Detection

Bootstrap files automatically detect the testing environment:

1. **Package Testing**: When `SHIELD_PACKAGE_PATH` environment variable is set
2. **Docker Testing**: When `/var/www/html/wp-content/plugins/wp-simple-firewall` exists
3. **Source Testing**: Default mode using current repository directory

This follows patterns from Yoast WordPress SEO, Easy Digital Downloads, and WooCommerce.

### Docker Compose Files

The testing infrastructure uses multiple Docker Compose files for different purposes:

**See also:** [Manual Docker Commands](#manual-docker-commands) for usage examples

##### Base Configuration: docker-compose.yml
- **Purpose**: Base container configuration for local development testing
- **Services**: MySQL database and test runner containers
- **Usage**: Default configuration for `run-tests.ps1` and manual Docker testing
- **Environment**: Development-focused with source code mounting

##### CI Configuration: docker-compose.ci.yml
- **Purpose**: CI-specific overrides for GitHub Actions workflow
- **Features**: 
  - Optimized for automated testing environment
  - Includes CI-specific environment variables
  - Streamlined for parallel matrix execution
- **Usage**: Automatically used by GitHub Actions workflow
- **When to use**: Reference for understanding CI behavior, not for local use

##### Package Testing: docker-compose.package.yml
- **Purpose**: Override configuration for testing built packages
- **Features**:
  - Mounts built package instead of source code
  - Tests production-ready plugin structure
  - Validates vendor_prefixed dependencies
- **Usage**: Applied with `-Package` flag or `composer docker:test:package`
- **When to use**: Pre-release validation and production testing

##### Relationship Between Files
```yaml
# Base configuration (always loaded)
docker-compose.yml
  ↓
# Override for package testing (when -Package flag used)
docker-compose.yml + docker-compose.package.yml
  ↓
# CI environment (GitHub Actions only)
docker-compose.yml + docker-compose.ci.yml
```

### Manual Docker Commands

For advanced usage or debugging:

```bash
# Start containers manually (source testing)
docker-compose -f tests/docker/docker-compose.yml up -d

# Start containers with package testing override
docker-compose -f tests/docker/docker-compose.yml -f tests/docker/docker-compose.package.yml up -d

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

**See also:** [Docker Compose Files](#docker-compose-files) for file descriptions | [Container Architecture](#container-architecture) for container organization

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

---

## Troubleshooting

**Jump to:** [Docker Issues](#docker-specific-issues) | [Windows Issues](#windows-specific-issues) | [Performance](#performance-issues) | [Debug Mode](#debug-mode)

### Cross-Platform Considerations

#### Line Ending Configuration
```bash
# Configure Git for cross-platform development
# Windows (preserve CRLF)
git config --global core.autocrlf true

# Linux/macOS/WSL (convert to LF)
git config --global core.autocrlf input

# Check current setting
git config --get core.autocrlf

# Fix line endings in existing files
# Convert all files to LF (Linux/macOS/WSL)
find . -type f -name "*.sh" -exec dos2unix {} \;
find . -type f -name "*.php" -exec dos2unix {} \;

# Or using Git
git add --renormalize .
git commit -m "Normalize line endings"
```

#### Path Separator Differences
```bash
# Windows uses backslash
D:\Work\Dev\Repos\Shield

# Linux/macOS/WSL uses forward slash
/mnt/d/Work/Dev/Repos/Shield

# Docker always uses forward slash
/var/www/html/wp-content/plugins/wp-simple-firewall

# PowerShell Core on Linux/WSL uses forward slash
pwsh -c "Test-Path '/mnt/d/Work/Dev/Repos/Shield'"

# PowerShell Core on Windows uses backslash
pwsh -c "Test-Path 'D:\Work\Dev\Repos\Shield'"
```

#### Script Execution Permissions
```bash
# Linux/macOS/WSL: Make scripts executable
chmod +x ./bin/*.sh
chmod +x ./bin/*.ps1

# Check permissions
ls -la ./bin/

# Run with explicit interpreter if not executable
bash ./bin/run-docker-tests.sh
pwsh ./bin/run-tests.ps1
```

#### Environment Variable Handling
```bash
# Windows PowerShell
$env:WP_TESTS_DB_NAME = "wordpress_test"
$env:WP_TESTS_DB_USER = "root"

# Linux/macOS/WSL Bash
export WP_TESTS_DB_NAME="wordpress_test"
export WP_TESTS_DB_USER="root"

# PowerShell Core (cross-platform)
$env:WP_TESTS_DB_NAME = "wordpress_test"  # Works everywhere

# Docker (via .env file)
echo "WP_TESTS_DB_NAME=wordpress_test" >> tests/docker/.env
```

#### Case Sensitivity Issues
```bash
# Windows filesystem is case-insensitive
# Linux/macOS/WSL filesystem is case-sensitive

# This works on Windows but fails on Linux:
require_once 'MyFile.php';  # File is actually myfile.php

# Always use exact case matching
require_once 'myfile.php';  # Works everywhere

# Find case mismatches
find . -type f -name "*.php" | while read file; do
    basename="$(basename "$file")"
    if [ "$basename" != "$(echo "$basename" | tr '[:upper:]' '[:lower:]')" ]; then
        echo "Mixed case file: $file"
    fi
done
```

### Common Issues

#### "WordPress test library not found"
Run the install script to download WordPress test framework:
```bash
# Windows PowerShell
.\bin\install-wp-tests.ps1 -DB_NAME wordpress_test -DB_USER root -DB_PASS your_password

# Linux/macOS/WSL
bash bin/install-wp-tests.sh wordpress_test root 'your_password' localhost latest
```

#### MySQL Connection Issues
```bash
# Ensure MySQL/MariaDB is running
# Check database credentials
# Create test database manually if needed:
mysql -u root -p -e "CREATE DATABASE wordpress_test;"
```

**See also:** [Prerequisites & Setup](#prerequisites--setup) for complete setup instructions

#### PHP Version Issues
```bash
# Check PHP version (8.3+ recommended, not 8.2)
php -v
```

### Docker-Specific Issues

**See also:** [Docker Technical Details](#docker-technical-details) for architecture information | [Simple Docker Testing Requirements](#simple-docker-testing-requirements) for setup

#### Docker Not Available
```
❌ Docker is not available or not running
```
**Solution**: Ensure Docker Desktop is installed and running
- Windows: Check Docker Desktop in system tray
- macOS: Check Docker icon in menu bar
- Linux: Verify Docker service is running

#### Permission Issues (Linux/macOS/WSL)
```
docker: Got permission denied while trying to connect
```
**Solution**: Add user to docker group
```bash
# Add user to docker group
sudo usermod -aG docker $USER

# Apply group changes without logout (Linux/WSL)
newgrp docker

# Or log out and back in
exit
# Log back in

# Verify docker group membership
groups | grep docker

# Alternative: Fix socket permissions (temporary)
sudo chmod 666 /var/run/docker.sock
```

#### Docker Socket Issues on WSL
```
Cannot connect to the Docker daemon at unix:///var/run/docker.sock
```
**Solution**: Ensure Docker Desktop WSL integration is enabled
```bash
# Check if Docker is accessible from WSL
docker version

# If not, check Docker Desktop settings:
# 1. Settings → Resources → WSL Integration
# 2. Enable integration with your distro
# 3. Restart Docker Desktop

# Alternative: Use Docker context
docker context ls
docker context use default
```

#### SELinux/AppArmor Issues (Linux)
```
Permission denied when mounting volumes
```
**Solution**: Configure SELinux/AppArmor for Docker
```bash
# For SELinux (RHEL/CentOS/Fedora)
# Add :Z flag to volume mounts in docker-compose.yml
volumes:
  - ./:/var/www/html:Z

# Or disable SELinux temporarily (not recommended)
sudo setenforce 0

# For AppArmor (Ubuntu/Debian)
# Check AppArmor status
sudo aa-status

# If issues persist, add to docker-compose.yml:
security_opt:
  - apparmor:unconfined
```

#### File Permission Errors on Linux
```
Failed to write to file: Permission denied
```
**Solution**: Fix file ownership and permissions
```bash
# Check current ownership
ls -la tests/

# Fix ownership (replace 'username' with your user)
sudo chown -R username:username .

# Fix directory permissions
find . -type d -exec chmod 755 {} \;

# Fix file permissions
find . -type f -exec chmod 644 {} \;

# Make scripts executable
find ./bin -type f \( -name "*.sh" -o -name "*.ps1" \) -exec chmod +x {} \;
```

#### Database Connection Issues
```
Connection refused to database
```
**Solution**: Database health checks (Phase 4) automatically handle MySQL readiness
```bash
# Check container status and health
docker-compose -f tests/docker/docker-compose.yml ps

# View database logs and startup timing
docker-compose -f tests/docker/docker-compose.yml logs mysql

# MySQL typically takes ~38 seconds to fully initialize
# Health checks automatically wait for readiness

# Restart with fresh database if needed
docker-compose -f tests/docker/docker-compose.yml down -v
docker-compose -f tests/docker/docker-compose.yml up -d

# Verify MySQL health check timing (Phase 4 diagnostic)
bash ./bin/test-mysql-monitoring.sh
```

### Matrix Testing Issues

**See also:** [Matrix Testing Configuration](#matrix-testing-configuration) for full matrix details | [CI/CD Integration](#cicd-integration) for GitHub Actions setup

#### Matrix Not Running Expected Combinations
**Problem**: Matrix only testing one combination instead of full matrix

**Solutions**:
1. **Check Workflow Trigger Type**:
   - Manual triggers (workflow_dispatch) = single job with selected versions
   - Automatic triggers (push to main branches) = matrix execution

2. **Verify Matrix Configuration** in `.github/workflows/docker-tests.yml`:
   ```yaml
   matrix:
     php: ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4']  # Full PHP matrix
     wordpress: ${{ fromJSON(...) }}  # Uses detected versions
   ```

3. **Matrix Coverage**:
   - Full matrix: 6 PHP versions × 2 WordPress versions = 12 parallel jobs
   - All jobs run simultaneously in GitHub Actions

#### WordPress Version Detection Failures
**Problem**: WordPress API detection timeout or invalid versions

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
   
   # Clear cache if needed
   rm -rf ~/.wp-api-cache/
   ```

#### PHP/WordPress Compatibility Issues
**Problem**: Matrix combination fails due to version incompatibility

**Solutions**:
1. **Check Compatibility Matrix**:
   ```bash
   # WordPress version requirements:
   # WordPress 6.8+ : PHP 7.4-8.4 ✅
   # WordPress 6.7+ : PHP 7.4-8.4 ✅
   # WordPress 6.6  : PHP 7.4-8.3 (8.4 experimental)
   ```

2. **Test Specific Version Locally**:
   ```bash
   # Build with specific matrix combination
   docker build tests/docker/ \
     --build-arg PHP_VERSION=8.3 \
     --build-arg WP_VERSION=6.8.2 \
     --progress=plain
   ```

### Windows-Specific Issues

**See also:** [WSL2 Setup for Docker](#wsl2-setup-for-docker) for WSL2 configuration | [WSL Performance Tips](#wsl2-setup-for-docker) for optimization

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
```powershell
# Windows PowerShell (as Administrator)
# Install WSL2
wsl --install

# List installed distributions
wsl --list --verbose

# Convert existing WSL1 to WSL2
wsl --set-version Ubuntu 2

# Set WSL2 as default
wsl --set-default-version 2

# In Docker Desktop:
# Settings → General → Use the WSL 2 based engine ✓
# Settings → Resources → WSL Integration → Enable distro ✓
# Settings → Resources → Advanced → Memory: 4GB+
```

#### WSL2 Performance Issues
```
Slow file operations in WSL2
```
**Solution**: Optimize file location and Docker settings
```bash
# Move project to WSL filesystem (10x faster)
# Instead of: /mnt/d/Work/Dev/Repos/Shield
# Use:        ~/projects/Shield

# Clone directly in WSL
cd ~/projects
git clone https://github.com/your-repo/Shield.git
cd Shield

# Configure Docker to use WSL2 backend
# Docker Desktop → Settings → General → Use WSL 2 based engine

# Increase WSL2 memory limit
# Create/edit %USERPROFILE%\.wslconfig
[wsl2]
memory=8GB
processors=4
swap=4GB

# Restart WSL
wsl --shutdown
wsl
```

#### PowerShell Execution Policy
```
Execution of scripts is disabled on this system
```
**Solution**: Allow script execution
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Performance Issues

**See also:** [WSL Performance Tips](#wsl2-setup-for-docker) for WSL2 optimization | [Performance Optimization](#performance-optimization) in Maintenance section

#### Slow Container Startup
**Solution**: Optimize Docker Desktop settings
- Allocate 4GB+ RAM (8GB for parallel execution)
- Enable 2+ CPU cores
- Use WSL2 backend on Windows
- Ensure SSD storage for Docker

#### Build Performance Analysis (Phase 4)
**Build time breakdown** (Total: ~2m 49s):
- **NPM Build**: 1m 19s (46% - primary bottleneck)
- **File Copying**: 1m 0s (35%)
- **Composer Operations**: 27s combined
- **Other Operations**: 23s

**Optimization opportunities**:
- Consider npm build caching strategies
- Optimize file copy operations
- Run build timing analysis: `bash ./bin/analyze-build-timing.sh`

#### Test Execution Timeout
**Solution**: Increase container resources
```bash
# Check container resource usage
docker stats

# Rebuild with no cache
docker-compose -f tests/docker/docker-compose.yml build --no-cache
```

### Debug Mode

#### PowerShell Debug Mode
```powershell
# Enable debug output
.\bin\run-tests.ps1 all -Docker -Debug

# Debug specific matrix combination
.\bin\run-tests.ps1 all -Docker -PhpVersion 8.1 -WpVersion 6.8.2 -Debug
```

#### Phase 4 Diagnostic Scripts
```bash
# Test MySQL startup timing and health checks
bash ./bin/test-mysql-monitoring.sh

# Analyze build performance bottlenecks
bash ./bin/analyze-build-timing.sh

# These scripts help identify:
# - MySQL initialization timing (~38s typical)
# - Build phase performance (NPM, file copy, Composer)
# - Container startup sequences
```

#### WordPress Version Detection Debug
```bash
# Debug version detection process
./.github/scripts/detect-wp-versions.sh --debug

# Test all fallback mechanisms
./.github/scripts/detect-wp-versions.sh --test-fallbacks

# Display cache information
./.github/scripts/detect-wp-versions.sh --cache-info
```

#### Container Environment Debug
```bash
# Debug environment variables in running container
docker exec -it container_name env | grep -E "PHP_VERSION|WP_VERSION|SHIELD_"

# Check .env file creation
cat tests/docker/.env

# Verify docker-compose configuration
docker-compose -f tests/docker/docker-compose.yml config
```

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

---

## Appendices

### Testing Methods Comparison

#### Simple Docker Testing (run-docker-tests.sh) - Ultimate Simplicity ⚡
- ✅ **Ultimate Zero Setup**: One command, zero configuration
- ✅ **CI Parity Guaranteed**: Identical to GitHub Actions
- ✅ **Auto-Discovery**: Detects WordPress versions automatically
- ✅ **Production Validation**: Always tests built package
- ✅ **Complete Coverage**: Both unit and integration tests
- ✅ **Cross-Platform**: Works on Windows (Git Bash), macOS, Linux
- ✅ **Error Handling**: Clear failure messages and automatic cleanup
- ✅ **Team Consistency**: Identical results for all developers
- ✅ **Phase 2 Optimized**: Parallel execution for 40% faster testing

#### Advanced Docker Testing - Production Validated
- ✅ **Zero Setup Required**: No local PHP, MySQL, or WordPress needed
- ✅ **Matrix Testing**: 12 PHP/WordPress combinations validated
- ✅ **Consistent Environment**: Identical results across platforms
- ✅ **Version Flexibility**: Test any PHP (7.4-8.4) and WordPress version
- ✅ **No Local Conflicts**: Complete isolation from local environment
- ✅ **CI/CD Parity**: Exact match with GitHub Actions
- ✅ **Package Validation**: Production-ready package testing
- ✅ **Clean State**: Fresh database for each test run
- ✅ **Enterprise Grade**: Comprehensive caching and optimization

#### Local Testing Benefits
- ✅ **Faster Execution**: No container overhead (~30% faster)
- ✅ **Direct Debugging**: Use local debugging tools (Xdebug, IDE)
- ✅ **No Docker Dependency**: Works without Docker Desktop
- ✅ **Resource Efficiency**: Lower memory and CPU usage
- ✅ **Immediate Changes**: No container rebuilding needed

#### When to Use Each

**Use Simple Docker Testing (run-docker-tests.sh) for:**
- ✅ **Most scenarios** - comprehensive validation with minimal effort
- ✅ **Pre-commit validation** - ensure changes work in CI environment
- ✅ **New team members** - zero setup, immediate productivity
- ✅ **Release preparation** - comprehensive production validation
- ✅ **Regression testing** - validate major changes across versions

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

### Complete Testing Workflow

#### Recommended Development Cycle

**Option A: Simple CI-Equivalent Workflow (Recommended)**
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

**Option B: Granular Development Workflow**
```powershell
# 1. Make code changes
# 2. Quick unit test validation
.\bin\run-tests.ps1 unit -Docker

# 3. Full integration testing
.\bin\run-tests.ps1 integration -Docker

# 4. Before release: validate production package
.\bin\run-tests.ps1 all -Docker -Package
```

### Maintenance

#### Keeping Images Updated
```bash
# Update base images
docker-compose -f tests/docker/docker-compose.yml pull
docker-compose -f tests/docker/docker-compose.yml build --no-cache

# Clean up old images
docker system prune -f
```

#### Performance Optimization
```bash
# Use Docker BuildKit for faster builds
export DOCKER_BUILDKIT=1
docker-compose -f tests/docker/docker-compose.yml build

# Allocate more resources in Docker Desktop settings:
# - Memory: 4GB+ recommended (8GB for parallel execution)
# - CPUs: 2+ recommended
# - Disk space: 20GB+ recommended
```

#### Parallel Execution Monitoring
```bash
# Watch parallel test execution
watch -n 2 'docker ps --filter "name=test-runner" --format "table {{.Names}}\t{{.Status}}\t{{.RunningFor}}"'

# Monitor MySQL containers
watch -n 2 'docker ps --filter "name=mysql-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"'

# Check system resource usage
watch -n 2 'docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}"'
```

#### Container Cleanup
```bash
# Complete cleanup of test infrastructure
docker stop $(docker ps -q --filter "name=test-runner")
docker stop $(docker ps -q --filter "name=mysql-")
docker rm $(docker ps -aq --filter "name=test-runner")
docker rm $(docker ps -aq --filter "name=mysql-")
docker network prune -f

# Clean up test logs
rm -f /tmp/shield-test-*.log
rm -f /tmp/shield-test-*.exit
rm -rf /tmp/shield-package-local
```

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

### Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)
- [Brain Monkey Documentation](https://brain-wp.github.io/BrainMonkey/)
- [PHPUnit Polyfills Documentation](https://github.com/Yoast/PHPUnit-Polyfills)
- [Docker Documentation](https://docs.docker.com/)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)

### Related Documentation

#### Project Documentation
- **[README.md](README.md)** - Project overview and quick start guide
- **[README-SMOKE-TESTS.md](README-SMOKE-TESTS.md)** - Specialized smoke testing procedures
- **[.agent-os/knowledge/testing-infrastructure.md](.agent-os/knowledge/testing-infrastructure.md)** - Deep technical details and architecture

#### CI/CD Configuration
- **[.github/workflows/docker-tests.yml](.github/workflows/docker-tests.yml)** - GitHub Actions matrix testing workflow
- **[.github/scripts/detect-wp-versions.sh](.github/scripts/detect-wp-versions.sh)** - WordPress version detection script

#### Docker Configuration
- **[tests/docker/Dockerfile](tests/docker/Dockerfile)** - Multi-stage Docker build definition
- **[tests/docker/docker-compose.yml](tests/docker/docker-compose.yml)** - Base Docker Compose configuration
- **[tests/docker/docker-compose.package.yml](tests/docker/docker-compose.package.yml)** - Package testing overrides
- **[tests/docker/docker-compose.ci.yml](tests/docker/docker-compose.ci.yml)** - CI-specific configuration

[Back to top](#shield-security-wordpress-plugin---testing-documentation)

---

**Recommendation:** Start with `./bin/run-docker-tests.sh` for comprehensive validation, then use local testing for rapid development iteration. The simple Docker script provides the best balance of thoroughness and ease of use.