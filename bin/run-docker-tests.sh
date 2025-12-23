#!/bin/bash
# Simple Local Docker Test Runner
# Runs the exact same tests as CI/CD - no manual setup required

set -e

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

echo "🚀 Starting Local Docker Tests (matching CI configuration)"
echo "=================================================="

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

# Set a predictable Compose project name to avoid generic "docker" container names
export COMPOSE_PROJECT_NAME="shield-tests"

# Source matrix configuration (single source of truth)
if [ -f "$PROJECT_ROOT/.github/config/matrix.conf" ]; then
    source "$PROJECT_ROOT/.github/config/matrix.conf"
else
    echo "⚠️  Warning: Matrix config not found, using defaults"
    DEFAULT_PHP="8.2"
fi

# Source packager config for Strauss version
if [ -f "$PROJECT_ROOT/.github/scripts/read-packager-config.sh" ]; then
    # shellcheck source=/dev/null
    source "$PROJECT_ROOT/.github/scripts/read-packager-config.sh"
else
    echo "⚠️  Warning: Packager config loader not found, STRAUSS_VERSION not set"
fi

# PHP version to test (can be overridden via environment variable)
PHP_VERSION=${PHP_VERSION:-"$DEFAULT_PHP"}
echo "🐘 PHP Version: $PHP_VERSION"

# Detect WordPress versions (exactly like CI does)
echo "📱 Detecting WordPress versions..."

# Run detection script with timeout to prevent hangs
# Using if/else pattern because script has set -e (line 5)
# The if/else ensures non-zero exit codes don't terminate the script
VERSIONS_OUTPUT=""
DETECTION_ERROR=""

if command -v timeout >/dev/null 2>&1; then
    # Linux/Git Bash: use timeout command (60 seconds should be plenty)
    if VERSIONS_OUTPUT=$(timeout 60 ./.github/scripts/detect-wp-versions.sh 2>&1); then
        DETECTION_ERROR=""
    else
        DETECTION_ERROR=$?
        # timeout returns 124 when command times out
        if [[ "$DETECTION_ERROR" == "124" ]]; then
            echo "   ⚠️  Detection script timed out after 60 seconds"
        fi
    fi
else
    # Systems without timeout command (rare - most Git Bash has it)
    if VERSIONS_OUTPUT=$(./.github/scripts/detect-wp-versions.sh 2>&1); then
        DETECTION_ERROR=""
    else
        DETECTION_ERROR=$?
    fi
fi

# Parse the output (head -1 for defensive parsing in case of duplicate lines)
LATEST_VERSION=$(echo "$VERSIONS_OUTPUT" | grep "^LATEST_VERSION=" | head -1 | cut -d'=' -f2 | tr -d '[:space:]')
PREVIOUS_VERSION=$(echo "$VERSIONS_OUTPUT" | grep "^PREVIOUS_VERSION=" | head -1 | cut -d'=' -f2 | tr -d '[:space:]')

# Validate we got versions; use fallback if not
if [[ -z "$LATEST_VERSION" ]] || [[ -z "$PREVIOUS_VERSION" ]]; then
    echo "   ⚠️  Could not detect versions, using fallback"
    # Provide context about what went wrong
    if [[ -n "$DETECTION_ERROR" ]]; then
        echo "   Detection script failed (exit code $DETECTION_ERROR):"
    elif [[ -z "$VERSIONS_OUTPUT" ]]; then
        echo "   Detection script produced no output"
    else
        echo "   Could not parse versions from output:"
    fi
    echo "$VERSIONS_OUTPUT" | head -20 | sed 's/^/      /'
    
    # Fallback versions - UPDATE THESE when WordPress releases new versions
    # Latest: Current major version from https://wordpress.org/download/
    # Previous: Latest patch of previous major from https://wordpress.org/download/releases/
    LATEST_VERSION="6.9"
    PREVIOUS_VERSION="6.8.3"
else
    echo "   ✅ Detected from WordPress API"
fi

