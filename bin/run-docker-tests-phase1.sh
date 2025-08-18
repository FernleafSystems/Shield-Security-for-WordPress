#!/bin/bash
# Simple Local Docker Test Runner
# Runs the exact same tests as CI/CD - no manual setup required

set -e

echo "🚀 Starting Local Docker Tests (matching CI configuration)"
echo "=================================================="

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

# Detect WordPress versions (exactly like CI does)
echo "📱 Detecting WordPress versions..."
if ! VERSIONS_OUTPUT=$(./.github/scripts/detect-wp-versions.sh 2>/dev/null); then
    echo "❌ WordPress version detection failed, using fallback versions"
    LATEST_VERSION="6.8.2"
    PREVIOUS_VERSION="6.7.1"
else
    LATEST_VERSION=$(echo "$VERSIONS_OUTPUT" | grep "LATEST_VERSION=" | cut -d'=' -f2)
    PREVIOUS_VERSION=$(echo "$VERSIONS_OUTPUT" | grep "PREVIOUS_VERSION=" | cut -d'=' -f2)
fi

echo "   Latest WordPress: $LATEST_VERSION"
echo "   Previous WordPress: $PREVIOUS_VERSION"

# Build assets (like CI does)
echo "🔨 Building assets..."
if command -v npm >/dev/null 2>&1; then
    npm ci --no-audit --no-fund
    npm run build
else
    echo "   ⚠️  npm not found, skipping asset build"
fi

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
PACKAGE_DIR="/tmp/shield-package-local"
rm -rf "$PACKAGE_DIR"
./bin/build-package.sh "$PACKAGE_DIR" "$PROJECT_ROOT"

# Prepare Docker environment directory
echo "⚙️  Setting up Docker environment..."
mkdir -p tests/docker

# Build Docker images for each WordPress version (matching GitHub Actions approach)
build_docker_image_for_wp_version() {
    local WP_VERSION=$1
    local VERSION_NAME=$2
    
    echo "🐳 Building Docker image for PHP 7.4 + WordPress $WP_VERSION ($VERSION_NAME)..."
    docker build tests/docker/ \
        --build-arg PHP_VERSION=7.4 \
        --build-arg WP_VERSION=$WP_VERSION \
        --tag shield-test-runner:wp-$WP_VERSION
}

# Function to run tests with specific WordPress version
run_tests_for_wp_version() {
    local WP_VERSION=$1
    local VERSION_NAME=$2
    
    echo "🧪 Running tests with PHP 7.4 + WordPress $WP_VERSION ($VERSION_NAME)..."
    
    # Update environment for this WordPress version (matching GitHub Actions exactly)
    cat > tests/docker/.env << EOF
PHP_VERSION=7.4
WP_VERSION=$WP_VERSION
TEST_PHP_VERSION=7.4
TEST_WP_VERSION=$WP_VERSION
PLUGIN_SOURCE=$PACKAGE_DIR
SHIELD_PACKAGE_PATH=$PACKAGE_DIR
EOF
    
    # Update docker-compose to use the correct image for this WordPress version
    export SHIELD_TEST_IMAGE=shield-test-runner:wp-$WP_VERSION
    
    # Run tests using the version-specific image
    docker compose -f tests/docker/docker-compose.yml \
        -f tests/docker/docker-compose.ci.yml \
        -f tests/docker/docker-compose.package.yml \
        run --rm -T test-runner
}

# Build Docker images for both WordPress versions
echo "🏗️ Building Docker images for all WordPress versions..."
build_docker_image_for_wp_version "$LATEST_VERSION" "latest"
build_docker_image_for_wp_version "$PREVIOUS_VERSION" "previous"

# Run tests for both WordPress versions using the same package
run_tests_for_wp_version "$LATEST_VERSION" "latest"
run_tests_for_wp_version "$PREVIOUS_VERSION" "previous"

# Cleanup
echo "🧹 Cleaning up..."
docker compose -f tests/docker/docker-compose.yml \
    -f tests/docker/docker-compose.ci.yml \
    -f tests/docker/docker-compose.package.yml \
    down -v --remove-orphans || true
rm -f tests/docker/.env

echo ""
echo "✅ Local Docker tests completed!"
echo "   Tests ran with the same configuration as CI:"
echo "   - PHP 7.4 + WordPress $LATEST_VERSION"
echo "   - PHP 7.4 + WordPress $PREVIOUS_VERSION"
echo "   - Package testing mode (production validation)"