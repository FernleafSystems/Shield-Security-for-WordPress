# Comprehensive Plan: .gitattributes export-ignore as Single Source of Truth

## Document Information

- **Created**: 2025-01-12
- **Purpose**: Eliminate duplicate file lists in package building by using `.gitattributes` `export-ignore` as the single source of truth
- **Scope**: `.gitattributes`, `bin/run-docker-tests.sh`, `infrastructure/src/Tooling/PluginPackager.php`, `bin/package-plugin.php`
- **Note**: This plan document is in `/docs/` which is excluded from packages via `export-ignore`. This is intentional - development documentation is not needed in production packages.

---

## 1. Problem Statement

### Current State

The list of files to include in plugin packages is maintained in **two separate locations**:

1. **`infrastructure/src/Tooling/PluginPackager.php`** (lines 316-326 for root files, lines 356-362 for directories):
   - Root files (line 316-326): `icwp-wpsf.php`, `plugin_init.php`, `readme.txt`, `plugin.json`, `cl.json`, `plugin_autoload.php`, `plugin_compatibility.php`, `uninstall.php`, `unsupported.php`
   - Directories (lines 356-362): `src`, `assets`, `flags`, `languages`, `templates`

2. **`bin/run-docker-tests.sh`** (line 116):
   - Calls `composer package-plugin` which uses the PluginPackager lists

### Problems

1. **DRY Violation**: Same information maintained in multiple places
2. **Maintenance Burden**: Adding/removing files requires changes in multiple locations
3. **Sync Risk**: Lists can drift out of sync, causing inconsistent packages
4. **No Standard**: Uses custom PHP logic instead of git's built-in `export-ignore` feature

### Solution

Use `.gitattributes` `export-ignore` attribute as the **single source of truth**. This is git's designed mechanism for controlling what files are included in archives.

---

## 2. Files Inventory

### 2.1 Files Currently Tracked in Git (Requiring export-ignore)

| Path | Type | Reason to Exclude |
|------|------|-------------------|
| `/.agent-os/` | Directory | AI agent documentation |
| `/.backup/` | Directory | Backup files |
| `/.github/` | Directory | CI/CD workflows |
| `/.gitattributes` | File | Git configuration |
| `/.gitignore` | File | Git configuration |
| `/.phpcs.xml.dist` | File | Code style config |
| `/.travis.yml` | File | Legacy CI config |
| `/AGENTS.md` | File | AI agent documentation |
| `/CLAUDE.md` | File | AI assistant documentation |
| `/README.md` | File | GitHub readme (readme.txt is for WordPress.org) |
| `/TESTING.md` | File | Testing documentation |
| `/bin/` | Directory | Development/build scripts |
| `/changelog.md` | File | Development changelog |
| `/composer.json` | File | Dev dependency management |
| `/composer.lock` | File | Dev dependency lock |
| `/docs/` | Directory | Documentation |
| `/infrastructure/` | Directory | Build tooling |
| `/package.json` | File | npm configuration |
| `/package-lock.json` | File | npm lock file |
| `/patchwork.json` | File | Testing configuration |
| `/phpunit.xml` | File | PHPUnit config |
| `/phpunit.xml.bak` | File | PHPUnit config backup |
| `/phpunit-integration.xml` | File | Integration test config |
| `/phpunit-unit.xml` | File | Unit test config |
| `/scripts/` | Directory | Development scripts |
| `/tests/` | Directory | Test files |
| `/tmp/` | Directory | Temporary files |
| `/translations-tools/` | Directory | Translation development tools |
| `/webpack.config.js` | File | Webpack build config |
| `/assets/SASS_MODERNIZATION.md` | File | Development documentation in assets |
| `/languages/*.po` | Files | Translation source files (not needed at runtime) |
| `/languages/*.pot` | File | Translation template (not needed at runtime) |

### 2.2 Files That MUST Be in Package

| Path | Type | Purpose |
|------|------|---------|
| `/icwp-wpsf.php` | File | Main plugin file |
| `/plugin_init.php` | File | Plugin initialization |
| `/plugin.json` | File | Plugin configuration |
| `/cl.json` | File | Changelog data |
| `/plugin_autoload.php` | File | Autoloader |
| `/plugin_compatibility.php` | File | Compatibility checks |
| `/readme.txt` | File | WordPress.org readme |
| `/uninstall.php` | File | Uninstall handler |
| `/unsupported.php` | File | Unsupported PHP handler |
| `/src/` | Directory | Plugin source code |
| `/assets/` | Directory | CSS, JS, images (note: assets/dist is gitignored) |
| `/flags/` | Directory | Country flag images |
| `/languages/*.mo` | Files | Compiled translation files |
| `/templates/` | Directory | Twig templates |

### 2.3 Files Handled Specially