echo "   Latest WordPress: $LATEST_VERSION"
echo "   Previous WordPress: $PREVIOUS_VERSION"

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

# Ensure clean environment now that env vars are set
echo "🧹 Cleaning up any existing test containers/volumes..."
docker compose -f tests/docker/docker-compose.yml \
    -f tests/docker/docker-compose.package.yml \
    down -v --remove-orphans || true
echo "   ✅ Clean start ensured"

# Start MySQL containers early in background for parallel initialization
# Based on testing, MySQL takes ~38 seconds to fully initialize
echo "🗄️ Starting MySQL databases in background for parallel initialization..."
# Only use base compose file for MySQL (package.yml requires package to exist)
docker compose -f tests/docker/docker-compose.yml \
    up -d mysql-latest mysql-previous 2>&1 | tee /tmp/mysql-startup.log &
MYSQL_START_PID=$!
echo "   MySQL containers starting in background (PID: $MYSQL_START_PID)"
echo "   Containers will initialize while we build assets (~38 seconds typical)"

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
    docker run --rm --name shield-node-build \
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

# Build base PHP image for Composer operations (before installing dependencies)
# Uses WP_VERSION=latest to skip WordPress download - we only need PHP + extensions + Composer
# This image is reused for all Composer operations and shares layers with test-runner images
echo "🐳 Building PHP base image for Composer operations..."
COMPOSER_IMAGE="shield-composer-runner:php${PHP_VERSION}"
docker build tests/docker/ \
    --build-arg PHP_VERSION=$PHP_VERSION \
    --build-arg WP_VERSION=latest \
    --tag $COMPOSER_IMAGE || {
    echo "❌ Failed to build Composer runner image"
    exit 1
}
echo "   ✅ Composer runner image built: $COMPOSER_IMAGE"

# Install dependencies using Docker (no local PHP/Composer required)
# Uses the test-runner based image with all PHP extensions pre-installed
echo "📦 Installing dependencies..."
echo "   Using PHP ${PHP_VERSION} with full extension support"

docker run --rm --name shield-composer-root \
    -v "$PROJECT_ROOT:/app" \
    -w /app \
    $COMPOSER_IMAGE \
    composer install --no-interaction --prefer-dist --optimize-autoloader || {
    echo "❌ Root composer install failed"
    exit 1
}

if [ -d "$PROJECT_ROOT/src/lib" ]; then
    docker run --rm --name shield-composer-lib \
        -v "$PROJECT_ROOT:/app" \
        -w /app/src/lib \
        $COMPOSER_IMAGE \
        composer install --no-interaction --prefer-dist --optimize-autoloader || {
        echo "❌ src/lib composer install failed"
        exit 1
    }
fi

echo "   ✅ Dependencies installed"

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
# Uses the same PHP image built earlier with all extensions
echo "   Running Strauss prefixing..."
docker run --rm --name shield-composer-package \
    -v "$PROJECT_ROOT:/app" \
    -w /app \
    -e COMPOSER_PROCESS_TIMEOUT=900 \
    -e SHIELD_STRAUSS_VERSION="$SHIELD_STRAUSS_VERSION" \
    $COMPOSER_IMAGE \
    composer package-plugin -- --output="$PACKAGE_DIR_RELATIVE" \
        --skip-root-composer --skip-lib-composer \
        --skip-npm-install --skip-npm-build \
        --skip-directory-clean --skip-copy || {
    echo "❌ Package build failed"
    exit 1
}

echo "   ✅ Package built at $PACKAGE_DIR"

# Prepare Docker environment directory
echo "⚙️  Setting up Docker environment..."
mkdir -p tests/docker

# Build Docker images for each WordPress version (matching GitHub Actions approach)
build_docker_image_for_wp_version() {
    local WP_VERSION=$1
    local VERSION_NAME=$2
    
    echo "🐳 Building Docker image for PHP $PHP_VERSION + WordPress $WP_VERSION ($VERSION_NAME)..."
    docker build tests/docker/ \
        --build-arg PHP_VERSION=$PHP_VERSION \
        --build-arg WP_VERSION=$WP_VERSION \
        --tag shield-test-runner:php$PHP_VERSION-wp$WP_VERSION
}

