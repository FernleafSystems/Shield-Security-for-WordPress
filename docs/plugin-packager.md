# Shield Plugin Packager

This document provides a complete reference for the Shield plugin packaging system, which creates distributable plugin packages for WordPress.org SVN, direct downloads, or custom deployments.

## Overview

The plugin packager automates the complex process of building a production-ready WordPress plugin package. It handles:

- Composer dependency installation (root and library)
- NPM asset building
- Vendor namespace prefixing via Strauss
- Development file cleanup
- Plugin configuration generation
- Version metadata updates
- Package verification

## Quick Start

```bash
# Build a zip file (most common use case)
composer build-zip

# Build a zip with specific version
composer build-zip -- --version=21.0.102

# Build to a directory (for SVN deployment)
composer package-plugin -- --output=/path/to/svn/trunk
```

---

## CLI Commands

### build-zip

Creates a complete distributable zip archive of the plugin.

**Location:** `bin/build-zip.php`

**Composer Script:**
```bash
composer build-zip -- [options]
```

**Synopsis:**
```bash
php bin/build-zip.php [--output=<path>] [--version=<version>] [--build=<build>] [--release-timestamp=<timestamp>] [--zip-root-folder=<name>] [--keep-package] [--skip-*]
```

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--output` | string | `builds/wp-simple-firewall-{timestamp}.zip` | Output zip file path |
| `--zip-root-folder` | string | `wp-simple-firewall` | Folder name inside the zip archive |
| `--version` | string | - | Set plugin version (e.g., `21.0.102`) |
| `--release-timestamp` | integer | auto-generated | Unix timestamp for release (auto-set when `--version` provided) |
| `--build` | string | - | Build identifier (`YYYYMM.DDBB` format or `auto`) |
| `--keep-package` | flag | - | Keep intermediate package directory after zip creation |
| `--strauss-version` | string | config/env | Strauss version to use |
| `--strauss-fork-repo` | string | - | GitHub repo URL for Strauss fork |
| `--skip-root-composer` | flag | - | Skip root composer install |
| `--skip-lib-composer` | flag | - | Skip src/lib composer install |
| `--skip-npm-install` | flag | - | Skip npm ci |
| `--skip-npm-build` | flag | - | Skip npm run build |

**Examples:**

```bash
# Build with default settings (outputs to builds/ directory)
composer build-zip

# Build with specific output path
composer build-zip -- --output=/releases/shield-21.0.102.zip

# Build a release version with auto-generated timestamp
composer build-zip -- --version=21.0.102

# Build with explicit version, timestamp, and auto-incrementing build number
composer build-zip -- --version=21.0.102 --build=auto

# Build with all version metadata explicit
composer build-zip -- --version=21.0.102 --release-timestamp=1765370000 --build=202602.0301

# Build and keep intermediate files for inspection
composer build-zip -- --version=21.0.102 --keep-package

# Fast rebuild (skip dependency installation)
composer build-zip -- --skip-root-composer --skip-lib-composer --skip-npm-install --skip-npm-build

# Custom zip folder name (for rebranded distributions)
composer build-zip -- --zip-root-folder=my-custom-plugin
```

---

### package-plugin

Creates a plugin package directory (without zipping). Used for SVN deployment or custom workflows.

**Location:** `bin/package-plugin.php`

**Composer Script:**
```bash
composer package-plugin -- [options]
```

**Synopsis:**
```bash
php bin/package-plugin.php --output=<path> [--version=<version>] [--build=<build>] [--release-timestamp=<timestamp>] [--skip-*]
```

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--output` | string | **required** | Output directory path |
| `--version` | string | - | Set plugin version (e.g., `21.0.102`) |
| `--release-timestamp` | integer | auto-generated | Unix timestamp for release |
| `--build` | string | - | Build identifier (`YYYYMM.DDBB` format or `auto`) |
| `--strauss-version` | string | config/env | Strauss version to use |
| `--strauss-fork-repo` | string | - | GitHub repo URL for Strauss fork |
| `--skip-root-composer` | flag | - | Skip root composer install |
| `--skip-lib-composer` | flag | - | Skip src/lib composer install |
| `--skip-npm-install` | flag | - | Skip npm ci |
| `--skip-npm-build` | flag | - | Skip npm run build |
| `--skip-directory-clean` | flag | - | Don't clean existing output directory |
| `--skip-copy` | flag | - | Skip file copy (for git archive workflows) |

**Examples:**