| Path | Handling | Reason |
|------|----------|--------|
| `/assets/dist/` | Gitignored but copied by `copyPluginFiles()` | Built by npm before packaging, needed at runtime |
| `/assets/dist/` (shell script) | Copied separately after `git archive` | `git archive` only includes tracked files |
| `/src/lib/vendor/` | Created during packaging | Composer install in package |
| `/src/lib/vendor_prefixed/` | Created during packaging | Strauss prefixing |
| `/src/lib/strauss.phar` | Downloaded during packaging | Strauss tool |

**Important**: `assets/dist/` handling differs by method:
- **`git archive` (shell script)**: Doesn't include `assets/dist/` (gitignored), so shell script copies it separately
- **`copyPluginFiles()` (PHP)**: Includes `assets/dist/` if it exists on disk (iterates filesystem, not git)

---

## 3. Implementation Details

### 3.1 Phase 1: Update .gitattributes

**File**: `.gitattributes`
**Current Lines**: 96
**Action**: Append export-ignore rules after line 96

**Content to Add**:

```gitattributes
# =============================================================================
# Package Export Rules (git archive exclusions)
# =============================================================================
# Files and directories marked with export-ignore are excluded from:
# - git archive output (used by build scripts)
# - Plugin packages (PluginPackager reads these rules)
# - GitHub release downloads
#
# To include a file in packages, ensure it is NOT listed here.
# To exclude a file from packages, add: /path export-ignore
#
# NOTE: Some files are created DURING packaging and cleaned up after:
# - vendor/twig, vendor/monolog, vendor/bin (created by composer, cleaned post-Strauss)
# - strauss.phar (downloaded then removed)
# - vendor_prefixed/autoload-files.php (created by Strauss, then removed)
# These are handled by PluginPackager cleanup methods, not export-ignore.

# AI/Agent documentation
/.agent-os export-ignore
/AGENTS.md export-ignore
/CLAUDE.md export-ignore

# Backup files
/.backup export-ignore

# CI/CD and version control
/.github export-ignore
/.gitattributes export-ignore
/.gitignore export-ignore
/.travis.yml export-ignore

# Code quality tools
/.phpcs.xml.dist export-ignore

# Build and development scripts
/bin export-ignore
/scripts export-ignore
/infrastructure export-ignore

# Documentation (readme.txt is kept for WordPress.org)
/docs export-ignore
/README.md export-ignore
/TESTING.md export-ignore
/changelog.md export-ignore

# Dependency management (runtime deps installed separately in package)
/composer.json export-ignore
/composer.lock export-ignore
/package.json export-ignore
/package-lock.json export-ignore
/webpack.config.js export-ignore

# Testing
/tests export-ignore
/patchwork.json export-ignore
/phpunit.xml export-ignore
/phpunit.xml.bak export-ignore
/phpunit-integration.xml export-ignore
/phpunit-unit.xml export-ignore

# Development tools
/translations-tools export-ignore

# Development documentation inside included directories
/assets/SASS_MODERNIZATION.md export-ignore

# Temporary files
/tmp export-ignore

# Translation source files (only compiled .mo files needed at runtime)
# .po files are source files for translators
# .pot file is the template
# .mo files are compiled binaries (KEPT - these are what WordPress uses)
/languages/*.po export-ignore
/languages/*.pot export-ignore
```

### 3.2 Phase 2: Update PluginPackager.php

**File**: `infrastructure/src/Tooling/PluginPackager.php`

**⚠️ CODING STYLE NOTE**: The PHP code examples in this plan use simplified formatting for readability. When implementing, convert to the project's WordPress Coding Standards:
- Use **tabs** for indentation (not spaces)
- Add spaces inside parentheses: `function( $param )` not `function($param)`
- Add spaces around parameters: `( string $targetDir )` not `(string $targetDir)`
- Return type syntax: `) :void` not `): void`

Example conversion:
- Plan: `private function getExportIgnorePatterns(): array {`
- Actual: `private function getExportIgnorePatterns() :array {`

#### 3.2.1 Add New Methods (insert after line 253, after `resolveOptions()`)

**Note**: The `resolveOptions()` method spans lines 243-253. Line 253 is the closing brace `}`. Line 254 is blank. Line 255 starts `getComposerCommand()`. New methods should be inserted between lines 253 and 255.

**Symfony Version Requirement**: The new methods use `Path::makeRelative()` which is available in Symfony Filesystem 5.4+. The project's `composer.json` (line 32) already requires `"symfony/filesystem": "^5.4|^6.0|^7.0"` so this is compatible.

**Method 1: getExportIgnorePatterns()**

Purpose: Parse .gitattributes and extract all export-ignore patterns

```php
/**
 * Parse .gitattributes and return list of export-ignore patterns
 * This makes .gitattributes the single source of truth for package contents
 * @return string[] Array of patterns that should be excluded from packages
 */
private function getExportIgnorePatterns(): array {
    $gitattributesPath = Path::join($this->projectRoot, '.gitattributes');
    
    if (!file_exists($gitattributesPath)) {
        $this->log('Warning: .gitattributes not found, no export-ignore patterns loaded');
        return [];
    }
    
    $patterns = [];
    $lines = file($gitattributesPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if ($lines === false) {
        $this->log('Warning: Could not read .gitattributes');
        return [];
    }
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments and empty lines
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        
        // Match lines with export-ignore attribute
        // Formats supported:
        //   /path export-ignore
        //   /path/to/dir export-ignore
        //   /languages/*.po export-ignore
        if (preg_match('/^(\S+)\s+.*\bexport-ignore\b/', $line, $matches)) {
            $pattern = $matches[1];
            // Normalize: remove leading slash for internal consistency
            $patterns[] = ltrim($pattern, '/');
        }
    }
    
    return $patterns;
}
```

