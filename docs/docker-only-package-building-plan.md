---
name: Docker-Only Package Building
overview: ""
todos:
  - id: docker-check
    content: Add Docker availability check (insert after line 5, before line 7)
    status: pending
  - id: package-dir
    content: Update PACKAGE_DIR + add PACKAGE_DIR_RELATIVE for Docker
    status: pending
  - id: npm-docker
    content: Replace npm with Docker node:18, preserve cache, move cache file to tmp/
    status: pending
  - id: composer-docker
    content: Replace composer with Docker composer:2, use relative path + --skip-directory-clean
    status: pending
  - id: test-windows
    content: Test script in Git Bash on Windows without local PHP
    status: pending
  - id: test-full
    content: Verify package builds correctly and tests pass
    status: pending
---

# Docker-Only Package Building for Local Test Runner

## Problem Statement

The script [`bin/run-docker-tests.sh`](bin/run-docker-tests.sh) header claims "no manual setup required" but currently requires local PHP and Composer to build the plugin package before Docker tests run. On Windows with Git Bash, this fails with error:

```
composer: line 14: php: command not found
```

**Root Cause**: Lines 103-116 of the script call `composer` directly on the host machine, which requires PHP to be installed and in the PATH.

## Prerequisites

Before implementing this plan, verify:

- Docker Desktop is installed and running
- You have access to modify `bin/run-docker-tests.sh`
- You can test on Windows with Git Bash (ideally without PHP in PATH)

**System Requirements**:

- Docker Desktop with ~1GB free disk space (for `node:18` and `composer:2` images)
- Network access for Docker to pull images and for npm/composer to download packages
- Corporate environments may need proxy configuration in Docker Desktop settings

## Current Code That Must Change

### File: [`bin/run-docker-tests.sh`](bin/run-docker-tests.sh)

**Lines 29-33 - Package Directory Definition**:

```bash
PACKAGE_DIR="/tmp/shield-package-local"
```

Problem: `/tmp/` is not reliably accessible to Docker Desktop on Windows.

**Lines 45-101 - npm Build Section**:

Problem: Requires local npm/Node.js installation.

**Lines 103-116 - Composer Commands**:

Problem: Requires local PHP and Composer installation.

---

## Implementation Changes

### Change 1: Add Docker Availability Check

**Location**: Insert after line 5 (`set -e`), before line 7 (`echo "🚀 Starting..."`).

**Lines affected**: Inserts 14 new lines (new lines 6-19). All subsequent line numbers shift by +14.

**Add this new section**:

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
```

### Change 2: Update Package Directory Location

**Original location**: Lines 29-33

**New location after Change 1**: Lines 43-48 (29 + 14 = 43)

**Replace**:

```bash
# Set environment variables early to avoid Docker Compose warnings
PACKAGE_DIR="/tmp/shield-package-local"
export SHIELD_PACKAGE_PATH="$PACKAGE_DIR"
# PLUGIN_SOURCE needs to be the actual path, not just "package"
export PLUGIN_SOURCE="$PACKAGE_DIR"
```

**With**:

```bash
# Set environment variables early to avoid Docker Compose warnings
# Use directory inside project so Docker can mount it on all platforms (Windows, Linux, macOS)
# Note: tmp/ directory exists and is already in .gitignore (line 19)
PACKAGE_DIR="$PROJECT_ROOT/tmp/shield-package-local"
export SHIELD_PACKAGE_PATH="$PACKAGE_DIR"
# PLUGIN_SOURCE needs to be the actual path, not just "package"
export PLUGIN_SOURCE="$PACKAGE_DIR"
# Relative path for use inside Docker container (project mounted at /app)
PACKAGE_DIR_RELATIVE="tmp/shield-package-local"
```

**Why relative path is needed**: Inside Docker, the project is mounted at `/app`, not the host path. The PluginPackager's `resolveOutputDirectory()` treats absolute paths as-is, but the host path (e.g., `/d/Work/.../tmp/shield-package-local`) doesn't exist inside Docker. A relative path is resolved against the container's project root (`/app`), resulting in `/app/tmp/shield-package-local` which is correct.

### Change 3: Replace npm Build with Docker-based Build

**Original location**: Lines 45-101 (57 lines)

**New location after Change 1**: Lines 59-115 (45 + 14 = 59)

**Result**: Replaces 57 lines with ~85 lines (+28 lines)

**Replace the entire section** with Docker-based equivalent that preserves the caching logic:

```bash
# Build assets using Docker (no local Node.js required) - with caching optimization
echo "🔨 Building assets..."
DIST_DIR="$PROJECT_ROOT/assets/dist"
SRC_DIR="$PROJECT_ROOT/assets/js"
# Cache file moved from /tmp/ to project tmp/ for:
# 1. Cross-platform compatibility (Windows Docker access)
# 2. Persistence across system reboots
# 3. Consistency with package directory location
CACHE_FILE="$PROJECT_ROOT/tmp/.shield-webpack-cache-checksum"

