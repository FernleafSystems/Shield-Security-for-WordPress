# Shield Security Plugin Packaging System

## Overview

The Shield Security plugin packaging system is a sophisticated build process that transforms development source code into a production-ready WordPress plugin distribution. This document consolidates all learnings from multiple sessions addressing packaging challenges, dependency conflicts, and distribution requirements.

## Table of Contents

1. [Core Concepts](#core-concepts)
2. [Packaging Architecture](#packaging-architecture)
3. [Strauss Prefixing System](#strauss-prefixing-system)
4. [Build Process](#build-process)
5. [Distribution Structure](#distribution-structure)
6. [Package Validation](#package-validation)
7. [Common Issues and Solutions](#common-issues-and-solutions)
8. [Build Scripts](#build-scripts)
9. [CI/CD Integration](#cicd-integration)
10. [Version Management](#version-management)

## Core Concepts

### The Packaging Challenge

WordPress plugins operate in a shared environment where multiple plugins may use the same third-party libraries. This creates several challenges:

1. **Namespace Conflicts**: Two plugins using different versions of the same library (e.g., Monolog, Twig)
2. **Autoloader Conflicts**: Multiple autoloaders trying to load the same classes
3. **Development vs Production**: Development files shouldn't be distributed to end users
4. **File Size**: Minimizing distribution size while maintaining functionality
5. **Compatibility**: Ensuring the plugin works across different WordPress environments

### Solution: Dual Vendor Directory Approach

Shield Security solves these challenges using a dual vendor directory strategy:

- **`vendor/`**: Standard Composer dependencies (minimal, core requirements only)
- **`vendor_prefixed/`**: Strauss-prefixed dependencies with namespace isolation

## Packaging Architecture

### Directory Structure Evolution

```
Development Structure:
├── src/lib/
│   ├── composer.json        # Dependencies and Strauss config
│   ├── vendor/              # Standard Composer dependencies
│   └── vendor_prefixed/     # Prefixed dependencies (created during build)
├── tests/                   # Test files (excluded from distribution)
├── bin/                     # Build scripts (excluded from distribution)
└── [plugin files]

Distribution Structure:
├── assets/                  # Frontend resources
│   ├── dist/               # Compiled JS/CSS
│   └── images/             # Plugin images
├── flags/                   # Country flags
├── languages/              # Translation files
├── src/                    # Plugin source code
│   └── lib/
│       ├── vendor/         # Minimal vendor (autoloader only)
│       └── vendor_prefixed/ # All prefixed dependencies
├── templates/              # Twig templates
└── [9 root files]          # Main plugin files
```

### Package Components (WordPress.org Standard)

The final distribution package contains exactly 14 components:

**9 Root Files:**
1. `icwp-wpsf.php` - Main plugin file
2. `plugin.json` - Plugin configuration (6,673 lines)
3. `plugin_init.php` - Initialization logic
4. `plugin_autoload.php` - Autoloader setup
5. `plugin_compatibility.php` - Compatibility checks
6. `readme.txt` - WordPress.org readme
7. `cl.json` - Changelog data
8. `uninstall.php` - Uninstall handler
9. `unsupported.php` - Unsupported version handler

**5 Directories:**
1. `assets/` - Frontend resources (dist/, images/)
2. `flags/` - Country flag icons
3. `languages/` - Internationalization files
4. `src/` - Plugin source code
5. `templates/` - Twig template files

## Strauss Prefixing System

### What is Strauss?

Strauss is a PHP dependency prefixing tool that prevents namespace conflicts by:
- Rewriting namespaces with a custom prefix
- Updating all references to use the new namespace
- Creating isolated copies of dependencies

### Strauss Configuration

Located in `src/lib/composer.json`:

```json
{
  "extra": {
    "strauss": {
      "target_directory": "vendor_prefixed",
      "namespace_prefix": "AptowebDeps\\",
      "classmap_prefix": "AptowebDeps_Pfx_",
      "constant_prefix": "APTOWEB_PFX_",
      "packages": [
        "monolog/monolog",
        "twig/twig",
        "crowdsec/capi-client"
      ],
      "exclude_from_copy": {
        "packages": [
          "psr/log",
          "symfony/deprecation-contracts",
          "symfony/polyfill-ctype",
          "symfony/polyfill-mbstring",
          "symfony/polyfill-php81",
          "symfony/polyfill-php80",
          "symfony/polyfill-uuid"
        ]
      },
      "delete_vendor_packages": false,
      "delete_vendor_files": false
    }
  }
}
```

### Prefixing Process

1. **Download Strauss**: Version 0.19.4 is used for consistency
2. **Execute Prefixing**: `php strauss.phar` in `src/lib/` directory
3. **Result**: Creates `vendor_prefixed/` with isolated dependencies
4. **Namespace Transformation**:
   - `Monolog\Logger` → `AptowebDeps\Monolog\Logger`
   - `Twig\Environment` → `AptowebDeps\Twig\Environment`

### Challenges and Solutions

#### Twig/Monolog Duplication Issue

**Problem**: Twig and Monolog were present in both `vendor/` and `vendor_prefixed/`, causing:
- Autoloader conflicts
- Duplicate class definitions
- File size bloat

**Solution**: Post-processing cleanup:
1. Remove duplicates from `vendor/`: `rm -rf vendor/twig/ vendor/monolog/`
2. Prune autoload files to remove Twig references
3. Keep only autoloader infrastructure in `vendor/`

#### Autoload File Pruning

The build process cleans Composer autoload files to remove references to deleted packages:

```bash
# Files to clean
autoload_files.php
autoload_static.php
autoload_psr4.php

# Remove lines containing /twig/twig/
grep -v '/twig/twig/' "$file" > "$file.tmp" && mv "$file.tmp" "$file"
```

## Build Process

### Complete Build Pipeline

1. **Dependency Installation**
   ```bash
   cd src/lib
   composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
   ```

2. **Strauss Prefixing**
   ```bash
   curl -sL https://github.com/BrianHenryIE/strauss/releases/download/0.19.4/strauss.phar -o strauss.phar
   php strauss.phar
   ```

3. **File Copying**
   - Copy 9 root files
   - Copy 5 directories
   - Maintain exact structure

4. **Cleanup Operations**
   - Remove duplicate libraries from vendor/
   - Delete development files
   - Prune autoload references
   - Remove strauss.phar

5. **Validation**
   - Verify all required files exist
   - Check vendor_prefixed creation
   - Validate autoload functionality

### Build Script (`bin/build-package.sh`)

```bash
#!/bin/bash
set -e

PACKAGE_DIR="${1:-}"
WORKSPACE_DIR="${2:-$(pwd)}"

# Create package structure
mkdir -p "$PACKAGE_DIR"

# Copy root files
for file in icwp-wpsf.php plugin_init.php readme.txt plugin.json cl.json \
            plugin_autoload.php plugin_compatibility.php uninstall.php unsupported.php; do
  [ -f "$file" ] && cp "$file" "$PACKAGE_DIR/"
done

# Copy directories
for dir in src assets flags languages templates; do
  [ -d "$dir" ] && cp -R "$dir" "$PACKAGE_DIR/"
done

# Install dependencies
cd "$PACKAGE_DIR/src/lib"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Run Strauss
curl -sL https://github.com/BrianHenryIE/strauss/releases/download/0.19.4/strauss.phar -o strauss.phar
php strauss.phar

# Cleanup
rm -rf vendor/twig/ vendor/monolog/ vendor/bin/
rm -f vendor_prefixed/autoload-files.php strauss.phar

# Prune autoload files
cd vendor/composer
for file in autoload_files.php autoload_static.php autoload_psr4.php; do
  [ -f "$file" ] && grep -v '/twig/twig/' "$file" > "$file.tmp" && mv "$file.tmp" "$file"
done
```

## Distribution Structure

### WordPress.org Requirements

The plugin must match the exact structure expected by WordPress.org:

```
wp-simple-firewall/
├── assets/
│   ├── dist/           # Compiled frontend assets
│   └── images/         # Plugin images
├── flags/              # Country flags for IP blocking
├── languages/          # Translation files (.mo, .po)
├── src/
│   └── lib/
│       ├── src/        # Plugin PHP source code
│       ├── vendor/     # Minimal Composer autoloader
│       └── vendor_prefixed/  # Prefixed dependencies
├── templates/          # Twig templates
├── icwp-wpsf.php      # Main plugin file
├── plugin.json        # Configuration
├── plugin_init.php    # Initialization
├── plugin_autoload.php # Autoloader
├── plugin_compatibility.php # Compatibility
├── readme.txt         # WordPress readme
├── cl.json           # Changelog
├── uninstall.php     # Cleanup on uninstall
└── unsupported.php   # Version check handler
```

### Files Excluded from Distribution

These files/directories are never included in the production package:

- `.github/` - GitHub Actions workflows
- `.agent-os/` - Agent OS documentation
- `.claude/` - Claude session notes
- `tests/` - Test suites
- `bin/` - Build and utility scripts
- `docs/` - Documentation
- Development configs: `phpunit.xml`, `phpstan.neon`, `composer.json` (root)
- Version control: `.git/`, `.gitignore`, `.gitattributes`
- CI/CD files: `.travis.yml`, `patchwork.json`
- Development dependencies in `vendor/bin/`

## Package Validation

### Automated Validation Tests

The `PluginPackageValidationTest.php` verifies:

1. **Required Files Exist**
   - All 9 root files present
   - Critical configuration files

2. **Required Directories Exist**
   - All 5 directories present
   - Proper structure maintained

3. **Development Files Excluded**
   - No test files
   - No build scripts
   - No development configs

4. **Strauss Prefixing Applied**
   - `vendor_prefixed/` exists
   - Autoload classmap present
   - Namespaces properly prefixed

5. **Autoload Files Pruned**
   - No Twig references in autoload
   - No duplicate class definitions

### Manual Verification Commands

```bash
# Verify package structure
ls -la /path/to/package/

# Check for vendor_prefixed
[ -d "src/lib/vendor_prefixed" ] && echo "✓ Prefixed vendors exist"

# Verify no duplicates
[ ! -d "src/lib/vendor/twig" ] && echo "✓ No Twig duplication"
[ ! -d "src/lib/vendor/monolog" ] && echo "✓ No Monolog duplication"

# Check autoload pruning
grep -c '/twig/twig/' src/lib/vendor/composer/autoload_*.php
# Should return 0
```

## Common Issues and Solutions

### Issue 1: Namespace Conflicts

**Symptom**: Class redeclaration errors, wrong library versions loaded

**Solution**: 
- Ensure Strauss prefixing is applied
- Verify no unprefixed dependencies remain
- Check autoload order

### Issue 2: Missing Dependencies

**Symptom**: Class not found errors in production

**Solution**:
- Verify `vendor_prefixed/` is included in package
- Check Strauss configuration includes all necessary packages
- Ensure autoload classmap is present

### Issue 3: Autoloader Conflicts

**Symptom**: Multiple autoloaders trying to load same classes

**Solution**:
- Prune duplicate references from autoload files
- Remove original vendor directories after prefixing
- Use single autoloader entry point

### Issue 4: Package Size Too Large

**Symptom**: Distribution package exceeds size limits

**Solution**:
- Remove development dependencies (`--no-dev`)
- Exclude test files and documentation
- Optimize autoloader (`--optimize-autoloader`)
- Remove unnecessary vendor subdirectories

### Issue 5: CI/CD Build Failures

**Symptom**: Tests pass locally but fail in CI

**Solution**:
- Ensure build script creates identical structure
- Verify all file permissions are correct
- Check for platform-specific path issues
- Use consistent Strauss version (0.19.4)

## Build Scripts

### Shell Script (Linux/CI)

**Location**: `bin/build-package.sh`

**Features**:
- Error handling with `set -e`
- Configurable package directory
- Comprehensive validation
- Strauss version pinning
- Autoload pruning

### PowerShell Script (Windows Development)

**Conceptual Structure** (from session notes):

```powershell
param(
    [string]$PackageDir = ".\dist\wp-simple-firewall"
)

# Create package directory
New-Item -ItemType Directory -Force -Path $PackageDir

# Copy files
$rootFiles = @(
    "icwp-wpsf.php", "plugin.json", "plugin_init.php",
    "plugin_autoload.php", "plugin_compatibility.php",
    "readme.txt", "cl.json", "uninstall.php", "unsupported.php"
)

foreach ($file in $rootFiles) {
    if (Test-Path $file) {
        Copy-Item $file -Destination $PackageDir
    }
}

# Copy directories
$directories = @("assets", "flags", "languages", "src", "templates")
foreach ($dir in $directories) {
    if (Test-Path $dir) {
        Copy-Item $dir -Destination $PackageDir -Recurse
    }
}

# Run composer and Strauss
Set-Location "$PackageDir\src\lib"
& composer install --no-dev --no-interaction
Invoke-WebRequest -Uri "https://github.com/BrianHenryIE/strauss/releases/download/0.19.4/strauss.phar" -OutFile strauss.phar
& php strauss.phar

# Cleanup and validation
Remove-Item -Recurse -Force vendor\twig, vendor\monolog -ErrorAction SilentlyContinue
```

## CI/CD Integration

### GitHub Actions Workflow Integration

The packaging process is integrated into CI/CD pipelines:

1. **Build Step Addition**:
   ```yaml
   - name: Build Plugin Package
     run: |
       ./bin/build-package.sh ./wp-simple-firewall
       echo "Package built successfully"
   ```

2. **Test Execution on Package**:
   ```yaml
   - name: Run Tests on Package
     run: |
       cd ./wp-simple-firewall
       phpunit --configuration phpunit.xml
   ```

3. **Artifact Upload**:
   ```yaml
   - name: Upload Package Artifact
     uses: actions/upload-artifact@v3
     with:
       name: shield-security-package
       path: ./wp-simple-firewall
   ```

### Docker Test Environment

Tests run against the packaged plugin structure:
- Plugin installed in `/var/www/html/wp-content/plugins/wp-simple-firewall/`
- Same directory name as WordPress.org distribution
- Ensures test environment matches production

## Version Management

### Version Tagging Process

1. **Update Version Numbers**:
   - `icwp-wpsf.php` header
   - `plugin.json` version field
   - `readme.txt` stable tag

2. **Create Distribution Package**:
   ```bash
   ./bin/build-package.sh ./dist/wp-simple-firewall-21.0.7
   ```

3. **Create Release Archive**:
   ```bash
   cd ./dist
   zip -r wp-simple-firewall-21.0.7.zip wp-simple-firewall-21.0.7/
   ```

4. **Tag in Git**:
   ```bash
   git tag -a 21.0.7 -m "Release version 21.0.7"
   git push origin 21.0.7
   ```

### WordPress.org SVN Deployment

The package structure must exactly match SVN requirements:
- Use `svn list` to verify structure
- Compare against production distribution
- Ensure no extra files included

## Testing Methodology

### Package Testing Checklist

1. **Pre-Build Verification**:
   - [ ] All source files present
   - [ ] Composer dependencies updated
   - [ ] Version numbers consistent

2. **Build Process**:
   - [ ] Strauss completes without errors
   - [ ] vendor_prefixed directory created
   - [ ] Duplicates removed from vendor/
   - [ ] Autoload files pruned

3. **Post-Build Validation**:
   - [ ] All 14 components present
   - [ ] No development files included
   - [ ] Package activates in WordPress
   - [ ] No namespace conflicts
   - [ ] Features work as expected

4. **Distribution Testing**:
   - [ ] Install from zip file
   - [ ] Upgrade from previous version
   - [ ] No file permission issues
   - [ ] Compatible with standard WordPress setup

## Lessons Learned

### Key Insights from Implementation

1. **WordPress Directory Standards Matter**
   - Plugin directory must be `wp-simple-firewall` (not `shield-security`)
   - Exact structure matching prevents activation issues

2. **Strauss Version Consistency**
   - Always use same version (0.19.4) across environments
   - Different versions may produce different output

3. **Autoload Pruning is Critical**
   - Leaving references to deleted packages causes fatal errors
   - Must clean all autoload files, not just some

4. **Test on Packaged Code**
   - Development structure differs from distribution
   - CI/CD must test the actual package, not source

5. **Validation at Every Step**
   - Build scripts need comprehensive error checking
   - Silent failures lead to broken distributions

6. **Documentation is Essential**
   - Complex packaging process needs clear documentation
   - Future maintainers need to understand the "why"

### Evolution of Understanding

The packaging system evolved through multiple iterations:

1. **Initial State**: Basic file copying, no prefixing
2. **Strauss Integration**: Added namespace prefixing
3. **Duplicate Resolution**: Discovered and fixed vendor duplication
4. **Autoload Cleanup**: Added pruning step
5. **CI/CD Integration**: Automated entire process
6. **Validation Addition**: Added comprehensive checks

Each iteration solved specific problems discovered in production or testing.

## Troubleshooting Guide

### Debug Commands

```bash
# Check package structure
find ./package -type f -name "*.php" | head -20

# Verify prefixing applied
grep -r "namespace AptowebDeps" ./package/src/lib/vendor_prefixed/

# Check for duplicates
ls -la ./package/src/lib/vendor/ | grep -E "twig|monolog"

# Validate autoload
php -r "require './package/src/lib/vendor/autoload.php'; echo 'Autoload OK';"

# Test plugin activation
wp plugin activate wp-simple-firewall --path=/path/to/wordpress
```

### Common Error Messages

**"Class X already declared"**
- Duplicate libraries not properly removed
- Check vendor/ and vendor_prefixed/ for duplicates

**"Class AptowebDeps\X not found"**
- Strauss prefixing failed or incomplete
- Verify vendor_prefixed/ exists and contains expected files

**"Failed to open stream: No such file or directory"**
- Missing files in package
- Compare package contents against requirements

**"Plugin could not be activated"**
- Structure doesn't match WordPress expectations
- Verify directory name is wp-simple-firewall

## Conclusion

The Shield Security packaging system represents a sophisticated solution to complex WordPress plugin distribution challenges. Through careful use of Strauss prefixing, intelligent file management, and comprehensive validation, the system ensures reliable, conflict-free plugin deployment across diverse WordPress environments.

The journey from source code to distributable package involves multiple transformations, each addressing specific technical challenges. This documentation captures the complete understanding developed through extensive testing and refinement, providing a roadmap for maintaining and evolving the packaging system.

---

*This document consolidates learnings from sessions on 2025-01-22 and 2025-01-23, representing the complete packaging system knowledge for Shield Security for WordPress.*