**Method 2: shouldExcludePath()**

Purpose: Check if a given path should be excluded based on export-ignore patterns

```php
/**
 * Check if a path should be excluded based on export-ignore patterns
 * Supports:
 *   - Exact file matches: "README.md"
 *   - Directory matches: "tests" matches "tests/Unit/Test.php"
 *   - Glob patterns: "languages/*.po" matches "languages/en_US.po"
 *
 * NOTE: Matching is case-sensitive (consistent with git's behavior).
 * Git tracks paths exactly as committed, so case mismatches are rare.
 *
 * @param string $relativePath Path relative to project root (forward slashes)
 * @param string[] $patterns Export-ignore patterns from .gitattributes
 * @return bool True if path should be excluded
 */
private function shouldExcludePath(string $relativePath, array $patterns): bool {
    // Normalize to forward slashes (simpler than Path::normalize() for this use case)
    // We only need to handle backslash->forward slash conversion, not ../ resolution
    $normalizedPath = str_replace('\\', '/', $relativePath);
    
    foreach ($patterns as $pattern) {
        // Normalize pattern
        $normalizedPattern = str_replace('\\', '/', $pattern);
        
        // Case 1: Exact match
        if ($normalizedPath === $normalizedPattern) {
            return true;
        }
        
        // Case 2: Directory match - pattern is a directory prefix
        // Pattern "tests" should match "tests/Unit/Test.php"
        $patternAsDir = rtrim($normalizedPattern, '/') . '/';
        if (strpos($normalizedPath, $patternAsDir) === 0) {
            return true;
        }
        
        // Case 3: Pattern ends with directory name, path is exactly that directory
        if ($normalizedPath === rtrim($normalizedPattern, '/')) {
            return true;
        }
        
        // Case 4: Glob pattern with asterisk (e.g., "languages/*.po")
        if (strpos($normalizedPattern, '*') !== false) {
            // Convert glob pattern to regex
            // Escape regex special chars except *
            $regexPattern = preg_quote($normalizedPattern, '#');
            // Replace escaped \* with regex .*
            $regexPattern = str_replace('\\*', '[^/]*', $regexPattern);
            // Anchor the pattern
            $regexPattern = '#^' . $regexPattern . '$#';
            
            if (preg_match($regexPattern, $normalizedPath)) {
                return true;
            }
        }
    }
    
    return false;
}
```

**Method 3: isCommonGitIgnoredPath()**

Purpose: Check for paths that are typically gitignored (vendor, node_modules, etc.)

```php
/**
 * Check if a path is commonly gitignored and should NOT be copied
 * These paths exist on disk but should not be in the package because they are either:
 * - Development tools (node_modules/, vendor/, .idea/, .git/)
 * - Created DURING packaging (src/lib/vendor/, src/lib/vendor_prefixed/)
 *
 * NOTE: assets/dist/ is gitignored but SHOULD be copied (it's the built output)
 * So it is intentionally NOT in this list.
 *
 * @param string $relativePath Path relative to project root
 * @return bool True if path should be skipped (not copied)
 */
private function isCommonGitIgnoredPath(string $relativePath): bool {
    $gitIgnoredPrefixes = [
        'vendor/',                  // Root dev dependencies (composer install at root)
        'node_modules/',            // npm dependencies (build tools)
        '.idea/',                   // IDE settings
        '.git/',                    // Version control
        'src/lib/vendor/',          // Created by composer install during packaging
        'src/lib/vendor_prefixed/', // Created by Strauss during packaging
        // NOTE: assets/dist/ is NOT here because it SHOULD be copied
        // It's built by npm BEFORE packaging and needs to be in the package
    ];
    
    $normalized = str_replace('\\', '/', $relativePath);
    
    foreach ($gitIgnoredPrefixes as $prefix) {
        if (strpos($normalized, $prefix) === 0) {
            return true;
        }
        // Also check if path IS the directory itself (without trailing slash)
        if ($normalized === rtrim($prefix, '/')) {
            return true;
        }
    }
    
    return false;
}
```

#### 3.2.2 Replace copyPluginFiles() Method

**Location**: Lines 300-392 (docblock at lines 300-304, method at lines 305-392)
**Action**: Replace entire method including its docblock with new implementation (93 lines total)

