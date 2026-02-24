#!/bin/bash
# Simple Local Docker Test Runner
# Runs the exact same tests as CI/CD - no manual setup required

set -e

ANALYZE_PACKAGE_MODE=false

for arg in "$@"; do
    case "$arg" in
        --analyze-package)
            ANALYZE_PACKAGE_MODE=true
            ;;
        --help|-h)
            echo "Usage: $0 [--analyze-package]"
            echo ""
            echo "Modes:"
            echo "  (default)          Build package and run parallel Docker test suites"
            echo "  --analyze-package  Build package and run packaged PHPStan analysis"
            echo ""
            echo "Source:"
            echo "  HEAD-only          Build and test from a clean HEAD snapshot (local changes are ignored)"
            exit 0
            ;;
        *)
            echo "Error: Unknown argument: $arg"
            echo "Use --help for usage."
            exit 1
            ;;
    esac
done

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

# Enable Docker BuildKit for cache mount support
# BuildKit provides automatic caching of apt packages and composer dependencies
# Cache is created automatically if missing, reused on subsequent builds
export DOCKER_BUILDKIT=1

echo "🚀 Starting Local Docker Tests (matching CI configuration)"
if [ "$ANALYZE_PACKAGE_MODE" = true ]; then
    echo "   Mode: Packaged PHPStan analysis"
else
    echo "   Mode: Parallel package test suites"
fi
echo "=================================================="

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CALLER_PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
HEAD_SNAPSHOT_DIR="$CALLER_PROJECT_ROOT/tmp/shield-head-snapshot"
HEAD_INDEX_FILE="$CALLER_PROJECT_ROOT/tmp/shield-head-snapshot.index"
HEAD_SNAPSHOT_RELATIVE="tmp/shield-head-snapshot"
HEAD_INDEX_RELATIVE="tmp/shield-head-snapshot.index"

HEAD_COMMIT="$(cd "$CALLER_PROJECT_ROOT" >/dev/null 2>&1 && git rev-parse HEAD 2>/dev/null || true)"

if [ -z "$HEAD_COMMIT" ]; then
    echo "❌ Error: Unable to resolve HEAD commit at $CALLER_PROJECT_ROOT"
    exit 1
fi

prepare_head_snapshot() {
    mkdir -p "$CALLER_PROJECT_ROOT/tmp"
    rm -rf "$HEAD_SNAPSHOT_DIR"
    rm -f "$HEAD_INDEX_FILE"
    mkdir -p "$HEAD_SNAPSHOT_DIR"

    if ! (
        cd "$CALLER_PROJECT_ROOT" >/dev/null 2>&1 &&
        GIT_INDEX_FILE="$HEAD_INDEX_RELATIVE" git read-tree HEAD &&
        GIT_INDEX_FILE="$HEAD_INDEX_RELATIVE" git checkout-index -a -f --prefix="$HEAD_SNAPSHOT_RELATIVE/"
    ); then
        echo "❌ Error: Failed to export HEAD snapshot to $HEAD_SNAPSHOT_DIR"
        rm -f "$HEAD_INDEX_FILE"
        exit 1
    fi

    rm -f "$HEAD_INDEX_FILE"

    if [ ! -f "$HEAD_SNAPSHOT_DIR/icwp-wpsf.php" ]; then
        echo "❌ Error: HEAD snapshot missing required file: icwp-wpsf.php"
        exit 1
    fi
    if [ ! -d "$HEAD_SNAPSHOT_DIR/tests" ]; then
        echo "❌ Error: HEAD snapshot missing required directory: tests"
        exit 1
    fi
    if [ ! -d "$HEAD_SNAPSHOT_DIR/.github" ]; then
        echo "❌ Error: HEAD snapshot missing required directory: .github"
        exit 1
    fi
}

prepare_head_snapshot

PROJECT_ROOT="$HEAD_SNAPSHOT_DIR"
cd "$PROJECT_ROOT"

echo "   Source: HEAD-only snapshot"
echo "   HEAD Commit: $HEAD_COMMIT"
echo "   Effective Project Root: $PROJECT_ROOT"

