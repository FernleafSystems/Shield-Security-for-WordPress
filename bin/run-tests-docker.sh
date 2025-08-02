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
timeout=30
while ! mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --silent; do
    timeout=$((timeout - 1))
    if [ $timeout -eq 0 ]; then
        echo "MySQL failed to start within 30 seconds"
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

# Handle dependency installation based on testing mode
if [ -n "$SHIELD_PACKAGE_PATH" ]; then
    echo "Package Testing Mode: Skipping dependency installation (package should be pre-built)"
    echo "ℹ Note: Package testing assumes all dependencies are already installed in the package"
else
    # Install Composer dependencies for source testing
    echo "Source Testing Mode: Installing Composer dependencies..."
    composer install --no-interaction --no-cache

    # Install runtime dependencies
    echo "Installing runtime dependencies..."
    if [ -d "src/lib" ]; then
        cd src/lib
        composer install --no-interaction --no-cache --no-dev
        cd ../..
    fi
fi

# Run tests with environment variable support
echo "Running PHPUnit tests..."

# Prepare environment variables for PHPUnit
PHPUNIT_ENV=""
if [ -n "$SHIELD_PACKAGE_PATH" ]; then
    PHPUNIT_ENV="SHIELD_PACKAGE_PATH=$SHIELD_PACKAGE_PATH"
    echo "ℹ Passing SHIELD_PACKAGE_PATH to PHPUnit: $SHIELD_PACKAGE_PATH"
fi

# Run Unit Tests
echo "Running Unit Tests..."
if [ -n "$PHPUNIT_ENV" ]; then
    env $PHPUNIT_ENV vendor/bin/phpunit -c phpunit-unit.xml --no-coverage
else
    vendor/bin/phpunit -c phpunit-unit.xml --no-coverage
fi

# Run Integration Tests
echo "Running Integration Tests..."
if [ -n "$PHPUNIT_ENV" ]; then
    env $PHPUNIT_ENV vendor/bin/phpunit -c phpunit-integration.xml --no-coverage
else
    vendor/bin/phpunit -c phpunit-integration.xml --no-coverage
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