```php
/**
 * Copy plugin files to the target package directory
 * Uses .gitattributes export-ignore patterns as single source of truth
 * @throws RuntimeException if copy operations fail
 */
private function copyPluginFiles(string $targetDir): void {
    $this->log('Copying plugin files...');
    $this->log('(Using .gitattributes export-ignore as source of truth)');
    
    $fs = new Filesystem();
    $excludePatterns = $this->getExportIgnorePatterns();
    
    $this->log(sprintf('  Loaded %d export-ignore patterns from .gitattributes', count($excludePatterns)));
    
    // Ensure target directory exists
    if (!is_dir($targetDir)) {
        $fs->mkdir($targetDir);
    }
    
    // Use filter iterator to avoid descending into excluded directories
    // This is much faster than iterating all files then skipping
    // Note: Since PHP 5.4, closures auto-bind $this when defined in class methods
    //
    // Symlink behavior: RecursiveDirectoryIterator follows symlinks by default.
    // If a symlink points outside the project, Path::makeRelative() may return
    // unexpected results. This is acceptable as symlinks in the source tree are rare.
    $projectRoot = $this->projectRoot;
    $dirIterator = new \RecursiveDirectoryIterator(
        $this->projectRoot,
        \RecursiveDirectoryIterator::SKIP_DOTS
    );
    
    // Filter out excluded directories BEFORE descending into them
    $filterIterator = new \RecursiveCallbackFilterIterator(
        $dirIterator,
        function (\SplFileInfo $current, string $key, \RecursiveDirectoryIterator $iterator) use ($projectRoot, $excludePatterns): bool {
            $relativePath = Path::makeRelative($current->getPathname(), $projectRoot);
            
            // Skip export-ignore paths
            if ($this->shouldExcludePath($relativePath, $excludePatterns)) {
                return false;
            }
            
            // Skip gitignored directories (prevents descending into vendor/, node_modules/)
            if ($this->isCommonGitIgnoredPath($relativePath)) {
                return false;
            }
            
            return true;
        }
    );
    
    $iterator = new \RecursiveIteratorIterator(
        $filterIterator,
        \RecursiveIteratorIterator::SELF_FIRST
    );
    
    $stats = ['files' => 0, 'dirs' => 0];
    
    foreach ($iterator as $item) {
        /** @var \SplFileInfo $item */
        $fullPath = $item->getPathname();
        $relativePath = Path::makeRelative($fullPath, $this->projectRoot);
        
        $targetPath = Path::join($targetDir, $relativePath);
        
        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                $fs->mkdir($targetPath);
                $stats['dirs']++;
            }
        } else {
            try {
                $fs->copy($fullPath, $targetPath, true);
                $stats['files']++;
            } catch (\Exception $e) {
                throw new RuntimeException(
                    sprintf('Failed to copy file "%s": %s', $relativePath, $e->getMessage())
                );
            }
        }
    }
    
    $this->log(sprintf('  ✓ Copied %d files in %d directories', $stats['files'], $stats['dirs']));
    $this->log('  (Excluded paths filtered via .gitattributes export-ignore)');
    $this->log('');
    $this->log('Package structure created successfully');
    $this->log('');
}
```

#### 3.2.3 Remove cleanPoFiles() Method

**Location**: Lines 676-692 (including docblock at lines 676-679, method at lines 680-692)
**Action**: Delete entire method including its docblock (17 lines total, from line 675 blank line through line 692)

**Reason**: `.po` and `.pot` files are now excluded via `export-ignore`, so they are never copied in the first place. The cleanup method is no longer needed.

**Note**: The existing docblock at lines 676-679 incorrectly says "Clean autoload files to remove twig references" but the method actually removes `.po` files. This is a documentation error in the source that will be eliminated when the method is removed.

#### 3.2.4 Remove cleanPoFiles() Call

**Location**: Line 87 (in `package()` method)
**Action**: Delete the line `$this->cleanPoFiles( $targetDir );`

**⚠️ EDIT ORDER WARNING**: When editing PluginPackager.php, apply changes in this order to maintain accurate line numbers:
1. **First**: Remove `cleanPoFiles()` method (Section 3.2.3) - lines 675-692
2. **Second**: Remove `cleanPoFiles()` call (Section 3.2.4) - line 87
3. **Third**: Add `skip_copy` condition (Section 3.4.2) - lines 81-82
4. **Fourth**: Update `resolveOptions()` (Section 3.4.1) - lines 243-253
5. **Fifth**: Replace `copyPluginFiles()` method (Section 3.2.2) - lines 300-392
6. **Last**: Add new helper methods (Section 3.2.1) - after line 253

Alternatively, work from bottom to top of the file to avoid line number shifts affecting subsequent edits.

**Before** (lines 85-89):
```php
$this->runStraussPrefixing( $targetDir );
$this->cleanupPackageFiles( $targetDir );
$this->cleanPoFiles( $targetDir );
$this->cleanAutoloadFiles( $targetDir );
$this->verifyPackage( $targetDir );
```

**After** (lines 85-88):
```php
$this->runStraussPrefixing( $targetDir );
$this->cleanupPackageFiles( $targetDir );
$this->cleanAutoloadFiles( $targetDir );
$this->verifyPackage( $targetDir );
```