# Disable MSYS/Git Bash path conversion for Docker path arguments
# Prevents /app from being converted to C:/Program Files/Git/app
export MSYS_NO_PATHCONV=1

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

LATEST_VERSION=""
PREVIOUS_VERSION=""

if [ "$ANALYZE_PACKAGE_MODE" = false ]; then
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
        echo "❌ Could not parse WordPress versions from detect-wp-versions.sh"
        # Provide context about what went wrong
        if [[ -n "$DETECTION_ERROR" ]]; then
            echo "   Detection script failed (exit code $DETECTION_ERROR):"
        elif [[ -z "$VERSIONS_OUTPUT" ]]; then
            echo "   Detection script produced no output"
        else
            echo "   Could not parse versions from output:"
        fi
        echo "$VERSIONS_OUTPUT" | head -20 | sed 's/^/      /'
        exit 1
    else
        echo "   ✅ Version detection script returned valid versions"
    fi

    echo "   Latest WordPress: $LATEST_VERSION"
    echo "   Previous WordPress: $PREVIOUS_VERSION"
fi

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

# Local cache paths (all under tmp/ to keep cache lifecycle local to workspace)
WEBPACK_CACHE_FILE="$CALLER_PROJECT_ROOT/tmp/.shield-webpack-cache-checksum"
COMPOSER_ROOT_CACHE_FILE="$CALLER_PROJECT_ROOT/tmp/.shield-composer-root-cache-checksum"
PACKAGE_DEPS_CACHE_ROOT="$CALLER_PROJECT_ROOT/tmp/.shield-cache/package-deps"

hash_file_or_missing() {
    local file_path=$1
    if [ -f "$file_path" ]; then
        md5sum "$file_path" | cut -d' ' -f1
    else
        echo "missing"
    fi
}

hash_find_tree() {
    local root_path=$1
    shift

    if [ ! -d "$root_path" ]; then
        echo "missing"
        return 0
    fi

    find "$root_path" -type f \( "$@" \) -exec md5sum {} \; 2>/dev/null | sort | md5sum | cut -d' ' -f1
}

# Ensure clean environment now that env vars are set
if [ "$ANALYZE_PACKAGE_MODE" = false ]; then
    echo "🧹 Cleaning up any existing test containers/volumes..."
    docker compose -f tests/docker/docker-compose.yml \
        -f tests/docker/docker-compose.package.yml \
        down -v --remove-orphans || true
    echo "   ✅ Clean start ensured"
fi

# Start MySQL containers early in background for parallel initialization
# Based on testing, MySQL takes ~38 seconds to fully initialize
if [ "$ANALYZE_PACKAGE_MODE" = false ]; then
    echo "🗄️ Starting MySQL databases in background for parallel initialization..."
    # Only use base compose file for MySQL (package.yml requires package to exist)
    docker compose -f tests/docker/docker-compose.yml \
        up -d mysql-latest mysql-previous 2>&1 | tee /tmp/mysql-startup.log &
    MYSQL_START_PID=$!
    echo "   MySQL containers starting in background (PID: $MYSQL_START_PID)"
    echo "   Containers will initialize while we build assets (~38 seconds typical)"
fi

# Build assets using Docker (no local Node.js required) - with caching
echo "🔨 Building assets..."
DIST_DIR="$PROJECT_ROOT/assets/dist"
JS_SRC_DIR="$PROJECT_ROOT/assets/js"
CSS_SRC_DIR="$PROJECT_ROOT/assets/css"

# Ensure tmp directory exists
mkdir -p "$PROJECT_ROOT/tmp"