# Ensure tmp directory exists for cache file and package builds
mkdir -p "$PROJECT_ROOT/tmp" || {
    echo "❌ Error: Could not create tmp directory: $PROJECT_ROOT/tmp"
    exit 1
}

# Check if dist directory exists and has files
WEBPACK_CACHE_VALID=false
COMBINED_CHECKSUM=""

if [ -d "$DIST_DIR" ] && [ "$(ls -A $DIST_DIR 2>/dev/null)" ]; then
    echo "   Checking webpack build cache validity..."
    
    # Calculate checksum of all source files, package.json, and webpack.config.js
    CURRENT_CHECKSUM=$(find "$SRC_DIR" -type f \( -name "*.js" -o -name "*.jsx" -o -name "*.ts" -o -name "*.tsx" \) -exec md5sum {} \; 2>/dev/null | sort | md5sum | cut -d' ' -f1)
    PACKAGE_CHECKSUM=$(md5sum "$PROJECT_ROOT/package.json" 2>/dev/null | cut -d' ' -f1)
    WEBPACK_CHECKSUM=$(md5sum "$PROJECT_ROOT/webpack.config.js" 2>/dev/null | cut -d' ' -f1)
    
    # Validate checksum calculation succeeded
    if [ -z "$CURRENT_CHECKSUM" ] || [ -z "$PACKAGE_CHECKSUM" ] || [ -z "$WEBPACK_CHECKSUM" ]; then
        echo "   ⚠️  Warning: Could not calculate checksums, cache check skipped"
    else
        COMBINED_CHECKSUM="${CURRENT_CHECKSUM}-${PACKAGE_CHECKSUM}-${WEBPACK_CHECKSUM}"
        
        # Check if we have a stored checksum and if it matches
        if [ -f "$CACHE_FILE" ]; then
            STORED_CHECKSUM=$(cat "$CACHE_FILE" 2>/dev/null)
            if [ -n "$STORED_CHECKSUM" ] && [ "$COMBINED_CHECKSUM" = "$STORED_CHECKSUM" ]; then
                # Also verify dist files actually exist
                if [ -f "$DIST_DIR/shield-main.bundle.js" ] && [ -f "$DIST_DIR/shield-main.bundle.css" ]; then
                    WEBPACK_CACHE_VALID=true
                    echo "   ✅ Webpack build cache is valid - skipping rebuild (saves ~1m 40s)"
                else
                    echo "   Dist files missing - rebuild needed"
                fi
            else
                echo "   Source files changed - rebuild needed"
            fi
        else
            echo "   No cache checksum found - first run"
        fi
    fi
fi