### 3.3 Phase 3: Update Shell Script for Docker-Based Building

**File**: `bin/run-docker-tests.sh`

This phase converts the shell script to use Docker for all build operations (no local PHP/npm required) and use `git archive` for fast file export.

> **CRITICAL NOTE ON LINE NUMBERS**: All line numbers in this section refer to the **ORIGINAL** file before any modifications. When implementing, either:
> 1. Apply changes from bottom to top (highest line numbers first) so line numbers don't shift, OR
> 2. Replace the entire file content at once
>
> Applying from bottom to top order: 3.3.5 → 3.3.4 → 3.3.3 → 3.3.2 → 3.3.1

#### 3.3.1 Add Docker Availability Check and MSYS Fix

**Location**: After line 5 (`set -e`)
**Current structure**:
- Line 5: `set -e`
- Line 6: (blank line)
- Line 7: `echo "🚀 Starting Local Docker Tests..."`

**Action**: Insert Docker validation block and MSYS path fix between line 5 and line 7. This will push all subsequent line numbers down by approximately 22 lines.

**Why MSYS_NO_PATHCONV**: On Windows Git Bash, the MSYS layer converts Unix-style paths like `/app` to Windows paths like `C:/Program Files/Git/app`. Setting `MSYS_NO_PATHCONV=1` prevents this, which is essential for Docker volume mounts to work correctly.

```bash
# Verify Docker is available (required for all operations)
if ! command -v docker >/dev/null 2>&1; then
    echo "❌ Error: Docker is required but not found"
    echo ""
    echo "   This script uses Docker for all build and test operations."
    echo "   Please install Docker Desktop from https://www.docker.com/products/docker-desktop"
    echo ""
    exit 1
fi

# Verify Docker daemon is running
if ! docker info >/dev/null 2>&1; then
    echo "❌ Error: Docker is installed but not running"
    echo ""
    echo "   Please start Docker Desktop and try again."
    echo ""
    exit 1
fi

# Disable MSYS/Git Bash path conversion on Windows
# Prevents /app from being converted to C:/Program Files/Git/app
export MSYS_NO_PATHCONV=1
```

#### 3.3.2 Update Package Directory Configuration

**Location**: Lines 29-33
**Action**: Replace with project-relative path for Docker compatibility

**Before**:
```bash
# Set environment variables early to avoid Docker Compose warnings
PACKAGE_DIR="/tmp/shield-package-local"
export SHIELD_PACKAGE_PATH="$PACKAGE_DIR"
# PLUGIN_SOURCE needs to be the actual path, not just "package"
export PLUGIN_SOURCE="$PACKAGE_DIR"
```

**After**:
```bash
# Set environment variables early to avoid Docker Compose warnings
# Use directory inside project so Docker can mount it on all platforms
# Note: tmp/ directory exists and is already in .gitignore
PACKAGE_DIR="$PROJECT_ROOT/tmp/shield-package-local"
export SHIELD_PACKAGE_PATH="$PACKAGE_DIR"
export PLUGIN_SOURCE="$PACKAGE_DIR"

# Relative path for use inside Docker container
# Docker mounts: -v "$PROJECT_ROOT:/app" 
# So inside container: /app/tmp/shield-package-local = $PROJECT_ROOT/tmp/shield-package-local on host
# We pass relative path to avoid absolute path issues across host/container
PACKAGE_DIR_RELATIVE="tmp/shield-package-local"
```

#### 3.3.3 Replace npm Build Section with Docker

**Location**: Lines 45-101 (the `if command -v npm` section)
**Action**: Replace with Docker-based npm build with caching

```bash
# Build assets using Docker (no local Node.js required) - with caching
echo "🔨 Building assets..."
DIST_DIR="$PROJECT_ROOT/assets/dist"
SRC_DIR="$PROJECT_ROOT/assets/js"
CACHE_FILE="$PROJECT_ROOT/tmp/.shield-webpack-cache-checksum"

# Ensure tmp directory exists
mkdir -p "$PROJECT_ROOT/tmp"

# Check if webpack build cache is valid
WEBPACK_CACHE_VALID=false
COMBINED_CHECKSUM=""

if [ -d "$DIST_DIR" ] && [ "$(ls -A $DIST_DIR 2>/dev/null)" ]; then
    echo "   Checking webpack build cache..."
    
    CURRENT_CHECKSUM=$(find "$SRC_DIR" -type f \( -name "*.js" -o -name "*.jsx" -o -name "*.ts" -o -name "*.tsx" \) -exec md5sum {} \; 2>/dev/null | sort | md5sum | cut -d' ' -f1)
    PACKAGE_CHECKSUM=$(md5sum "$PROJECT_ROOT/package.json" 2>/dev/null | cut -d' ' -f1)
    WEBPACK_CHECKSUM=$(md5sum "$PROJECT_ROOT/webpack.config.js" 2>/dev/null | cut -d' ' -f1)
    
    if [ -n "$CURRENT_CHECKSUM" ] && [ -n "$PACKAGE_CHECKSUM" ] && [ -n "$WEBPACK_CHECKSUM" ]; then
        COMBINED_CHECKSUM="${CURRENT_CHECKSUM}-${PACKAGE_CHECKSUM}-${WEBPACK_CHECKSUM}"
        
        if [ -f "$CACHE_FILE" ]; then
            STORED_CHECKSUM=$(cat "$CACHE_FILE" 2>/dev/null)
            if [ "$COMBINED_CHECKSUM" = "$STORED_CHECKSUM" ]; then
                if [ -f "$DIST_DIR/shield-main.bundle.js" ] && [ -f "$DIST_DIR/shield-main.bundle.css" ]; then
                    WEBPACK_CACHE_VALID=true
                    echo "   ✅ Cache valid - skipping rebuild"
                fi
            fi
        fi
    fi
fi

if [ "$WEBPACK_CACHE_VALID" = false ]; then
    echo "   Building assets via Docker..."
    docker run --rm \
        -v "$PROJECT_ROOT:/app" \
        -w /app \
        node:18 \
        sh -c "npm ci --no-audit --no-fund && npm run build" || {
        echo "❌ Asset build failed"
        exit 1
    }
    
    # Save checksum
    if [ -n "$COMBINED_CHECKSUM" ]; then
        echo "$COMBINED_CHECKSUM" > "$CACHE_FILE"
    fi
    echo "   ✅ Build complete"
fi
```