# Health check function for MySQL containers
# Based on Task 4.2 testing, MySQL takes ~38 seconds to initialize
wait_for_mysql() {
    local container=$1
    local max_attempts=60  # 60 seconds based on testing
    local attempt=0
    
    echo "⏳ Waiting for $container to be ready (typically ~38 seconds)..."
    while [ $attempt -lt $max_attempts ]; do
        # Use mysqladmin ping to check if MySQL is ready
        if docker exec $container mysqladmin ping -h localhost --silent 2>/dev/null; then
            echo "✅ $container is ready (took $attempt seconds)"
            return 0
        fi
        
        attempt=$((attempt + 1))
        
        # Progress indicator every 10 seconds
        if [ $((attempt % 10)) -eq 0 ]; then
            echo "   Still waiting for $container... ($attempt/$max_attempts seconds)"
        fi
        
        sleep 1
    done
    
    # If we get here, MySQL failed to start
    echo "❌ $container failed to start within $max_attempts seconds"
    echo "   Container logs:"
    docker logs --tail 20 $container
    return 1
}

# Function to run tests in parallel with database isolation
run_parallel_tests() {
    echo "🧪 Running parallel tests with isolated databases..."
    
    # Prepare output directories
    mkdir -p /tmp
    rm -f /tmp/shield-test-latest.log /tmp/shield-test-previous.log /tmp/shield-test-latest.exit /tmp/shield-test-previous.exit
    
    # Set up environment variables for both WordPress versions
    cat > tests/docker/.env << EOF
PHP_VERSION=$PHP_VERSION
WP_VERSION_LATEST=$LATEST_VERSION
WP_VERSION_PREVIOUS=$PREVIOUS_VERSION
TEST_PHP_VERSION=$PHP_VERSION
PLUGIN_SOURCE=$PACKAGE_DIR
SHIELD_PACKAGE_PATH=$PACKAGE_DIR
SHIELD_STRAUSS_VERSION=$SHIELD_STRAUSS_VERSION
SHIELD_TEST_IMAGE_LATEST=shield-test-runner:php$PHP_VERSION-wp$LATEST_VERSION
SHIELD_TEST_IMAGE_PREVIOUS=shield-test-runner:php$PHP_VERSION-wp$PREVIOUS_VERSION
EOF
    
    # Ensure MySQL containers are running (they were started early)
    echo "🗄️ Ensuring MySQL databases are running..."
    # Check if containers are already running from early start
    # Container names are shield-db-latest/shield-db-previous (set via container_name in docker-compose.yml)
    if ! docker ps | grep -q shield-db-latest; then
        echo "   MySQL containers not found, starting them now..."
        docker compose -f tests/docker/docker-compose.yml \
            -f tests/docker/docker-compose.package.yml \
            up -d mysql-latest mysql-previous
    else
        echo "   MySQL containers already running from early initialization"
    fi
    
    # Build test runner images to ensure they're ready
    echo "🔨 Building test runner images..."
    docker compose -f tests/docker/docker-compose.yml \
        -f tests/docker/docker-compose.package.yml \
        build test-runner-latest test-runner-previous
    
    # Wait for both MySQL containers to be ready using health checks
    echo "⏳ Ensuring MySQL databases are ready for testing..."
    # Container names are shield-db-latest/shield-db-previous (set via container_name in docker-compose.yml)
    MYSQL_LATEST_CONTAINER=$(docker ps --filter "name=shield-db-latest" --format "{{.Names}}" | head -1)
    MYSQL_PREVIOUS_CONTAINER=$(docker ps --filter "name=shield-db-previous" --format "{{.Names}}" | head -1)
    
    if [ -z "$MYSQL_LATEST_CONTAINER" ]; then
        echo "❌ Cannot find shield-db-latest container"
        docker ps -a | grep shield-db
        exit 1
    fi
    
    if [ -z "$MYSQL_PREVIOUS_CONTAINER" ]; then
        echo "❌ Cannot find shield-db-previous container"
        docker ps -a | grep shield-db
        exit 1
    fi
    
    echo "   Found MySQL containers: $MYSQL_LATEST_CONTAINER, $MYSQL_PREVIOUS_CONTAINER"
    
    wait_for_mysql "$MYSQL_LATEST_CONTAINER" || exit 1
    wait_for_mysql "$MYSQL_PREVIOUS_CONTAINER" || exit 1
    echo "✅ Both MySQL databases are ready!"
    
    # Layer 1 Verification: Verify containers are ready
    echo "🔍 Layer 1 Verification: Checking database containers..."
    # Container names are shield-db-latest/shield-db-previous (set via container_name in docker-compose.yml)
    docker ps --filter "name=shield-db-latest" --filter "name=shield-db-previous" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    
    # Record start time for performance measurement
    PARALLEL_START_TIME=$(date +%s)
    echo "⏱️  Parallel execution started at: $(date)"
    
    # Initialize exit code tracking
    declare -A EXIT_CODES
    declare -A PROCESS_PIDS
    
    # Run WordPress Latest tests in background with output capture
    echo "🚀 Starting WordPress $LATEST_VERSION test stream..."
    (
        echo "[LATEST] Starting tests for WordPress $LATEST_VERSION at $(date)" > /tmp/shield-test-latest.log
        echo "[LATEST] Command: docker compose run test-runner-wp682" >> /tmp/shield-test-latest.log
        echo "[LATEST] ===============================================" >> /tmp/shield-test-latest.log
        docker compose -f tests/docker/docker-compose.yml \
            -f tests/docker/docker-compose.package.yml \
            run --rm test-runner-latest >> /tmp/shield-test-latest.log 2>&1
        echo $? > /tmp/shield-test-latest.exit
        echo "[LATEST] Tests completed at $(date) with exit code $(cat /tmp/shield-test-latest.exit)" >> /tmp/shield-test-latest.log
    ) &
    LATEST_PID=$!
    PROCESS_PIDS["latest"]=$LATEST_PID
    echo "📱 WordPress Latest stream started (PID: $LATEST_PID)"
    
    # Run WordPress Previous tests in background with output capture
    echo "🚀 Starting WordPress $PREVIOUS_VERSION test stream..."
    (
        echo "[PREVIOUS] Starting tests for WordPress $PREVIOUS_VERSION at $(date)" > /tmp/shield-test-previous.log
        echo "[PREVIOUS] Command: docker compose run test-runner-wp673" >> /tmp/shield-test-previous.log
        echo "[PREVIOUS] ===============================================" >> /tmp/shield-test-previous.log
        docker compose -f tests/docker/docker-compose.yml \
            -f tests/docker/docker-compose.package.yml \
            run --rm test-runner-previous >> /tmp/shield-test-previous.log 2>&1
        echo $? > /tmp/shield-test-previous.exit
        echo "[PREVIOUS] Tests completed at $(date) with exit code $(cat /tmp/shield-test-previous.exit)" >> /tmp/shield-test-previous.log
    ) &
    PREVIOUS_PID=$!
    PROCESS_PIDS["previous"]=$PREVIOUS_PID
    echo "📱 WordPress Previous stream started (PID: $PREVIOUS_PID)"
    
    # Layer 2 Verification: Monitor parallel execution
    echo "🔍 Layer 2 Verification: Monitoring parallel execution..."
    echo "   Active background processes: Latest PID $LATEST_PID, Previous PID $PREVIOUS_PID"
    
    # Monitor process startup with retries
    echo "   Monitoring process startup..."
    for i in {1..10}; do
        sleep 2
        LATEST_RUNNING=$(ps -p $LATEST_PID > /dev/null 2>&1 && echo "Running" || echo "Stopped")
        PREVIOUS_RUNNING=$(ps -p $PREVIOUS_PID > /dev/null 2>&1 && echo "Running" || echo "Stopped")
        echo "     Check $i: Latest PID $LATEST_PID ($LATEST_RUNNING), Previous PID $PREVIOUS_PID ($PREVIOUS_RUNNING)"
        
        if [ "$i" = "5" ]; then
            echo "   Docker containers during execution:"
            docker ps --filter "name=shield-test" --format "table {{.Names}}\t{{.Status}}\t{{.Image}}" || echo "     No test containers found yet"
            docker ps --filter "name=shield-db" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" || echo "     No database containers found"
        fi
        
        # Break early if both processes have finished
        if [ "$LATEST_RUNNING" = "Stopped" ] && [ "$PREVIOUS_RUNNING" = "Stopped" ]; then
            echo "     Both processes completed early at check $i"
            break
        fi
        
        # Show progress indicator
        if [ $((i % 2)) -eq 0 ]; then
            echo "     ⏳ Processes still running, continuing to monitor..."
        fi
    done
    
    # Start continuous monitoring in background during test execution
    if [ "$DEBUG_MODE" = "true" ]; then
        (
            while ps -p $LATEST_PID > /dev/null 2>&1 || ps -p $PREVIOUS_PID > /dev/null 2>&1; do
                echo "[$(date +%H:%M:%S)] Monitoring: Latest PID $LATEST_PID ($(ps -p $LATEST_PID > /dev/null 2>&1 && echo "Running" || echo "Stopped")), Previous PID $PREVIOUS_PID ($(ps -p $PREVIOUS_PID > /dev/null 2>&1 && echo "Running" || echo "Stopped"))"
                sleep 30
            done
        ) &
        MONITOR_PID=$!
    fi
    
    # Wait for both test suites to complete and collect exit codes
    echo "⏳ Waiting for parallel test streams to complete..."
    
    # Wait for Latest stream
    echo "   Waiting for WordPress Latest stream (PID: $LATEST_PID)..."
    wait $LATEST_PID
    if [ -f /tmp/shield-test-latest.exit ]; then
        EXIT_CODES["latest"]=$(cat /tmp/shield-test-latest.exit)
    else
        EXIT_CODES["latest"]=1  # Default to failure if exit file missing
    fi
    
    # Wait for Previous stream
    echo "   Waiting for WordPress Previous stream (PID: $PREVIOUS_PID)..."
    wait $PREVIOUS_PID
    if [ -f /tmp/shield-test-previous.exit ]; then
        EXIT_CODES["previous"]=$(cat /tmp/shield-test-previous.exit)
    else
        EXIT_CODES["previous"]=1  # Default to failure if exit file missing
    fi
    
    # Stop monitoring process if it was started
    if [ -n "${MONITOR_PID:-}" ]; then
        kill $MONITOR_PID 2>/dev/null || true
    fi
    
    # Record end time and calculate duration
    PARALLEL_END_TIME=$(date +%s)
    PARALLEL_DURATION=$((PARALLEL_END_TIME - PARALLEL_START_TIME))
    echo "⏱️  Parallel execution completed at: $(date)"
    echo "⏱️  Total parallel execution time: ${PARALLEL_DURATION} seconds ($(date -d@$PARALLEL_DURATION -u +%M:%S) mm:ss)"
    
    # Layer 3 Verification: Check results and timing
    echo "🔍 Layer 3 Verification: Analyzing results..."
    echo "   Latest stream exit code: ${EXIT_CODES[latest]}"
    echo "   Previous stream exit code: ${EXIT_CODES[previous]}"
    echo "   Log file sizes:"
    [ -f /tmp/shield-test-latest.log ] && echo "     Latest: $(wc -l < /tmp/shield-test-latest.log) lines"
    [ -f /tmp/shield-test-previous.log ] && echo "     Previous: $(wc -l < /tmp/shield-test-previous.log) lines"
    
    # Display results sequentially to prevent interleaving
    echo ""
    echo "📊 RESULTS SUMMARY"
    echo "=================="
    echo "WordPress $LATEST_VERSION Results (Exit Code: ${EXIT_CODES[latest]}):"
    echo "---------------------------------------------------------------"
    if [ -f /tmp/shield-test-latest.log ]; then
        tail -20 /tmp/shield-test-latest.log
    else
        echo "❌ Latest test log file not found"
    fi
    
    echo ""
    echo "WordPress $PREVIOUS_VERSION Results (Exit Code: ${EXIT_CODES[previous]}):"
    echo "---------------------------------------------------------------"
    if [ -f /tmp/shield-test-previous.log ]; then
        tail -20 /tmp/shield-test-previous.log
    else
        echo "❌ Previous test log file not found"
    fi
    
    # Aggregate exit codes for CI compatibility
    OVERALL_EXIT_CODE=0
    if [ "${EXIT_CODES[latest]}" != "0" ] || [ "${EXIT_CODES[previous]}" != "0" ]; then
        OVERALL_EXIT_CODE=1
        echo ""
        echo "❌ FAILURE: One or more test streams failed"
        echo "   WordPress $LATEST_VERSION: ${EXIT_CODES[latest]} ($([ "${EXIT_CODES[latest]}" = "0" ] && echo "✅ PASS" || echo "❌ FAIL"))"
        echo "   WordPress $PREVIOUS_VERSION: ${EXIT_CODES[previous]} ($([ "${EXIT_CODES[previous]}" = "0" ] && echo "✅ PASS" || echo "❌ FAIL"))"
    else
        echo ""
        echo "✅ SUCCESS: All test streams passed"
        echo "   WordPress $LATEST_VERSION: ✅ PASS"
        echo "   WordPress $PREVIOUS_VERSION: ✅ PASS"
    fi
    
    echo ""
    echo "🏁 Parallel execution verification complete!"
    echo "   Performance: ${PARALLEL_DURATION}s (target: ~210s for 50% improvement)"
    echo "   Full logs available at:"
    echo "     - Latest: /tmp/shield-test-latest.log"
    echo "     - Previous: /tmp/shield-test-previous.log"
    
    # Return overall exit code for CI compatibility
    return $OVERALL_EXIT_CODE
}