if [ "$WEBPACK_CACHE_VALID" = false ]; then
    echo "   Building JavaScript and CSS assets using Docker..."
    docker run --rm \
        -v "$PROJECT_ROOT:/app" \
        -w /app \
        node:18 \
        sh -c "npm ci --no-audit --no-fund && npm run build" || {
        echo "❌ Asset build failed"
        echo "   Please ensure Docker is running and has network access"
        echo "   Corporate networks may require proxy configuration in Docker Desktop"
        exit 1
    }
    
    # Calculate checksum if not already done (first run case where dist didn't exist)
    if [ -z "$COMBINED_CHECKSUM" ]; then
        CURRENT_CHECKSUM=$(find "$SRC_DIR" -type f \( -name "*.js" -o -name "*.jsx" -o -name "*.ts" -o -name "*.tsx" \) -exec md5sum {} \; 2>/dev/null | sort | md5sum | cut -d' ' -f1)
        PACKAGE_CHECKSUM=$(md5sum "$PROJECT_ROOT/package.json" 2>/dev/null | cut -d' ' -f1)
        WEBPACK_CHECKSUM=$(md5sum "$PROJECT_ROOT/webpack.config.js" 2>/dev/null | cut -d' ' -f1)
        if [ -n "$CURRENT_CHECKSUM" ] && [ -n "$PACKAGE_CHECKSUM" ] && [ -n "$WEBPACK_CHECKSUM" ]; then
            COMBINED_CHECKSUM="${CURRENT_CHECKSUM}-${PACKAGE_CHECKSUM}-${WEBPACK_CHECKSUM}"
        fi
    fi
    
    # Save the checksum for next run (only if we have a valid checksum)
    if [ -n "$COMBINED_CHECKSUM" ]; then
        if echo "$COMBINED_CHECKSUM" > "$CACHE_FILE" 2>/dev/null; then
            echo "   ✅ Build complete - cache checksum saved"
        else
            echo "   ✅ Build complete (cache checksum could not be saved)"
        fi
    else
        echo "   ✅ Build complete (checksum not calculated)"
    fi
else
    echo "   Using cached webpack build from previous run"
    # Still need to ensure node_modules exist for any tools that might need them
    if [ ! -d "$PROJECT_ROOT/node_modules" ]; then
        echo "   Installing npm dependencies (node_modules missing)..."
        docker run --rm \
            -v "$PROJECT_ROOT:/app" \
            -w /app \
            node:18 \
            sh -c "npm ci --no-audit --no-fund" || {
            echo "❌ npm install failed"
            exit 1
        }
    fi
fi
```

**Note**: The original `if command -v npm` fallback is removed entirely. This script now requires Docker for all operations, which is the intended behavior ("no manual setup required" means Docker handles everything).

### Change 4: Replace Composer Commands with Docker-based Commands

**Original location**: Lines 103-116 (14 lines)

**New location after Change 1**: Lines 117-130 (103 + 14 = 117)

**Result**: Replaces 14 lines with ~60 lines (+46 lines)

**Replace**:

```bash
# Install dependencies (like CI does)
echo "📦 Installing dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader
if [ -d "src/lib" ]; then
    cd src/lib
    composer install --no-interaction --prefer-dist --optimize-autoloader
    cd ../..
fi

# Build plugin package (like CI does)
echo "📦 Building plugin package..."
# PACKAGE_DIR already set earlier to avoid Docker Compose warnings
rm -rf "$PACKAGE_DIR"
composer package-plugin -- --output="$PACKAGE_DIR" --skip-root-composer --skip-lib-composer --skip-npm-install --skip-npm-build
```

**With**:

```bash
# Install dependencies using Docker (no local PHP/Composer required)
echo "📦 Installing dependencies using Docker..."

echo "   Installing root composer dependencies..."
docker run --rm \
    -v "$PROJECT_ROOT:/app" \
    -w /app \
    composer:2 \
    composer install --no-interaction --prefer-dist --optimize-autoloader || {
    echo "❌ Root composer install failed"
    exit 1
}

if [ -d "$PROJECT_ROOT/src/lib" ]; then
    echo "   Installing src/lib composer dependencies..."
    docker run --rm \
        -v "$PROJECT_ROOT:/app" \
        -w /app/src/lib \
        composer:2 \
        composer install --no-interaction --prefer-dist --optimize-autoloader || {
        echo "❌ src/lib composer install failed"
        exit 1
    }
fi

echo "   ✅ Dependencies installed successfully"