#### 3.3.4 Replace Composer Install with Docker

**Location**: Lines 103-110
**Action**: Replace with Docker-based composer

```bash
# Install dependencies using Docker (no local PHP/Composer required)
echo "📦 Installing dependencies..."

docker run --rm \
    -v "$PROJECT_ROOT:/app" \
    -w /app \
    composer:2 \
    composer install --no-interaction --prefer-dist --optimize-autoloader || {
    echo "❌ Root composer install failed"
    exit 1
}

if [ -d "$PROJECT_ROOT/src/lib" ]; then
    docker run --rm \
        -v "$PROJECT_ROOT:/app" \
        -w /app/src/lib \
        composer:2 \
        composer install --no-interaction --prefer-dist --optimize-autoloader || {
        echo "❌ src/lib composer install failed"
        exit 1
    }
fi

echo "   ✅ Dependencies installed"
```

#### 3.3.5 Replace Package Build with git archive + Docker

**Location**: Lines 112-116
**Action**: Replace with git archive approach

```bash
# Build plugin package
echo "📦 Building plugin package..."

# Clean and create package directory
rm -rf "$PACKAGE_DIR"
mkdir -p "$PACKAGE_DIR"

# Export tracked files using git archive (respects .gitattributes export-ignore)
# This is MUCH faster than PHP file-by-file copying
echo "   Exporting files via git archive..."
git archive HEAD | tar -x -C "$PACKAGE_DIR" || {
    echo "❌ git archive failed"
    exit 1
}

# Verify archive extraction produced expected files
if [ ! -f "$PACKAGE_DIR/icwp-wpsf.php" ]; then
    echo "❌ git archive extraction failed - main plugin file not found"
    exit 1
fi
echo "   ✅ Files exported (verified)"

# Copy built assets (gitignored but needed)
if [ -d "$PROJECT_ROOT/assets/dist" ]; then
    echo "   Copying built assets..."
    cp -r "$PROJECT_ROOT/assets/dist" "$PACKAGE_DIR/assets/dist" || {
        echo "❌ Failed to copy assets/dist"
        exit 1
    }
    # Verify expected bundle files exist
    if [ ! -f "$PACKAGE_DIR/assets/dist/shield-main.bundle.js" ] || \
       [ ! -f "$PACKAGE_DIR/assets/dist/shield-main.bundle.css" ]; then
        echo "⚠️  Warning: assets/dist copied but expected bundle files not found"
        echo "   Run 'npm run build' first to generate assets"
    fi
    echo "   ✅ Assets copied"
fi

# Validate PACKAGE_DIR_RELATIVE is set (defined in section 3.3.2)
if [ -z "$PACKAGE_DIR_RELATIVE" ]; then
    echo "❌ Error: PACKAGE_DIR_RELATIVE not set"
    exit 1
fi

# Run Strauss and post-processing via Docker
echo "   Running Strauss prefixing..."
docker run --rm \
    -v "$PROJECT_ROOT:/app" \
    -w /app \
    -e COMPOSER_PROCESS_TIMEOUT=900 \
    composer:2 \
    composer package-plugin -- --output="$PACKAGE_DIR_RELATIVE" \
        --skip-root-composer --skip-lib-composer \
        --skip-npm-install --skip-npm-build \
        --skip-directory-clean --skip-copy || {
    echo "❌ Package build failed"
    exit 1
}

echo "   ✅ Package built at $PACKAGE_DIR"
```

### 3.4 Phase 4: Add skip_copy Option to PluginPackager

#### 3.4.1 Update resolveOptions()

**Location**: Lines 243-253 in PluginPackager.php (method spans 11 lines)
**Action**: Add `skip_copy` option to the `$defaults` array