# Check if webpack build cache is valid
WEBPACK_CACHE_VALID=false
JS_CHECKSUM=$(hash_find_tree "$JS_SRC_DIR" -name "*.js" -o -name "*.jsx" -o -name "*.ts" -o -name "*.tsx")
CSS_CHECKSUM=$(hash_find_tree "$CSS_SRC_DIR" -name "*.css" -o -name "*.scss" -o -name "*.sass")
PACKAGE_JSON_CHECKSUM=$(hash_file_or_missing "$PROJECT_ROOT/package.json")
PACKAGE_LOCK_CHECKSUM=$(hash_file_or_missing "$PROJECT_ROOT/package-lock.json")
WEBPACK_CHECKSUM=$(hash_file_or_missing "$PROJECT_ROOT/webpack.config.js")
POSTCSS_CHECKSUM=$(hash_file_or_missing "$PROJECT_ROOT/postcss.config.js")
WEBPACK_COMBINED_CHECKSUM="${JS_CHECKSUM}-${CSS_CHECKSUM}-${PACKAGE_JSON_CHECKSUM}-${PACKAGE_LOCK_CHECKSUM}-${WEBPACK_CHECKSUM}-${POSTCSS_CHECKSUM}"

if [ -d "$DIST_DIR" ] && [ "$(ls -A "$DIST_DIR" 2>/dev/null)" ]; then
    echo "   Checking webpack build cache..."
    if [ -f "$WEBPACK_CACHE_FILE" ]; then
        STORED_CHECKSUM=$(cat "$WEBPACK_CACHE_FILE" 2>/dev/null)
        if [ "$WEBPACK_COMBINED_CHECKSUM" = "$STORED_CHECKSUM" ]; then
            if [ -f "$DIST_DIR/shield-main.bundle.js" ] && [ -f "$DIST_DIR/shield-main.bundle.css" ]; then
                WEBPACK_CACHE_VALID=true
                echo "   Cache valid - skipping rebuild"
            fi
        fi
    fi
fi

if [ "$WEBPACK_CACHE_VALID" = false ]; then
    echo "   Building assets via Docker..."
    # Anonymous volume (-v /app/node_modules) keeps Linux-native node_modules
    # inside the container only, preventing it from overwriting the host's
    # Windows node_modules directory. Build output (assets/dist) still writes
    # to the host via the main volume mount.
    docker run --rm --name shield-node-build \
        -v "$PROJECT_ROOT:/app" \
        -v /app/node_modules \
        -w /app \
        node:20.10 \
        sh -c "npm ci --no-audit --no-fund && npm run build" || {
        echo "❌ Asset build failed"
        exit 1
    }
    # Save checksum
    echo "$WEBPACK_COMBINED_CHECKSUM" > "$WEBPACK_CACHE_FILE"
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

COMPOSER_ROOT_CHECKSUM=$( (
    hash_file_or_missing "$PROJECT_ROOT/composer.json"
    hash_file_or_missing "$PROJECT_ROOT/composer.lock"
    echo "php:${PHP_VERSION}"
) | md5sum | cut -d' ' -f1 )

COMPOSER_CACHE_VALID=false
if [ -f "$COMPOSER_ROOT_CACHE_FILE" ] && [ -f "$PROJECT_ROOT/vendor/autoload.php" ]; then
    CACHED_COMPOSER_ROOT_CHECKSUM=$(cat "$COMPOSER_ROOT_CACHE_FILE" 2>/dev/null)
    if [ "$COMPOSER_ROOT_CHECKSUM" = "$CACHED_COMPOSER_ROOT_CHECKSUM" ]; then
        COMPOSER_CACHE_VALID=true
        echo "   Cache valid - skipping root composer install"
    fi
fi

if [ "$COMPOSER_CACHE_VALID" = false ]; then
    docker run --rm --name shield-composer-root \
        -v "$PROJECT_ROOT:/app" \
        -w /app \
        $COMPOSER_IMAGE \
        composer install --no-interaction --prefer-dist --optimize-autoloader || {
        echo "Root composer install failed"
        exit 1
    }
    echo "$COMPOSER_ROOT_CHECKSUM" > "$COMPOSER_ROOT_CACHE_FILE"
fi

echo "   Dependencies ready"

# Build plugin package
echo "Building plugin package..."