# Build Docker images for both WordPress versions
echo "🏗️ Building Docker images for all WordPress versions..."
build_docker_image_for_wp_version "$LATEST_VERSION" "latest"
build_docker_image_for_wp_version "$PREVIOUS_VERSION" "previous"

# Enable debug mode for detailed monitoring
DEBUG_MODE=${DEBUG_MODE:-"false"}

if [ "$DEBUG_MODE" = "true" ]; then
    echo "🔍 Debug mode enabled - will show detailed process monitoring"
    set -x  # Enable bash debug output
fi

# Initialize overall exit code for the entire script
OVERALL_SCRIPT_EXIT=0

# Run tests in parallel with database isolation
echo "🚀 Starting parallel execution mode..."
if ! run_parallel_tests; then
    OVERALL_SCRIPT_EXIT=1
    echo "❌ Parallel testing failed - check logs above for details"
fi

# Cleanup
echo "🧹 Cleaning up..."
docker compose -f tests/docker/docker-compose.yml \
    -f tests/docker/docker-compose.package.yml \
    down -v --remove-orphans || true
rm -f tests/docker/.env

echo ""
if [ "$OVERALL_SCRIPT_EXIT" = "0" ]; then
    echo "✅ Local Docker tests completed successfully!"
else
    echo "❌ Local Docker tests completed with failures!"
fi

echo "   Tests ran in PARALLEL mode with isolated databases:"
echo "   - WordPress $LATEST_VERSION (shield-db-latest:3309, database: wordpress_test_latest)"
echo "   - WordPress $PREVIOUS_VERSION (shield-db-previous:3310, database: wordpress_test_previous)"
echo "   - Execution mode: Parallel"
echo "   - Output logs: /tmp/shield-test-*.log"
echo "   - Package testing mode (production validation)"

# Exit with the appropriate code for CI compatibility
exit $OVERALL_SCRIPT_EXIT