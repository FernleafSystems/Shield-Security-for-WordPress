#!/bin/bash
# Shield Security Docker Test Runner
# Based on Easy Digital Downloads run-tests-internal-only.sh pattern

set -e

# Require arguments (following EDD pattern)
if [ $# -lt 3 ]; then
    echo "Usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

echo "=== Shield Security Docker Test Runner ==="
echo "Database: $DB_NAME@$DB_HOST"
echo "User: $DB_USER"
echo "WordPress Version: $WP_VERSION"
echo "PHP Version: $(php --version | head -n 1)"

# Check and display SHIELD_PACKAGE_PATH environment variable
if [ -n "$SHIELD_PACKAGE_PATH" ]; then
    echo "Package Testing Mode: ENABLED"
    echo "Package Path: $SHIELD_PACKAGE_PATH"
    
    # Verify the package exists and has the expected structure
    if [ ! -d "$SHIELD_PACKAGE_PATH" ]; then
        echo "ERROR: SHIELD_PACKAGE_PATH directory does not exist: $SHIELD_PACKAGE_PATH"
        exit 1
    fi
    
    if [ ! -f "$SHIELD_PACKAGE_PATH/icwp-wpsf.php" ]; then
        echo "ERROR: Main plugin file not found in package: $SHIELD_PACKAGE_PATH/icwp-wpsf.php"
        exit 1
    fi
    
    echo "✓ Package verification successful"
    # Export to ensure it's available to child processes
    export SHIELD_PACKAGE_PATH
else
    echo "Package Testing Mode: DISABLED (testing against source)"
fi

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
echo "Connection details: $DB_USER@$DB_HOST (password: ${DB_PASS:+***provided***}${DB_PASS:-not provided})"

# Build mysqladmin command with proper password handling
MYSQL_CMD="mysqladmin ping -h\"$DB_HOST\" -u\"$DB_USER\""
if [ -n "$DB_PASS" ]; then
    MYSQL_CMD="$MYSQL_CMD -p\"$DB_PASS\""
fi
MYSQL_CMD="$MYSQL_CMD --silent"

timeout=30
while ! eval "$MYSQL_CMD"; do
    timeout=$((timeout - 1))
    if [ $timeout -eq 0 ]; then
        echo "ERROR: MySQL failed to start within 30 seconds"
        echo "Connection details: $DB_USER@$DB_HOST"
        echo "Last attempted command: mysqladmin ping -h\"$DB_HOST\" -u\"$DB_USER\" ${DB_PASS:+-p***} --silent"
        echo "Troubleshooting tips:"
        echo "  - Verify MySQL container is running and accepting connections"
        echo "  - Check if password is required and correctly provided"
        echo "  - Ensure DB_HOST is accessible from this container"
        exit 1
    fi
    echo "Waiting for MySQL... ($timeout seconds left)"
    sleep 1
done
echo "MySQL is ready!"

# Install WordPress test environment (using existing script)
echo "Installing WordPress test environment..."
if [ -f "bin/install-wp-tests.sh" ]; then
    # Pass 'true' as SKIP_DB_CREATE parameter (6th arg) since database is pre-created by Docker
    bin/install-wp-tests.sh "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION" true
else
    echo "Error: bin/install-wp-tests.sh not found"
    exit 1
fi

# Create symlink for plugin in WordPress plugins directory
# This ensures plugins_url() returns correct URLs for our plugin
# WordPress calculates plugin URLs relative to WP_PLUGIN_DIR, so the plugin must be there
echo "Setting up plugin symlink for WordPress..."
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}
WP_PLUGINS_DIR="$WP_CORE_DIR/wp-content/plugins"
PLUGIN_SLUG="wp-simple-firewall"

# Determine the actual plugin source directory
if [ -n "$SHIELD_PACKAGE_PATH" ]; then
    PLUGIN_SOURCE_DIR="$SHIELD_PACKAGE_PATH"
else
    PLUGIN_SOURCE_DIR="/app"
fi

# Verify source directory exists before attempting symlink
if [ ! -d "$PLUGIN_SOURCE_DIR" ]; then
    echo ""
    echo "❌ FATAL: Plugin source directory does not exist"
    echo "   Expected: $PLUGIN_SOURCE_DIR"
    echo "   SHIELD_PACKAGE_PATH: ${SHIELD_PACKAGE_PATH:-not set}"
    echo ""
    echo "   This usually means:"
    echo "   - The Docker volume mount failed"
    echo "   - SHIELD_PACKAGE_PATH points to a non-existent directory"
    echo ""
    exit 1
fi

# Verify main plugin file exists in source
if [ ! -f "$PLUGIN_SOURCE_DIR/icwp-wpsf.php" ]; then
    echo ""
    echo "❌ FATAL: Main plugin file not found in source directory"
    echo "   Expected: $PLUGIN_SOURCE_DIR/icwp-wpsf.php"
    echo "   Directory contents:"
    ls -la "$PLUGIN_SOURCE_DIR" 2>/dev/null || echo "   (could not list directory)"
    echo ""
    exit 1
fi

# Create plugins directory if it doesn't exist
if ! mkdir -p "$WP_PLUGINS_DIR"; then
    echo ""
    echo "❌ FATAL: Could not create WordPress plugins directory"
    echo "   Target: $WP_PLUGINS_DIR"
    echo "   Check filesystem permissions"
    echo ""
    exit 1
fi