# Build plugin package using Docker (no local PHP required)
echo "📦 Building plugin package using Docker..."

# Clean package directory on HOST (before Docker runs)
if [ -d "$PACKAGE_DIR" ]; then
    rm -rf "$PACKAGE_DIR" || {
        echo "❌ Error: Could not remove existing package directory: $PACKAGE_DIR"
        exit 1
    }
fi

# Create package directory on HOST
mkdir -p "$PACKAGE_DIR" || {
    echo "❌ Error: Could not create package directory: $PACKAGE_DIR"
    exit 1
}

# Run composer package-plugin inside Docker
# CRITICAL: Use RELATIVE path ($PACKAGE_DIR_RELATIVE) not absolute path ($PACKAGE_DIR)
# Inside Docker, project is mounted at /app, not the host path
# The PluginPackager resolves relative paths against its project root (/app)
# Using --skip-directory-clean because:
#   1. We already cleaned the directory manually above
#   2. PluginPackager blocks cleaning directories within project root for safety
docker run --rm \
    -v "$PROJECT_ROOT:/app" \
    -w /app \
    composer:2 \
    composer package-plugin -- --output="$PACKAGE_DIR_RELATIVE" --skip-root-composer --skip-lib-composer --skip-npm-install --skip-npm-build --skip-directory-clean || {
    echo "❌ Package build failed"
    echo "   Please check the error messages above"
    exit 1
}

# Verify package was created with expected structure (check on HOST using $PACKAGE_DIR)
if [ ! -f "$PACKAGE_DIR/icwp-wpsf.php" ]; then
    echo "❌ Package verification failed - main plugin file not found"
    echo "   Expected: $PACKAGE_DIR/icwp-wpsf.php"
    exit 1
fi
if [ ! -d "$PACKAGE_DIR/src" ] || [ ! -d "$PACKAGE_DIR/assets" ]; then
    echo "❌ Package verification failed - expected directories missing"
    echo "   Expected: $PACKAGE_DIR/src/ and $PACKAGE_DIR/assets/"
    exit 1
fi

