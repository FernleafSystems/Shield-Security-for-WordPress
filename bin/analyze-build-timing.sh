#!/bin/bash
# Build Process Timing Analysis Script
# Identifies bottlenecks in the 2+ minute build process
#
# Purpose: Measures and analyzes the time taken by each component of the Shield Security
# build process to identify performance bottlenecks and optimization opportunities.
#
# Usage: ./bin/analyze-build-timing.sh
#
# Output: Detailed timing breakdown of:
#   - NPM install and build phases
#   - Composer install
#   - Package build process with sub-component timing
#   - Bottleneck identification and optimization suggestions

set -e

echo "=== Shield Security Build Process Timing Analysis ==="
echo "Analyzing build performance to identify bottlenecks..."
echo ""

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

# Function to format duration in human-readable format
format_duration() {
    local duration=$1
    local minutes=$((duration / 60))
    local seconds=$((duration % 60))
    if [ $minutes -gt 0 ]; then
        echo "${minutes}m ${seconds}s"
    else
        echo "${seconds}s"
    fi
}

# Track overall timing
TOTAL_START=$(date +%s)

# Test 1: NPM install
echo "📦 Testing npm install..."
NPM_INSTALL_START=$(date +%s)
if command -v npm >/dev/null 2>&1; then
    # Clear npm cache to ensure clean test
    npm cache clean --force >/dev/null 2>&1 || true
    
    # Remove node_modules for clean install
    rm -rf node_modules
    
    # Time npm install with ci for reproducible builds
    npm ci --no-audit --no-fund >/dev/null 2>&1
    NPM_INSTALL_END=$(date +%s)
    NPM_INSTALL_TIME=$((NPM_INSTALL_END - NPM_INSTALL_START))
    echo "   npm install: $(format_duration $NPM_INSTALL_TIME)"
else
    echo "   npm not found - skipping"
    NPM_INSTALL_TIME=0
fi

# Test 2: NPM build
echo "🔨 Testing npm build..."
NPM_BUILD_START=$(date +%s)
if command -v npm >/dev/null 2>&1; then
    npm run build >/dev/null 2>&1
    NPM_BUILD_END=$(date +%s)
    NPM_BUILD_TIME=$((NPM_BUILD_END - NPM_BUILD_START))
    echo "   npm build: $(format_duration $NPM_BUILD_TIME)"
else
    echo "   npm not found - skipping"
    NPM_BUILD_TIME=0
fi

# Test 3: Composer install
echo "📦 Testing composer install..."
COMPOSER_ROOT_START=$(date +%s)
composer install --no-interaction --prefer-dist --optimize-autoloader >/dev/null 2>&1
COMPOSER_ROOT_END=$(date +%s)
COMPOSER_ROOT_TIME=$((COMPOSER_ROOT_END - COMPOSER_ROOT_START))
echo "   composer install: $(format_duration $COMPOSER_ROOT_TIME)"

# Test 4: Package build process (via composer package-plugin) with detailed breakdown
echo "📦 Testing package build process (composer package-plugin)..."
PACKAGE_DIR="/tmp/shield-package-analysis-$$"
rm -rf "$PACKAGE_DIR"

# Time overall package build
PACKAGE_START=$(date +%s)

# Time file copying phase
COPY_START=$(date +%s)
mkdir -p "$PACKAGE_DIR"

# Generate plugin.json from plugin-spec before copying
php bin/build-config.php

# Copy individual files
for file in icwp-wpsf.php plugin_init.php readme.txt plugin.json cl.json \
            plugin_autoload.php plugin_compatibility.php uninstall.php unsupported.php; do
    if [ -f "$file" ]; then
        cp "$file" "$PACKAGE_DIR/" 2>/dev/null
    fi
done

# Copy directories
for dir in src assets flags languages templates; do
    if [ -d "$dir" ]; then
        cp -R "$dir" "$PACKAGE_DIR/" 2>/dev/null
    fi
done
COPY_END=$(date +%s)
COPY_TIME=$((COPY_END - COPY_START))

# Time composer install in package directory
PACKAGE_COMPOSER_START=$(date +%s)
cd "$PACKAGE_DIR"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader >/dev/null 2>&1
PACKAGE_COMPOSER_END=$(date +%s)
PACKAGE_COMPOSER_TIME=$((PACKAGE_COMPOSER_END - PACKAGE_COMPOSER_START))

# Time Strauss download and execution
STRAUSS_START=$(date +%s)

# Check if Strauss is already cached
if [ -f "/tmp/strauss.phar" ]; then
    echo "   Using cached Strauss.phar"
    cp /tmp/strauss.phar strauss.phar
    STRAUSS_DOWNLOAD_TIME=0
else
    # Download Strauss and cache it
    curl -sL https://github.com/BrianHenryIE/strauss/releases/download/0.19.4/strauss.phar -o strauss.phar
    cp strauss.phar /tmp/strauss.phar  # Cache for subsequent runs
    STRAUSS_END_DOWNLOAD=$(date +%s)
    STRAUSS_DOWNLOAD_TIME=$((STRAUSS_END_DOWNLOAD - STRAUSS_START))