prepare_package_dir() {
    # Clean and create package directory
    rm -rf "$PACKAGE_DIR"
    mkdir -p "$PACKAGE_DIR"

    # Export tracked files using git archive (respects .gitattributes export-ignore)
    # This is MUCH faster than PHP file-by-file copying
    echo "   Exporting files via git archive..."
    ( cd "$CALLER_PROJECT_ROOT" && git archive HEAD ) | tar -x -C "$PACKAGE_DIR" || {
        echo "Error: git archive failed"
        return 1
    }

    # Verify archive extraction produced expected files
    if [ ! -f "$PACKAGE_DIR/icwp-wpsf.php" ]; then
        echo "Error: git archive extraction failed - main plugin file not found"
        return 1
    fi
    echo "   Files exported (verified)"

    # Copy built assets (gitignored but needed)
    if [ -d "$PROJECT_ROOT/assets/dist" ]; then
        echo "   Copying built assets..."
        cp -r "$PROJECT_ROOT/assets/dist" "$PACKAGE_DIR/assets/dist" || {
            echo "Error: failed to copy assets/dist"
            return 1
        }
        # Verify expected bundle files exist
        if [ ! -f "$PACKAGE_DIR/assets/dist/shield-main.bundle.js" ] || \
           [ ! -f "$PACKAGE_DIR/assets/dist/shield-main.bundle.css" ]; then
            echo "Warning: assets/dist copied but expected bundle files not found"
            echo "   Run 'npm run build' first to generate assets"
        fi
        echo "   Assets copied"
    fi
}

prepare_package_dir || exit 1

# Validate PACKAGE_DIR_RELATIVE is set (defined in section 3.3.2)
if [ -z "$PACKAGE_DIR_RELATIVE" ]; then
    echo "❌ Error: PACKAGE_DIR_RELATIVE not set"
    exit 1
fi

# Run Strauss and post-processing via Docker
# Uses the same PHP image built earlier with all extensions
PACKAGE_DEPS_KEY=$( (
    hash_file_or_missing "$PROJECT_ROOT/composer.json"
    hash_file_or_missing "$PROJECT_ROOT/composer.lock"
    hash_file_or_missing "$PROJECT_ROOT/.github/config/packager.conf"
    echo "php:${PHP_VERSION}"
) | md5sum | cut -d' ' -f1 )
PACKAGE_DEPS_CACHE_DIR="$PACKAGE_DEPS_CACHE_ROOT/$PACKAGE_DEPS_KEY"
mkdir -p "$PACKAGE_DEPS_CACHE_ROOT"
PACKAGE_DEPS_CACHE_HIT=false

if [ -f "$PACKAGE_DEPS_CACHE_DIR/vendor/autoload.php" ]; then
    echo "   Restoring cached package dependencies..."
    rm -rf "$PACKAGE_DIR/vendor"
    cp -a "$PACKAGE_DEPS_CACHE_DIR/vendor" "$PACKAGE_DIR/vendor"
    PACKAGE_DEPS_CACHE_HIT=true
fi

run_package_build() {
    local -a package_args=(
        "--output=$PACKAGE_DIR_RELATIVE"
        "--skip-root-composer"
        "--skip-lib-composer"
        "--skip-npm-install"
        "--skip-npm-build"
        "--skip-directory-clean"
        "--skip-copy"
    )

    docker run --rm --name shield-composer-package \
        -v "$PROJECT_ROOT:/app" \
        -w /app \
        -e COMPOSER_PROCESS_TIMEOUT=900 \
        -e SHIELD_STRAUSS_VERSION="$SHIELD_STRAUSS_VERSION" \
        -e SHIELD_STRAUSS_FORK_REPO="${SHIELD_STRAUSS_FORK_REPO:-}" \
        $COMPOSER_IMAGE \
        composer package-plugin -- "${package_args[@]}"
}

echo "   Running package dependency build (composer + Strauss prefixing)..."
if [ "$PACKAGE_DEPS_CACHE_HIT" = true ]; then
    if ! run_package_build; then
        echo "   Cached package dependencies failed package build; retrying after package reset..."
        prepare_package_dir || exit 1
        run_package_build || {
            echo "Package build failed"
            exit 1
        }
    fi
else
    run_package_build || {
        echo "Package build failed"
        exit 1
    }
fi

if [ -f "$PACKAGE_DIR/vendor/autoload.php" ]; then
    mkdir -p "$PACKAGE_DEPS_CACHE_DIR"
    rm -rf "$PACKAGE_DEPS_CACHE_DIR/vendor" "$PACKAGE_DEPS_CACHE_DIR/vendor_prefixed"
    cp -a "$PACKAGE_DIR/vendor" "$PACKAGE_DEPS_CACHE_DIR/vendor"
