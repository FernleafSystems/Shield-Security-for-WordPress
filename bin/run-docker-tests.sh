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

# Set environment variables early to avoid Docker Compose warnings
PACKAGE_DIR="/tmp/shield-package-local"
export SHIELD_PACKAGE_PATH="$PACKAGE_DIR"
# PLUGIN_SOURCE needs to be the actual path, not just "package"
export PLUGIN_SOURCE="$PACKAGE_DIR"

# Start MySQL containers early in background for parallel initialization
# Based on testing, MySQL takes ~38 seconds to fully initialize
echo "🗄️ Starting MySQL databases in background for parallel initialization..."
# Only use base compose file for MySQL (package.yml requires package to exist)
docker compose -f tests/docker/docker-compose.yml \
    up -d mysql-latest mysql-previous 2>&1 | tee /tmp/mysql-startup.log &
MYSQL_START_PID=$!
echo "   MySQL containers starting in background (PID: $MYSQL_START_PID)"
echo "   Containers will initialize while we build assets (~38 seconds typical)"

# Build assets (like CI does) - with caching for Task 4.6 optimization
echo "🔨 Building assets..."
if command -v npm >/dev/null 2>&1; then
    # Task 4.6: Check if webpack build cache is valid using checksums (more reliable than timestamps)
    WEBPACK_CACHE_VALID=false
    DIST_DIR="$PROJECT_ROOT/assets/dist"
    SRC_DIR="$PROJECT_ROOT/assets/js"
    CACHE_FILE="/tmp/shield-webpack-cache-checksum"
    
    # Check if dist directory exists and has files
    if [ -d "$DIST_DIR" ] && [ "$(ls -A $DIST_DIR 2>/dev/null)" ]; then
        echo "   Checking webpack build cache validity..."
        
        # Calculate checksum of all source files, package.json, and webpack.config.js
        CURRENT_CHECKSUM=$(find "$SRC_DIR" -type f \( -name "*.js" -o -name "*.jsx" -o -name "*.ts" -o -name "*.tsx" \) -exec md5sum {} \; 2>/dev/null | sort | md5sum | cut -d' ' -f1)
        PACKAGE_CHECKSUM=$(md5sum "$PROJECT_ROOT/package.json" 2>/dev/null | cut -d' ' -f1)
        WEBPACK_CHECKSUM=$(md5sum "$PROJECT_ROOT/webpack.config.js" 2>/dev/null | cut -d' ' -f1)
        COMBINED_CHECKSUM="${CURRENT_CHECKSUM}-${PACKAGE_CHECKSUM}-${WEBPACK_CHECKSUM}"
        
        # Check if we have a stored checksum and if it matches
        if [ -f "$CACHE_FILE" ]; then
            STORED_CHECKSUM=$(cat "$CACHE_FILE")
            if [ "$COMBINED_CHECKSUM" = "$STORED_CHECKSUM" ]; then
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
    
    if [ "$WEBPACK_CACHE_VALID" = false ]; then
        echo "   Cache invalid or missing - running full build..."
        npm ci --no-audit --no-fund
        npm run build
        
        # Save the checksum for next run
        echo "$COMBINED_CHECKSUM" > "$CACHE_FILE"
        echo "   Build complete - cache checksum saved for next run"
    else
        echo "   Using cached webpack build from previous run"
        # Still need to ensure node_modules exist
        if [ ! -d "node_modules" ]; then
            echo "   Installing npm dependencies (node_modules missing)..."
            npm ci --no-audit --no-fund
        fi
    fi
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
# PACKAGE_DIR already set earlier to avoid Docker Compose warnings
rm -rf "$PACKAGE_DIR"
composer package-plugin -- --output="$PACKAGE_DIR" --skip-root-composer --skip-lib-composer --skip-npm-install --skip-npm-build

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
    
    # Set up environment variables for both WordPress versions
    cat > tests/docker/.env << EOF