```bash
# Package to SVN trunk directory
composer package-plugin -- --output=/path/to/wordpress-plugin-svn/trunk

# Package with version for release
composer package-plugin -- --output=./dist --version=21.0.102

# Package to existing directory without cleaning
composer package-plugin -- --output=./dist --skip-directory-clean

# Fast rebuild for testing
composer package-plugin -- --output=./dist --skip-root-composer --skip-lib-composer --skip-npm-install --skip-npm-build
```

---

## Version Metadata

The packager can update version information across multiple files during the build process. This is particularly useful for CI/CD pipelines where version numbers are determined at build time.

### Files Updated

When version options are provided, the following files are updated in the **target package** (not source):

| Option | File | Field/Pattern |
|--------|------|---------------|
| `--version` | `plugin.json` | `properties.version` |
| `--version` | `readme.txt` | `Stable tag: X.X.X` |
| `--version` | `icwp-wpsf.php` | `* Version: X.X.X` |
| `--release-timestamp` | `plugin.json` | `properties.release_timestamp` |
| `--build` | `plugin.json` | `properties.build` |

### Version Format

Versions must be numeric segments separated by dots:

```
✓ Valid:   1.0, 21.0.102, 1.2.3.4, 0.1
✗ Invalid: v1.0, 1.0-beta, 1.0.0-rc1, abc
```

### Build Number Format

Build numbers follow the `YYYYMM.DDBB` format:

- `YYYY` - 4-digit year
- `MM` - 2-digit month (01-12)
- `DD` - 2-digit day (01-31)
- `BB` - 2-digit iteration (01-99)

**Example:** `202602.0301` = 2026, February, 3rd day, 1st build of the day

### Auto-Increment Build Numbers

Use `--build=auto` to automatically generate and increment build numbers:

```bash
composer build-zip -- --version=21.0.102 --build=auto
```

**Logic:**
1. Reads current build from source `plugin-spec/01_properties.json`
2. If same day (YYYYMM.DD matches), increments BB
3. If different day, resets to `YYYYMM.DD01`

**Example progression:**
```
Source: 202602.0301 → Generated: 202602.0302 (same day, increment)
Source: 202602.0301 → Generated: 202602.0401 (next day, reset)
Source: 202601.1599 → Generated: 202602.0301 (new month, reset)
```

### Timestamp Behavior

- When `--version` is provided without `--release-timestamp`, timestamp auto-generates to current `time()`
- When `--release-timestamp` is explicitly provided, that value is used
- Timestamp must be a positive integer after year 2000 (>= 946684800)

---

## Workflow Examples

### Standard Release Build

```bash
# 1. Update version in source files (optional - packager can do this)
# 2. Build the release
composer build-zip -- --version=21.0.102 --build=auto

# Output: builds/wp-simple-firewall-20260203-143052.zip
# Contains: wp-simple-firewall/ folder with all plugin files
```

### WordPress.org SVN Deployment

```bash
# 1. Checkout SVN repository
svn checkout https://plugins.svn.wordpress.org/wp-simple-firewall wporg-svn
cd wporg-svn

# 2. Package directly to trunk
composer package-plugin -- --output=./trunk --version=21.0.102

# 3. Create tag
svn cp trunk tags/21.0.102

# 4. Commit
svn commit -m "Release 21.0.102"
```

### CI/CD Pipeline (GitHub Actions)

```yaml
- name: Build Release
  run: |
    composer build-zip -- \
      --version=${{ github.event.release.tag_name }} \
      --build=auto \
      --output=./dist/wp-simple-firewall-${{ github.event.release.tag_name }}.zip

- name: Upload Release Asset
  uses: actions/upload-release-asset@v1
  with:
    upload_url: ${{ github.event.release.upload_url }}
    asset_path: ./dist/wp-simple-firewall-${{ github.event.release.tag_name }}.zip
    asset_name: wp-simple-firewall-${{ github.event.release.tag_name }}.zip
    asset_content_type: application/zip
```

### Development Testing

```bash
# Quick rebuild without reinstalling dependencies
composer build-zip -- \
  --skip-root-composer \
  --skip-lib-composer \
  --skip-npm-install \
  --skip-npm-build \
  --keep-package

# Inspect the intermediate package
ls -la /tmp/shield-build-*/
```

### Custom Strauss Version

```bash
# Use specific Strauss version
composer build-zip -- --strauss-version=0.22.0

# Use Strauss fork (for testing fixes)
composer build-zip -- --strauss-fork-repo=https://github.com/user/strauss-fork
```

---

## Build Process

The packager executes these steps in order:

1. **Directory Setup**
   - Resolve output directory
   - Clean existing directory (unless `--skip-directory-clean`)