fi

echo "   Package built at $PACKAGE_DIR"

# Prepare Docker environment directory
echo "⚙️  Setting up Docker environment..."
mkdir -p tests/docker

run_packaged_phpstan() {
    local project_root_for_php="$PROJECT_ROOT"
    local package_dir_for_php="$PACKAGE_DIR"

    # When running under Git Bash on Windows, PHP may be a native Windows binary.
    # Convert POSIX-style /d/... paths to Windows-native D:/... paths for PHP args.
    if command -v cygpath >/dev/null 2>&1; then
        project_root_for_php="$(cygpath -m "$PROJECT_ROOT")"
        package_dir_for_php="$(cygpath -m "$PACKAGE_DIR")"
    fi

    php "./bin/run-packaged-phpstan.php" \
        --project-root="$project_root_for_php" \
        --composer-image="$COMPOSER_IMAGE" \
        --package-dir="$package_dir_for_php" \
        --package-dir-relative="$PACKAGE_DIR_RELATIVE"
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
SHIELD_STRAUSS_FORK_REPO=${SHIELD_STRAUSS_FORK_REPO:-}
SHIELD_TEST_IMAGE_LATEST=shield-test-runner:php$PHP_VERSION-wp$LATEST_VERSION
SHIELD_TEST_IMAGE_PREVIOUS=shield-test-runner:php$PHP_VERSION-wp$PREVIOUS_VERSION
PHPUNIT_DEBUG=${PHPUNIT_DEBUG:-}
SHIELD_TEST_VERBOSE=${SHIELD_TEST_VERBOSE:-}
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

# Enable debug mode for detailed monitoring
DEBUG_MODE=${DEBUG_MODE:-"false"}

if [ "$DEBUG_MODE" = "true" ]; then
    echo "🔍 Debug mode enabled - will show detailed process monitoring"
    set -x  # Enable bash debug output
fi

# Initialize overall exit code for the entire script
OVERALL_SCRIPT_EXIT=0

# Run requested execution mode
if [ "$ANALYZE_PACKAGE_MODE" = true ]; then
    echo "Running packaged PHPStan mode..."
    if ! run_packaged_phpstan; then
        OVERALL_SCRIPT_EXIT=1
        echo "ERROR: Packaged PHPStan analysis failed - check output above for details"
    fi
else
    # Run tests in parallel with database isolation
    echo "🚀 Starting parallel execution mode..."
    if ! run_parallel_tests; then
        OVERALL_SCRIPT_EXIT=1
        echo "❌ Parallel testing failed - check logs above for details"
    fi
fi

# Cleanup
if [ "$ANALYZE_PACKAGE_MODE" = false ]; then
    echo "🧹 Cleaning up..."
    docker compose -f tests/docker/docker-compose.yml \
        -f tests/docker/docker-compose.package.yml \
        down -v --remove-orphans || true
    rm -f tests/docker/.env
fi

echo ""
if [ "$OVERALL_SCRIPT_EXIT" = "0" ]; then
    echo "✅ Local Docker tests completed successfully!"
else
    echo "❌ Local Docker tests completed with failures!"
fi

if [ "$ANALYZE_PACKAGE_MODE" = true ]; then
    echo "   Execution mode: Packaged PHPStan analysis"
    echo "   Packaged plugin path: $PACKAGE_DIR"
else
    echo "   Tests ran in PARALLEL mode with isolated databases:"
    echo "   - WordPress $LATEST_VERSION (shield-db-latest:3309, database: wordpress_test_latest)"
    echo "   - WordPress $PREVIOUS_VERSION (shield-db-previous:3310, database: wordpress_test_previous)"
    echo "   - Execution mode: Parallel"
    echo "   - Output logs: /tmp/shield-test-*.log"
    echo "   - Package testing mode (production validation)"
fi

# Exit with the appropriate code for CI compatibility
exit $OVERALL_SCRIPT_EXIT