PHP_VERSION=7.4
WP_VERSION_LATEST=$LATEST_VERSION
WP_VERSION_PREVIOUS=$PREVIOUS_VERSION
TEST_PHP_VERSION=7.4
PLUGIN_SOURCE=$PACKAGE_DIR
SHIELD_PACKAGE_PATH=$PACKAGE_DIR
SHIELD_TEST_IMAGE_LATEST=shield-test-runner:wp-$LATEST_VERSION
SHIELD_TEST_IMAGE_PREVIOUS=shield-test-runner:wp-$PREVIOUS_VERSION
EOF
    
    # Ensure MySQL containers are running (they were started early)
    echo "🗄️ Ensuring MySQL databases are running..."
    # Check if containers are already running from early start
    if ! docker ps | grep -q mysql-latest; then
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
    # Docker Compose appends suffixes to container names, find the actual names
    MYSQL_LATEST_CONTAINER=$(docker ps --filter "name=mysql-latest" --format "{{.Names}}" | head -1)
    MYSQL_PREVIOUS_CONTAINER=$(docker ps --filter "name=mysql-previous" --format "{{.Names}}" | head -1)
    
    if [ -z "$MYSQL_LATEST_CONTAINER" ]; then
        echo "❌ Cannot find mysql-latest container"
        docker ps -a | grep mysql
        exit 1
    fi
    
    if [ -z "$MYSQL_PREVIOUS_CONTAINER" ]; then
        echo "❌ Cannot find mysql-previous container"
        docker ps -a | grep mysql
        exit 1
    fi
    
    echo "   Found MySQL containers: $MYSQL_LATEST_CONTAINER, $MYSQL_PREVIOUS_CONTAINER"
    
    wait_for_mysql "$MYSQL_LATEST_CONTAINER" || exit 1
    wait_for_mysql "$MYSQL_PREVIOUS_CONTAINER" || exit 1
    echo "✅ Both MySQL databases are ready!"
    
    # Layer 1 Verification: Verify containers are ready
    echo "🔍 Layer 1 Verification: Checking database containers..."
    # Container names per Phase 2.5 - generic names for CI compatibility
    docker ps --filter "name=mysql-latest" --filter "name=mysql-previous" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    
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
            docker ps --filter "name=test-runner" --format "table {{.Names}}\t{{.Status}}\t{{.Image}}" || echo "     No test-runner containers found yet"
            docker ps --filter "name=mysql" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" || echo "     No mysql containers found"
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

# Function to run tests with specific WordPress version (legacy support)
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
        -f tests/docker/docker-compose.package.yml \
        run --rm -T test-runner
}

# Build Docker images for both WordPress versions
echo "🏗️ Building Docker images for all WordPress versions..."
build_docker_image_for_wp_version "$LATEST_VERSION" "latest"
build_docker_image_for_wp_version "$PREVIOUS_VERSION" "previous"

# Check if parallel testing is supported (default: yes)
PARALLEL_TESTING=${PARALLEL_TESTING:-"true"}

# Enable debug mode for detailed monitoring
DEBUG_MODE=${DEBUG_MODE:-"false"}

if [ "$DEBUG_MODE" = "true" ]; then
    echo "🔍 Debug mode enabled - will show detailed process monitoring"
    set -x  # Enable bash debug output
fi

# Initialize overall exit code for the entire script
OVERALL_SCRIPT_EXIT=0

if [ "$PARALLEL_TESTING" = "true" ]; then
    # Run tests in parallel with database isolation
    echo "🚀 Starting parallel execution mode..."
    if ! run_parallel_tests; then
        OVERALL_SCRIPT_EXIT=1
        echo "❌ Parallel testing failed - check logs above for details"
    fi
else
    # Fallback to sequential execution for compatibility
    echo "📌 Running tests sequentially (PARALLEL_TESTING=false)"
    if ! run_tests_for_wp_version "$LATEST_VERSION" "latest"; then
        OVERALL_SCRIPT_EXIT=1
        echo "❌ WordPress $LATEST_VERSION tests failed"
    fi
    if ! run_tests_for_wp_version "$PREVIOUS_VERSION" "previous"; then
        OVERALL_SCRIPT_EXIT=1
        echo "❌ WordPress $PREVIOUS_VERSION tests failed"
    fi
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

if [ "$PARALLEL_TESTING" = "true" ]; then
    echo "   Tests ran in PARALLEL mode with isolated databases:"
    echo "   - WordPress $LATEST_VERSION (mysql-latest:3309, database: wordpress_test_latest)"
    echo "   - WordPress $PREVIOUS_VERSION (mysql-previous:3310, database: wordpress_test_previous)"
    echo "   - Execution mode: Parallel (faster)"
    echo "   - Output logs: /tmp/shield-test-*.log"
else
    echo "   Tests ran with the same configuration as CI (sequential):"
    echo "   - PHP 7.4 + WordPress $LATEST_VERSION"
    echo "   - PHP 7.4 + WordPress $PREVIOUS_VERSION"
fi
echo "   - Package testing mode (production validation)"

# Exit with the appropriate code for CI compatibility
exit $OVERALL_SCRIPT_EXIT