fi

# Run Strauss for namespace prefixing
php strauss.phar >/dev/null 2>&1 || true
STRAUSS_END=$(date +%s)
STRAUSS_RUN_TIME=$((STRAUSS_END - STRAUSS_START - STRAUSS_DOWNLOAD_TIME))

# Cleanup temporary package directory
cd "$PROJECT_ROOT"
rm -rf "$PACKAGE_DIR"

PACKAGE_END=$(date +%s)
PACKAGE_TOTAL_TIME=$((PACKAGE_END - PACKAGE_START))

echo "   Package build breakdown (composer package-plugin):"
echo "     - File copying: $(format_duration $COPY_TIME)"
echo "     - Composer install: $(format_duration $PACKAGE_COMPOSER_TIME)"
echo "     - Strauss download: $(format_duration $STRAUSS_DOWNLOAD_TIME)"
echo "     - Strauss execution: $(format_duration $STRAUSS_RUN_TIME)"
echo "     - Total: $(format_duration $PACKAGE_TOTAL_TIME)"

# Calculate total build time
TOTAL_END=$(date +%s)
TOTAL_TIME=$((TOTAL_END - TOTAL_START))

echo ""
echo "=== Build Process Timing Summary ==="
echo "NPM Install:          $(format_duration $NPM_INSTALL_TIME)"
echo "NPM Build:            $(format_duration $NPM_BUILD_TIME)"
echo "Composer:             $(format_duration $COMPOSER_ROOT_TIME)"
echo "Package Build:        $(format_duration $PACKAGE_TOTAL_TIME)"
echo "─────────────────────────────────"
echo "TOTAL BUILD TIME:     $(format_duration $TOTAL_TIME)"

# Identify bottlenecks
echo ""
echo "=== Bottleneck Analysis ==="

# Find the largest time consumer using associative array
declare -A times
times["NPM Install"]=$NPM_INSTALL_TIME
times["NPM Build"]=$NPM_BUILD_TIME
times["Composer"]=$COMPOSER_ROOT_TIME
times["Package Build"]=$PACKAGE_TOTAL_TIME

max_time=0
max_component=""
for component in "${!times[@]}"; do
    if [ ${times[$component]} -gt $max_time ]; then
        max_time=${times[$component]}
        max_component="$component"
    fi
done

echo "🔴 Biggest bottleneck: $max_component ($(format_duration $max_time))"

# Calculate percentage of total time
if [ $TOTAL_TIME -gt 0 ]; then
    percentage=$((max_time * 100 / TOTAL_TIME))
    echo "   This represents ${percentage}% of total build time"
fi

# Optimization suggestions based on measured times
echo ""
echo "=== Optimization Opportunities ==="

if [ $NPM_INSTALL_TIME -gt 30 ]; then
    echo "• NPM install is slow (>30s) - consider:"
    echo "  - Using npm ci with cache in CI/CD"
    echo "  - Implementing npm cache in Docker layers"
    echo "  - Using --prefer-offline flag when possible"
fi

if [ $NPM_BUILD_TIME -gt 20 ]; then
    echo "• NPM build is slow (>20s) - consider:"
    echo "  - Reviewing webpack configuration for optimization"
    echo "  - Enabling webpack caching"
    echo "  - Using production mode optimizations"
fi

if [ $COMPOSER_ROOT_TIME -gt 15 ]; then
    echo "• Composer install is slow (>15s) - consider:"
    echo "  - Ensuring --prefer-dist flag is used"
    echo "  - Implementing Composer cache in CI/CD"
    echo "  - Using prestissimo plugin for parallel downloads"
fi

if [ $STRAUSS_DOWNLOAD_TIME -gt 5 ]; then
    echo "• Strauss download takes time (>5s) - consider:"
    echo "  - Implementing permanent caching in /tmp/strauss.phar"
    echo "  - Including Strauss in Docker image"
    echo "  - Committing Strauss to repository tools"
fi

if [ $PACKAGE_COMPOSER_TIME -gt 20 ]; then
    echo "• Package composer install is slow (>20s) - consider:"
    echo "  - This may be redundant if dependencies are already installed"
    echo "  - Copying vendor directory instead of reinstalling"
    echo "  - Using rsync for faster file operations"
fi

if [ $COPY_TIME -gt 10 ]; then
    echo "• File copying is slow (>10s) - consider:"
    echo "  - Using rsync instead of cp for large directories"
    echo "  - Implementing parallel copying"
    echo "  - Excluding unnecessary files during copy"
fi

# Additional performance tips
echo ""
echo "=== General Performance Tips ==="
echo "• Run builds on SSD storage for faster I/O"
echo "• Use Docker layer caching for dependencies"
echo "• Implement parallel execution where possible"
echo "• Consider using build caching tools like ccache"
echo "• Profile webpack bundle for optimization opportunities"

echo ""
echo "Analysis complete! Total analysis time: $(format_duration $TOTAL_TIME)"
echo ""
echo "To save this report: $0 > build-timing-report.txt"