2. **Dependency Installation**
   - `composer install` in project root (unless `--skip-root-composer`)
   - `composer install` in `src/lib` (unless `--skip-lib-composer`)
   - `npm ci` (unless `--skip-npm-install`)
   - `npm run build` (unless `--skip-npm-build`)

3. **File Copy**
   - Copy plugin files to target directory (unless `--skip-copy`)
   - Respects `.gitattributes` export-ignore patterns

4. **Configuration Generation**
   - Merge `plugin-spec/*.json` files into `plugin.json`

5. **Version Metadata Update** (if options provided)
   - Update `plugin.json`, `readme.txt`, `icwp-wpsf.php`

6. **Production Dependencies**
   - `composer install --no-dev` in target `src/lib`

7. **Vendor Cleanup**
   - Remove test directories, documentation, CI configs
   - Clean both `vendor` and `vendor_prefixed`

8. **Strauss Prefixing**
   - Download/cache Strauss binary
   - Run namespace prefixing
   - Clean duplicate libraries

9. **Autoload Cleanup**
   - Remove Twig references from autoload files
   - Clean up Strauss artifacts

10. **Verification**
    - Check required files exist
    - Validate package structure

11. **Zip Creation** (build-zip only)
    - Create zip archive with specified root folder
    - Clean up temp directory (unless `--keep-package`)

---

## Configuration

### Environment Variables

| Variable | Description |
|----------|-------------|
| `SHIELD_STRAUSS_VERSION` | Default Strauss version |
| `SHIELD_STRAUSS_FORK_REPO` | Default Strauss fork repository |

### Config File

Create `tests/Helpers/packager-config.json`:

```json
{
    "strauss_version": "0.22.0",
    "strauss_fork_repo": "https://github.com/user/strauss-fork"
}
```

**Priority:** CLI arguments > Environment variables > Config file > Defaults

---

## Troubleshooting

### Common Issues

**"Output directory is required"**
```bash
# package-plugin requires --output
composer package-plugin -- --output=./dist
```

**"Invalid version format"**
```bash
# Version must be numeric segments with dots
✗ composer build-zip -- --version=v1.0      # No 'v' prefix
✗ composer build-zip -- --version=1.0-beta  # No suffixes
✓ composer build-zip -- --version=1.0
✓ composer build-zip -- --version=21.0.102
```

**"Invalid build format"**
```bash
# Build must be YYYYMM.DDBB format
✗ composer build-zip -- --build=2026-02-03  # Wrong format
✗ composer build-zip -- --build=20260203    # Missing dot
✓ composer build-zip -- --build=202602.0301
✓ composer build-zip -- --build=auto
```

**"plugin.json not found"**
- Ensure `plugin-spec/` directory exists with all JSON files
- Check that file copy completed successfully

**Strauss download failures**
- Check network connectivity
- Try specifying a different Strauss version
- Use `--strauss-fork-repo` if main releases have issues

### Debug Mode

Keep intermediate files for inspection:

```bash
composer build-zip -- --keep-package
# Check /tmp/shield-build-* directory
```

---

## Architecture

### Key Classes

| Class | Responsibility |
|-------|----------------|
| `PluginPackager` | Main orchestrator |
| `VersionUpdater` | Version metadata updates |
| `PluginFileCopier` | File copying with .gitattributes support |
| `VendorCleaner` | Development file removal |
| `StraussBinaryProvider` | Strauss download and execution |
| `CommandRunner` | Shell command execution |
| `SafeDirectoryRemover` | Safe directory deletion |
| `ConfigMerger` | plugin.json generation |

### File Locations

```
bin/
├── build-zip.php           # Zip build script
├── package-plugin.php      # Directory package script
└── run-docker-tests.sh     # Docker test runner

infrastructure/src/Tooling/
├── ConfigMerger.php        # JSON config merger
└── PluginPackager/
    ├── PluginPackager.php      # Main packager
    ├── VersionUpdater.php      # Version metadata
    ├── PluginFileCopier.php    # File operations
    ├── VendorCleaner.php       # Cleanup logic
    ├── StraussBinaryProvider.php
    ├── CommandRunner.php
    ├── SafeDirectoryRemover.php
    └── FileSystemUtils.php

plugin-spec/                # Source JSON files for plugin.json
├── 01_properties.json
├── 02_requirements.json
└── ...
```

---

## Related Documentation

- [CLAUDE.md](../CLAUDE.md) - Development guidelines and testing commands
- [WP-CLI Commands](wp-cli-commands.md) - Runtime plugin commands