**Before**:
```php
private function resolveOptions( array $options ) :array {
    $defaults = [
        'composer_root'   => true,
        'composer_lib'    => true,
        'npm_install'     => true,
        'npm_build'       => true,
        'directory_clean' => true,
    ];

    return array_replace( $defaults, array_intersect_key( $options, $defaults ) );
}
```

**After**:
```php
private function resolveOptions( array $options ) :array {
    $defaults = [
        'composer_root'   => true,
        'composer_lib'    => true,
        'npm_install'     => true,
        'npm_build'       => true,
        'directory_clean' => true,
        'skip_copy'       => false,
    ];

    return array_replace( $defaults, array_intersect_key( $options, $defaults ) );
}
```

#### 3.4.2 Update package() Method

**Location**: Lines 81-82 in PluginPackager.php
**Action**: Conditionally skip file copying

**Before**:
```php
// Build the package using PHP (cross-platform, preserves line endings)
$this->copyPluginFiles( $targetDir );
```

**After**:
```php
// Copy files (skip if already in place via git archive)
if ( !$options[ 'skip_copy' ] ) {
    $this->copyPluginFiles( $targetDir );
} else {
    $this->log( 'Skipping file copy (--skip-copy enabled)' );
}
```

#### 3.4.3 Update bin/package-plugin.php

**Location**: Lines 9-16 and after line 43
**Action**: Add `--skip-copy` CLI option

**Add to getopt array** (line 9-16):
```php
$options = getopt( '', [
    'output::',
    'skip-root-composer',
    'skip-lib-composer',
    'skip-npm-install',
    'skip-npm-build',
    'skip-directory-clean',
    'skip-copy',  // NEW
] );
```

**Add handler** (after line 43):
```php
if ( isset( $options[ 'skip-copy' ] ) ) {
    $packagerOptions[ 'skip_copy' ] = true;
}
```

---

## 4. Verification Steps

### 4.1 Verify .gitattributes export-ignore

After adding export-ignore rules, run:

```bash
# List files that WILL be in archive (should NOT include tests, docs, etc.)
# Note: Use tar -t (list) to show archive contents
git archive HEAD | tar -t | sort

# Verify specific exclusions - these should return NOTHING (empty output)
git archive HEAD | tar -t | grep -E "^(tests/|docs/|bin/)" 
# Expected: no output (all excluded)

# Verify .po files excluded
git archive HEAD | tar -t | grep "\.po$"
# Expected: no output (all .po files excluded)

# Verify .pot file excluded
git archive HEAD | tar -t | grep "\.pot$"
# Expected: no output

# Verify .mo files INCLUDED (these should appear)
git archive HEAD | tar -t | grep "\.mo$"
# Expected: list of all .mo files (e.g., languages/wp-simple-firewall-de_DE.mo)
```

**Note for Windows users**: These commands work in Git Bash. For PowerShell, use:
```powershell
git archive HEAD | tar -t | Select-String "tests/|docs/|bin/"
```

### 4.2 Verify Package Contents

```bash
# Build package
./bin/run-docker-tests.sh

# Check package structure
ls -la tmp/shield-package-local/

# Verify expected files present
test -f tmp/shield-package-local/icwp-wpsf.php && echo "✓ Main plugin file"
test -d tmp/shield-package-local/src && echo "✓ src directory"
test -d tmp/shield-package-local/assets/dist && echo "✓ assets/dist directory"
test -f tmp/shield-package-local/languages/wp-simple-firewall-de_DE.mo && echo "✓ .mo files"

# Verify excluded files NOT present
test ! -d tmp/shield-package-local/tests && echo "✓ tests excluded"
test ! -d tmp/shield-package-local/docs && echo "✓ docs excluded"
test ! -f tmp/shield-package-local/languages/wp-simple-firewall-de_DE.po && echo "✓ .po files excluded"
```

### 4.3 Run Full Test Suite

```bash
./bin/run-docker-tests.sh
# Tests should pass with the new package
```

---

## 5. Summary of Changes

| File | Lines Changed | Change Type |
|------|---------------|-------------|
| `.gitattributes` | +76 lines | Append export-ignore rules |
| `PluginPackager.php` | +100, -100 lines | Replace copyPluginFiles(), add helper methods, remove cleanPoFiles() |
| `bin/package-plugin.php` | +5 lines | Add --skip-copy option |
| `bin/run-docker-tests.sh` | Major rewrite | Docker-based builds, git archive |

### Methods Added to PluginPackager

1. `getExportIgnorePatterns()` - Parse .gitattributes
2. `shouldExcludePath()` - Check if path matches export-ignore pattern
3. `isCommonGitIgnoredPath()` - Check for gitignored paths

### Methods Removed from PluginPackager

1. `cleanPoFiles()` - No longer needed (handled by export-ignore)

### CLI Options Added

1. `--skip-copy` - Skip file copying when files already in place

---

## 6. Pre-Implementation Checklist

Before starting implementation, verify:

- [ ] Docker Desktop is installed and running
- [ ] Git Bash is available (for Windows users running shell script)
- [ ] Current working directory is the project root
- [ ] All pending changes are committed (so `git archive` produces expected output)
- [ ] `npm run build` has been run at least once (so assets/dist exists)
- [ ] You understand the edit order for PluginPackager.php (Section 3.2.4)

After implementation, verify:

- [ ] `git archive HEAD | tar -t` excludes tests/, docs/, bin/, .po files
- [ ] `git archive HEAD | tar -t` includes src/, assets/, templates/, .mo files
- [ ] `./bin/run-docker-tests.sh` completes without errors
- [ ] Package at `tmp/shield-package-local/` contains expected files
- [ ] Package does NOT contain .po files in languages/

---

## 7. Exclusion Responsibility Matrix

| What | Where Handled | Why |
|------|---------------|-----|
| Development files (tests, docs, bin) | `.gitattributes` export-ignore | Tracked in git, excluded at archive time |
| Translation source files (.po, .pot) | `.gitattributes` export-ignore | Tracked in git, not needed at runtime |
| vendor/twig, vendor/monolog | `cleanupPackageFiles()` | Created by composer, removed after Strauss |
| vendor/bin | `cleanupPackageFiles()` | Created by composer, not needed |
| strauss.phar | `cleanupPackageFiles()` | Downloaded during build, not needed |
| Twig autoload references | `cleanAutoloadFiles()` | Content modification in generated files |
| assets/dist (shell script) | Copied separately after `git archive` | `git archive` excludes gitignored files |
| assets/dist (PHP direct) | Copied by `copyPluginFiles()` | Exists on disk, needed at runtime |
| node_modules, vendor, .git, .idea | Skipped by `isCommonGitIgnoredPath()` | Development/build artifacts, not needed |
| src/lib/vendor, src/lib/vendor_prefixed | Skipped by `isCommonGitIgnoredPath()` | Created DURING packaging, not before |

---

## 8. Reviewed Concerns (Addressed)

The following concerns were raised during review and have been verified as **not issues**:

| Concern | Why It's Not An Issue |
|---------|----------------------|
| "Line numbers will shift after removing cleanPoFiles()" | Removing lines 675-692 does NOT affect line 87 (earlier in file). Edit order "bottom to top" is correct. |
| "`/languages/*.po` glob may not work" | All .po files are directly in `languages/` (no subdirectories). The pattern `*` correctly matches filenames. Verified via `git ls-files languages/`. |
| "Regex for glob patterns is incorrect" | Tested: `#^languages/[^/]*\.po$#` matches `languages/wp-simple-firewall-de_DE.po` correctly (returns 1). |
| "Empty .gitattributes not handled" | `file()` returns `[]` for empty files, foreach doesn't execute, empty array returned. Correct behavior. |
| "No verification before --skip-copy" | `git archive ... || exit 1` exits on failure. Added inline verification for extra safety. |
| "Export-ignore patterns may not match files" | All patterns verified against actual tracked files via `git ls-files`. |
| "RecursiveCallbackFilterIterator may exclude too early" | Filter correctly prevents descending into excluded directories. Files in excluded dirs matched by prefix. |
| "Composer package-plugin script not verified" | Script defined at composer.json line 62: `"package-plugin": "@php bin/package-plugin.php"` |

**Minor improvements added based on review:**
1. Added inline verification after `git archive` to check main plugin file exists
2. Added clarifying comments about Docker path mapping
3. Added note about path normalization approach in `shouldExcludePath()`
4. Added error handling for `cp` command when copying assets
5. Added case sensitivity documentation in `shouldExcludePath()`
6. Added symlink behavior documentation in `copyPluginFiles()`

---

## 9. Edge Cases and Assumptions

This section documents edge cases and their expected behavior:

| Edge Case | Behavior | Notes |
|-----------|----------|-------|
| **Case sensitivity** | Matching is case-sensitive | Consistent with git's behavior. Git tracks paths exactly as committed. |
| **Symlinks** | Followed by default | `RecursiveDirectoryIterator` follows symlinks. Symlinks to excluded dirs are handled by filter. |
| **Hidden files** | Included unless in export-ignore | Files starting with `.` are processed normally. Add to export-ignore to exclude. |
| **Unicode paths** | Handled by Symfony Path | `Path::makeRelative()` supports UTF-8 paths. |
| **Empty .gitattributes** | Returns empty pattern list | `file()` returns empty array, no patterns to match, all files included. |
| **Malformed .gitattributes** | Graceful degradation | Regex only matches valid `pattern export-ignore` lines. Invalid lines ignored. |
| **Shell script environment** | Git Bash required on Windows | `cp -r`, `tar`, and other Unix commands require Git Bash or WSL. |
| **Very large repos** | Optimized via filter iterator | Filter prevents descending into excluded directories, avoiding full traversal. |

**Requirements:**
- Docker Desktop installed and running
- Git Bash (on Windows) for shell script execution
- Symfony Filesystem 5.4+ (required for `Path::makeRelative()`)
