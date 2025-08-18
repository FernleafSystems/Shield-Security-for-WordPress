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

# Create Docker environment file
echo "⚙️  Setting up Docker environment..."
mkdir -p tests/docker
cat > tests/docker/.env << EOF
PHP_VERSION=7.4
WP_VERSION=$LATEST_VERSION
TEST_PHP_VERSION=7.4
TEST_WP_VERSION=$LATEST_VERSION
PLUGIN_SOURCE=$PACKAGE_DIR
SHIELD_PACKAGE_PATH=$PACKAGE_DIR
EOF

echo "   Environment configured for PHP 7.4 + WordPress $LATEST_VERSION"

# Build Docker image with latest WordPress (we'll use this for both tests)
echo "🐳 Building Docker test image with PHP 7.4..."
docker build tests/docker/ \
    --build-arg PHP_VERSION=7.4 \
    --build-arg WP_VERSION=latest \
    --tag shield-test-runner:latest

# Run tests with latest WordPress
echo "🧪 Running tests with PHP 7.4 + WordPress $LATEST_VERSION..."
docker compose -f tests/docker/docker-compose.yml \
    -f tests/docker/docker-compose.ci.yml \
    -f tests/docker/docker-compose.package.yml \
    run --rm -T test-runner

# Update environment for previous WordPress version
echo "⚙️  Switching to previous WordPress version..."
cat > tests/docker/.env << EOF
PHP_VERSION=7.4
WP_VERSION=$PREVIOUS_VERSION
TEST_PHP_VERSION=7.4
TEST_WP_VERSION=$PREVIOUS_VERSION
PLUGIN_SOURCE=$PACKAGE_DIR
SHIELD_PACKAGE_PATH=$PACKAGE_DIR
EOF

echo "   Environment configured for PHP 7.4 + WordPress $PREVIOUS_VERSION"

# Run tests with previous WordPress (reusing same image - runtime WordPress download)
echo "🧪 Running tests with PHP 7.4 + WordPress $PREVIOUS_VERSION..."
docker compose -f tests/docker/docker-compose.yml \
    -f tests/docker/docker-compose.ci.yml \
    -f tests/docker/docker-compose.package.yml \
    run --rm -T test-runner

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