# Remove existing symlink/directory if present
if [ -L "$WP_PLUGINS_DIR/$PLUGIN_SLUG" ] || [ -d "$WP_PLUGINS_DIR/$PLUGIN_SLUG" ]; then
    rm -rf "$WP_PLUGINS_DIR/$PLUGIN_SLUG"
fi

# Create symlink from WordPress plugins dir to our plugin
echo "Creating symlink: $WP_PLUGINS_DIR/$PLUGIN_SLUG -> $PLUGIN_SOURCE_DIR"
if ! ln -s "$PLUGIN_SOURCE_DIR" "$WP_PLUGINS_DIR/$PLUGIN_SLUG"; then
    echo ""
    echo "❌ FATAL: Could not create symlink"
    echo "   Source: $PLUGIN_SOURCE_DIR"
    echo "   Target: $WP_PLUGINS_DIR/$PLUGIN_SLUG"
    echo ""
    echo "   Diagnostic information:"
    echo "   - Source exists: $([ -d "$PLUGIN_SOURCE_DIR" ] && echo "yes" || echo "no")"
    echo "   - Target parent exists: $([ -d "$WP_PLUGINS_DIR" ] && echo "yes" || echo "no")"
    echo "   - Target parent writable: $([ -w "$WP_PLUGINS_DIR" ] && echo "yes" || echo "no")"
    echo "   - Current user: $(whoami)"
    echo "   - Filesystem type: $(df -T "$WP_PLUGINS_DIR" 2>/dev/null | tail -1 | awk '{print $2}' || echo "unknown")"
    echo ""
    exit 1
fi
echo "✓ Plugin symlinked successfully"

# Verify the symlink works (can access plugin file through it)
if [ ! -f "$WP_PLUGINS_DIR/$PLUGIN_SLUG/icwp-wpsf.php" ]; then
    echo ""
    echo "❌ FATAL: Symlink created but plugin file not accessible through it"
    echo "   Symlink: $WP_PLUGINS_DIR/$PLUGIN_SLUG"
    echo "   Points to: $(readlink "$WP_PLUGINS_DIR/$PLUGIN_SLUG")"
    echo "   Expected file: $WP_PLUGINS_DIR/$PLUGIN_SLUG/icwp-wpsf.php"
    echo ""
    echo "   This indicates a broken symlink or permission issue"
    echo ""
    exit 1
fi
echo "✓ Plugin file accessible via symlink"

# Handle dependency installation based on testing mode
if [ -n "$SHIELD_PACKAGE_PATH" ]; then
    echo "Package Testing Mode: Skipping dependency installation (package should be pre-built)"
    echo "ℹ Note: Package testing assumes all dependencies are already installed in the package"
else
    # Install Composer dependencies for source testing
    echo "Source Testing Mode: Installing Composer dependencies..."
    composer install --no-interaction --no-cache

    # Generate plugin.json from modular spec files (source testing only)
    # In package mode, PluginPackager already generates plugin.json in the package
    echo "Generating plugin.json from plugin-spec/ files..."
    php bin/build-config.php
fi

# Run tests with environment variable support
echo "Running PHPUnit tests..."

# Build PHPUnit extra flags
# Default: debug enabled (set PHPUNIT_DEBUG=0 to disable)
PHPUNIT_EXTRA_FLAGS=""
if [ "${PHPUNIT_DEBUG:-1}" = "1" ] || [ "${PHPUNIT_DEBUG:-}" = "true" ]; then
    PHPUNIT_EXTRA_FLAGS="--debug"
    echo "PHPUnit debug mode enabled"
fi

# Prepare environment variables for PHPUnit
PHPUNIT_ENV=""
if [ -n "$SHIELD_PACKAGE_PATH" ]; then
    PHPUNIT_ENV="SHIELD_PACKAGE_PATH=$SHIELD_PACKAGE_PATH"
    echo "ℹ Passing SHIELD_PACKAGE_PATH to PHPUnit: $SHIELD_PACKAGE_PATH"
fi

# Run Unit Tests
echo "Running Unit Tests..."
if [ -n "$PHPUNIT_ENV" ]; then
    env $PHPUNIT_ENV vendor/bin/phpunit -c phpunit-unit.xml --no-coverage $PHPUNIT_EXTRA_FLAGS
else
    vendor/bin/phpunit -c phpunit-unit.xml --no-coverage $PHPUNIT_EXTRA_FLAGS
fi

# Run Integration Tests
echo "Running Integration Tests..."
if [ -n "$PHPUNIT_ENV" ]; then
    env $PHPUNIT_ENV vendor/bin/phpunit -c phpunit-integration.xml --no-coverage $PHPUNIT_EXTRA_FLAGS
else
    vendor/bin/phpunit -c phpunit-integration.xml --no-coverage $PHPUNIT_EXTRA_FLAGS
fi

echo "=== All tests completed successfully! ==="
echo ""
echo "Test Summary:"
if [ -n "$SHIELD_PACKAGE_PATH" ]; then
    echo "  Testing Mode: Package Testing"
    echo "  Plugin Path: $SHIELD_PACKAGE_PATH"
    echo "  Dependencies: Pre-built package (skipped installation)"
else
    echo "  Testing Mode: Source Testing"
    echo "  Plugin Path: /app (mounted source directory)"
    echo "  Dependencies: Installed during test run"
fi
echo "  Database: $DB_NAME@$DB_HOST"
echo "  WordPress Version: $WP_VERSION"
echo "  PHP Version: $(php --version | head -n 1 | cut -d' ' -f2)"