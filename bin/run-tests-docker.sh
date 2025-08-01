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
    bin/install-wp-tests.sh "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION"
else
    echo "Error: bin/install-wp-tests.sh not found"
    exit 1
fi

# Install Composer dependencies
echo "Installing Composer dependencies..."
composer install --no-interaction --no-cache

# Install runtime dependencies
echo "Installing runtime dependencies..."
if [ -d "src/lib" ]; then
    cd src/lib
    composer install --no-interaction --no-cache --no-dev
    cd ../..
fi

# Run tests
echo "Running PHPUnit tests..."
vendor/bin/phpunit -c phpunit-unit.xml --no-coverage
vendor/bin/phpunit -c phpunit-integration.xml --no-coverage

echo "=== All tests completed successfully! ==="