echo "   ✅ Package built successfully at $PACKAGE_DIR"
```

**Critical details:**

1. **Relative path for Docker**: Uses `$PACKAGE_DIR_RELATIVE` (`tmp/shield-package-local`), not the absolute host path.
2. **--skip-directory-clean flag**: Required because PluginPackager blocks cleaning directories within project root.
3. **composer:2 image**: Uses Composer 2.x for better stability than `composer:latest`.

---

## Line Number Summary

| Change | Original Lines | Lines Changed | Net Change |

|--------|---------------|---------------|------------|

| Change 1 | Insert after 5 | +14 lines | +14 |

| Change 2 | 29-33 (5 lines) | 9 lines | +4 |

| Change 3 | 45-101 (57 lines) | ~85 lines | +28 |

| Change 4 | 103-116 (14 lines) | ~60 lines | +46 |

| **Total** | | | **+92 lines** |

**Final script**: ~585 lines (493 + 92)

---

## Technical Considerations

### Docker Path Conversion (CRITICAL)

Inside Docker containers:

- The project is mounted at `/app` (via `-v "$PROJECT_ROOT:/app"`)
- The PluginPackager detects its project root as `/app` (from `realpath(__DIR__.'/../../..')`)
- Host paths like `/d/Work/.../tmp/shield-package-local` don't exist inside Docker

**Solution**: Use a relative path (`tmp/shield-package-local`) which the PluginPackager resolves against `/app`, resulting in `/app/tmp/shield-package-local`.

### PluginPackager Safety Check

The PluginPackager (lines 852-876 of `infrastructure/src/Tooling/PluginPackager.php`) has a safety check that blocks cleaning directories within the project root. We use `--skip-directory-clean` and manually remove the directory on the host before running Docker.

### Windows Path Handling (MSYS_NO_PATHCONV)

Git Bash on Windows sometimes converts paths incorrectly when passing them to Docker. Symptoms include:

- Volume mounts failing with "path not found"
- Paths appearing with double slashes or incorrect drive letters

**When to use**: If you see path-related errors during Docker operations, run:

```bash
export MSYS_NO_PATHCONV=1
./bin/run-docker-tests.sh
```

This disables Git Bash's automatic path conversion, letting Docker Desktop handle it instead.

### Cache File Location Change (INTENTIONAL)

The webpack cache checksum file moves from `/tmp/shield-webpack-cache-checksum` to `$PROJECT_ROOT/tmp/.shield-webpack-cache-checksum`. This is intentional:

1. **Cross-platform**: Works reliably on Windows Docker
2. **Persistent**: Survives system reboots (system `/tmp/` is often cleared)
3. **Consistent**: Same location strategy as the package directory
4. **Already ignored**: Covered by `.gitignore` line 19 (`/tmp/`)

### Docker Image Selection

- **node:18**: Stable LTS version, widely compatible
- **composer:2**: Composer 2.x for stability (not `latest` which could change unexpectedly)

For maximum reproducibility, consider pinning specific versions:

```bash
node:18.19-alpine    # Smaller image, faster pulls
composer:2.7         # Specific Composer version
```

### Network Requirements

Docker containers need network access for:

- `npm ci`: Downloads packages from npm registry
- `composer install`: Downloads packages from Packagist

Corporate environments may need proxy configuration in Docker Desktop settings.

---

## Files Modified

| File | Type of Change |

|------|----------------|

| [`bin/run-docker-tests.sh`](bin/run-docker-tests.sh) | Modify existing file (4 sections) |

No other files need modification.

---

## Verification Steps

### Test 1: Windows Git Bash (Primary Test Case)

```bash
# In Git Bash on Windows, with NO PHP in PATH
cd /d/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield
./bin/run-docker-tests.sh
```

**Expected**: Script completes successfully, building everything inside Docker.

### Test 2: Verify Package Contents

```bash
ls -la tmp/shield-package-local/
# Should contain: icwp-wpsf.php, plugin.json, src/, assets/
```

### Test 3: Verify Cache Works

```bash
# Run twice - second run should skip asset rebuild
./bin/run-docker-tests.sh
# Look for: "✅ Webpack build cache is valid - skipping rebuild"
```

### Test 4: Verify node_modules Check

```bash
rm -rf node_modules
./bin/run-docker-tests.sh
# Look for: "Installing npm dependencies (node_modules missing)..."
```

### Test 5: Clean Run

```bash
rm -rf assets/dist tmp/shield-package-local tmp/.shield-webpack-cache-checksum node_modules
./bin/run-docker-tests.sh
```

### Test 6: Docker Error Handling

```bash
# Stop Docker Desktop, then run:
./bin/run-docker-tests.sh
# Expected: "❌ Error: Docker is installed but not running"
```

---

## Potential Issues and Solutions

| Issue | Cause | Solution |

|-------|-------|----------|

| "Cannot connect to Docker daemon" | Docker Desktop not running | Start Docker Desktop |

| "drive not shared" error | Docker file sharing disabled | Enable file sharing in Docker Desktop Settings |

| Path errors on Windows | Git Bash path conversion | Run with `MSYS_NO_PATHCONV=1` prefix |

| Slow first run (~3 min extra) | Docker images being pulled | Normal; pre-pull with `docker pull node:18 composer:2` |

| Network errors during npm/composer | No internet or proxy needed | Configure proxy in Docker Desktop settings |

| "Cannot build package within project" | Missing --skip-directory-clean | Already fixed in plan |

---

## Rollback

```bash
git checkout HEAD -- bin/run-docker-tests.sh
```

---

## Impact on Other Systems

### GitHub Actions CI/CD (VERIFIED - NO IMPACT)

The `bin/run-docker-tests.sh` script is **NOT used by GitHub Actions**. CI workflows use `setup-php` and `setup-node` actions instead.

### Other Local Scripts

- **PowerShell script** (`bin/run-tests.ps1`): Unchanged
- **docker-compose files**: Unchanged
