#!/bin/bash
# Docker Test Runner for Shield Security

set -e

# Default values
TEST_TYPE="all"
TEST_FILE=""

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --unit)
            TEST_TYPE="unit"
            shift
            ;;
        --integration)
            TEST_TYPE="integration"
            shift
            ;;
        --package)
            TEST_TYPE="package"
            shift
            ;;
        *)
            TEST_FILE="$1"
            shift
            ;;
    esac
done

# Navigate to docker directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR/.."

# Ensure containers are running
echo "Starting Docker containers..."
docker-compose up -d

# Wait for MySQL to be ready
echo "Waiting for database..."
docker-compose exec -T mysql mysqladmin ping -h localhost --wait=30

# Run tests based on type
case $TEST_TYPE in
    unit)
        echo "Running unit tests..."
        docker-compose exec -T test-runner phpunit -c /var/www/html/wp-content/plugins/wp-simple-firewall/phpunit-unit.xml $TEST_FILE
        ;;
    integration)
        echo "Running integration tests..."
        docker-compose exec -T test-runner phpunit -c /var/www/html/wp-content/plugins/wp-simple-firewall/phpunit-integration.xml $TEST_FILE
        ;;
    package)
        echo "Building and testing package..."
        # TODO: Implement package testing
        echo "Package testing not yet implemented"
        ;;
    all)
        echo "Running all tests..."
        docker-compose exec -T test-runner phpunit -c /var/www/html/wp-content/plugins/wp-simple-firewall/phpunit.xml $TEST_FILE
        ;;
esac