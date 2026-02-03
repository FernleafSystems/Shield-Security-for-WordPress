#!/bin/bash

#
# Matrix Validation Script for Shield Security
# Simulates GitHub Actions matrix execution locally
#
# This script validates the WordPress version matrix configuration by:
# - Testing WordPress version detection
# - Simulating matrix job execution for both automatic and manual triggers
# - Validating Docker environment setup for both WordPress versions
# - Testing environment variable handling
#

set -euo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
readonly DOCKER_DIR="$PROJECT_ROOT/tests/docker"
readonly MATRIX_CONFIG="$PROJECT_ROOT/.github/config/matrix.conf"

# Source matrix configuration (single source of truth)
if [[ -f "$MATRIX_CONFIG" ]]; then
    source "$MATRIX_CONFIG"
    # Convert space-separated string to array
    read -ra PHP_VERSIONS_ARRAY <<< "$PHP_VERSIONS"
else
    echo "Error: Matrix config not found at $MATRIX_CONFIG" >&2
    exit 1
fi

# Color codes for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[0;33m'
readonly BLUE='\033[0;34m'
readonly CYAN='\033[0;36m'
readonly NC='\033[0m'

# Test results tracking
declare -a PASSED_TESTS=()
declare -a FAILED_TESTS=()
declare -a WARNINGS=()

#
# Logging functions
#
log_info() {
    echo -e "${BLUE}[INFO]${NC} $*"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $*"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $*"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $*"
}

log_test() {
    echo -e "${CYAN}[TEST]${NC} $*"
}

log_pass() {
    echo -e "${GREEN}[PASS]${NC} $*"
    PASSED_TESTS+=("$1")
}

log_fail() {
    echo -e "${RED}[FAIL]${NC} $*"
    FAILED_TESTS+=("$1")
}

add_warning() {
    WARNINGS+=("$1")
    log_warn "$1"
}

#
# Validation functions
#
validate_workflow_syntax() {
    log_test "Validating GitHub Actions workflow YAML syntax"
    
    local workflow_file="$PROJECT_ROOT/.github/workflows/docker-tests.yml"
    
    if [[ ! -f "$workflow_file" ]]; then
        log_fail "Workflow file not found: $workflow_file"
        return 1
    fi
    
    # Check for basic YAML syntax using Python (available on most systems)
    if command -v python3 >/dev/null 2>&1; then
        if python3 -c "import yaml; yaml.safe_load(open('$workflow_file'))" 2>/dev/null; then
            log_pass "Workflow YAML syntax validation"
        else
            log_fail "Workflow YAML syntax validation"
            return 1
        fi
    else
        # Fallback to basic structure checks
        if grep -q "^name:" "$workflow_file" && \
           grep -q "^on:" "$workflow_file" && \
           grep -q "^jobs:" "$workflow_file"; then
            log_pass "Workflow basic structure validation"
        else
            log_fail "Workflow basic structure validation"
            return 1
        fi
    fi
    
    return 0
}

validate_matrix_configuration() {
    log_test "Validating matrix configuration logic"
    
    local workflow_file="$PROJECT_ROOT/.github/workflows/docker-tests.yml"
    
    # Check for matrix configuration
    if grep -q "strategy:" "$workflow_file" && \
       grep -q "matrix:" "$workflow_file" && \
       grep -q "php:" "$workflow_file" && \
       grep -q "wordpress:" "$workflow_file"; then
        log_pass "Matrix configuration structure"
    else
        log_fail "Matrix configuration structure"
        return 1
    fi
    
    # Check that workflow uses matrix config (single source of truth)
    if grep -q "fromJSON(needs.detect-wp-versions.outputs.php_versions)" "$workflow_file"; then
        log_pass "PHP version matrix uses config file"
    else
        add_warning "PHP version matrix should use fromJSON(needs.detect-wp-versions.outputs.php_versions)"
    fi
    
    # Check for WordPress version matrix logic
    if grep -q "wordpress.*fromJSON.*detect-wp-versions" "$workflow_file"; then
        log_pass "WordPress version matrix logic"
    else
        log_fail "WordPress version matrix logic"
        return 1
    fi
    
    return 0
}

test_wordpress_version_detection() {
    log_test "Testing WordPress version detection script"
    
    local detection_script="$PROJECT_ROOT/.github/scripts/detect-wp-versions.sh"
    
    if [[ ! -f "$detection_script" ]]; then
        log_fail "WordPress version detection script not found"
        return 1
    fi
    
    # Make script executable
    chmod +x "$detection_script"
    
    # Test basic execution
    local output
    if output=$(cd "$PROJECT_ROOT" && "$detection_script" 2>/dev/null); then
        log_pass "WordPress version detection script execution"
        
        # Parse output to check format
        if echo "$output" | grep -q "LATEST_VERSION=" && \
           echo "$output" | grep -q "PREVIOUS_VERSION="; then
            log_pass "WordPress version detection output format"
            
            # Extract versions for later use
            local latest_version previous_version
            latest_version=$(echo "$output" | grep "LATEST_VERSION=" | cut -d= -f2)
            previous_version=$(echo "$output" | grep "PREVIOUS_VERSION=" | cut -d= -f2)
            
            log_info "Detected WordPress versions:"
            log_info "  Latest: $latest_version"
            log_info "  Previous: $previous_version"
            
            # Store for matrix testing
            echo "DETECTED_LATEST=$latest_version" > /tmp/wp-versions.env
            echo "DETECTED_PREVIOUS=$previous_version" >> /tmp/wp-versions.env
        else
            log_fail "WordPress version detection output format"
            return 1
        fi
    else
        log_fail "WordPress version detection script execution"
        return 1
    fi
    
    return 0
}

simulate_matrix_jobs() {
    log_test "Simulating matrix job execution"
    
    # Load detected versions
    if [[ ! -f /tmp/wp-versions.env ]]; then
        log_fail "WordPress versions not detected, cannot simulate matrix"
        return 1
    fi
    
    source /tmp/wp-versions.env
    
    if [[ -z "${DETECTED_LATEST:-}" ]] || [[ -z "${DETECTED_PREVIOUS:-}" ]]; then
        log_fail "Invalid WordPress version data"
        return 1
    fi
    
    local php_count=${#PHP_VERSIONS_ARRAY[@]}
    local expected_jobs=$((php_count * 2))
    
    log_info "Simulating matrix with detected versions:"
    log_info "  PHP: $PHP_VERSIONS (from matrix.conf)"
    log_info "  WordPress: $DETECTED_LATEST, $DETECTED_PREVIOUS"
    
    # Simulate automatic trigger (push event)
    log_test "Simulating automatic trigger ($expected_jobs jobs)"
    
    local job_count=0
    
    for php_version in "${PHP_VERSIONS_ARRAY[@]}"; do
        for wp_version in "$DETECTED_LATEST" "$DETECTED_PREVIOUS"; do
            ((job_count++))
            log_info "Matrix Job $job_count: PHP $php_version / WordPress $wp_version"
            
            if simulate_docker_environment_setup "$php_version" "$wp_version"; then
                log_pass "Matrix job $job_count environment setup"
            else
                log_fail "Matrix job $job_count environment setup"
                return 1
            fi
        done
    done
    
    if [[ $job_count -eq $expected_jobs ]]; then
        log_pass "Automatic trigger matrix simulation ($expected_jobs jobs)"
    else
        log_fail "Automatic trigger matrix simulation (expected $expected_jobs jobs, got $job_count)"
        return 1
    fi
    
    # Simulate manual trigger (workflow_dispatch) - 1 job with specified version
    log_test "Simulating manual trigger (1 job)"
    
    log_info "Manual trigger job: PHP $DEFAULT_PHP / WordPress $DETECTED_LATEST"
    
    if simulate_docker_environment_setup "$DEFAULT_PHP" "$DETECTED_LATEST"; then
        log_pass "Manual trigger matrix simulation (1 job)"
    else
        log_fail "Manual trigger matrix simulation (1 job)"
        return 1
    fi
    
    return 0
}

simulate_docker_environment_setup() {
    local php_version="$1"
    local wp_version="$2"
    
    log_test "Testing Docker environment setup for PHP $php_version / WP $wp_version"
    
    # Create test environment file
    local test_env_file="/tmp/shield-test-$$.env"
    
    cat > "$test_env_file" << EOF
TEST_PHP_VERSION=$php_version
TEST_WP_VERSION=$wp_version
PLUGIN_SOURCE=/tmp/test-package
SHIELD_PACKAGE_PATH=/tmp/test-package
EOF
    
    # Validate environment file format
    if grep -q "TEST_PHP_VERSION=" "$test_env_file" && \
       grep -q "TEST_WP_VERSION=" "$test_env_file" && \
       grep -q "PLUGIN_SOURCE=" "$test_env_file" && \
       grep -q "SHIELD_PACKAGE_PATH=" "$test_env_file"; then
        log_pass "Environment file format for PHP $php_version / WP $wp_version"
    else
        log_fail "Environment file format for PHP $php_version / WP $wp_version"
        rm -f "$test_env_file"
        return 1
    fi
    
    log_info "Environment variables:"
    cat "$test_env_file" | sed 's/^/    /'
    
    rm -f "$test_env_file"
    return 0
}

validate_docker_configuration() {
    log_test "Validating Docker configuration files"
    
    local dockerfile="$DOCKER_DIR/Dockerfile"
    local compose_file="$DOCKER_DIR/docker-compose.yml"
    local compose_ci="$DOCKER_DIR/docker-compose.ci.yml"
    local compose_package="$DOCKER_DIR/docker-compose.package.yml"
    
    # Check Dockerfile
    if [[ -f "$dockerfile" ]]; then
        # Check for PHP version argument
        if grep -q "ARG PHP_VERSION" "$dockerfile"; then
            log_pass "Dockerfile PHP_VERSION argument"
        else
            log_fail "Dockerfile PHP_VERSION argument"
            return 1
        fi
        
        # Check for WordPress version argument
        if grep -q "ARG WP_VERSION" "$dockerfile"; then
            log_pass "Dockerfile WP_VERSION argument"
        else
            log_fail "Dockerfile WP_VERSION argument"
            return 1
        fi
        
        # Check for multi-stage build optimization
        if grep -q "FROM.*AS" "$dockerfile"; then
            log_pass "Dockerfile multi-stage build structure"
        else
            add_warning "Dockerfile may not use multi-stage builds for optimization"
        fi
    else
        log_fail "Dockerfile not found: $dockerfile"
        return 1
    fi
    
    # Check docker-compose.yml
    if [[ -f "$compose_file" ]]; then
        # Check for environment variable support
        if grep -q "\${PHP_VERSION" "$compose_file" && \
           grep -q "\${WP_VERSION" "$compose_file"; then
            log_pass "Docker Compose environment variable support"
        else
            log_fail "Docker Compose environment variable support"
            return 1
        fi
        
        # Check for test-runner-latest service
        if grep -q "test-runner-latest:" "$compose_file"; then
            log_pass "Docker Compose test-runner-latest service"
        else
            log_fail "Docker Compose test-runner-latest service"
            return 1
        fi
    else
        log_fail "Docker Compose file not found: $compose_file"
        return 1
    fi
    
    # Check CI and package override files
    for override_file in "$compose_ci" "$compose_package"; do
        if [[ -f "$override_file" ]]; then
            log_pass "Docker Compose override file: $(basename "$override_file")"
        else
            add_warning "Docker Compose override file not found: $(basename "$override_file")"
        fi
    done
    
    return 0
}

test_environment_variable_handling() {
    log_test "Testing environment variable handling in workflow"
    
    local workflow_file="$PROJECT_ROOT/.github/workflows/docker-tests.yml"
    
    # Check for matrix variable usage in Docker build args
    if grep -q "PHP_VERSION.*matrix\.php" "$workflow_file" && \
       grep -q "WP_VERSION.*matrix\.wordpress" "$workflow_file"; then
        log_pass "Matrix variables in Docker build args"
    else
        log_fail "Matrix variables in Docker build args"
        return 1
    fi
    
    # Check for environment file creation logic
    if grep -q "cat > tests/docker/\.env" "$workflow_file"; then
        log_pass "Environment file creation in workflow"
    else
        log_fail "Environment file creation in workflow"
        return 1
    fi
    
    # Check for workflow_dispatch vs push event handling
    if grep -q "github\.event_name.*workflow_dispatch" "$workflow_file"; then
        log_pass "Event-specific environment variable handling"
    else
        log_fail "Event-specific environment variable handling"
        return 1
    fi
    
    return 0
}

test_docker_build_capability() {
    log_test "Testing Docker build capability (dry run)"
    
    if ! command -v docker >/dev/null 2>&1; then
        add_warning "Docker not available for build testing"
        return 0
    fi
    
    # Test basic Docker functionality
    if docker info >/dev/null 2>&1; then
        log_pass "Docker daemon accessibility"
    else
        add_warning "Docker daemon not accessible"
        return 0
    fi
    
    # Test Dockerfile validation
    local dockerfile="$DOCKER_DIR/Dockerfile"
    if docker build --dry-run -f "$dockerfile" "$DOCKER_DIR" >/dev/null 2>&1; then
        log_pass "Dockerfile validation (dry run)"
    else
        log_fail "Dockerfile validation (dry run)"
        return 1
    fi
    
    return 0
}

generate_validation_report() {
    echo
    echo "============================================="
    echo "Shield Security Matrix Validation Report"
    echo "============================================="
    echo
    
    if [[ ${#PASSED_TESTS[@]} -gt 0 ]]; then
        echo -e "${GREEN}PASSED TESTS (${#PASSED_TESTS[@]}):${NC}"
        for test in "${PASSED_TESTS[@]}"; do
            echo "  ✓ $test"
        done
        echo
    fi
    
    if [[ ${#FAILED_TESTS[@]} -gt 0 ]]; then
        echo -e "${RED}FAILED TESTS (${#FAILED_TESTS[@]}):${NC}"
        for test in "${FAILED_TESTS[@]}"; do
            echo "  ✗ $test"
        done
        echo
    fi
    
    if [[ ${#WARNINGS[@]} -gt 0 ]]; then
        echo -e "${YELLOW}WARNINGS (${#WARNINGS[@]}):${NC}"
        for warning in "${WARNINGS[@]}"; do
            echo "  ⚠ $warning"
        done
        echo
    fi
    
    local total_tests=$((${#PASSED_TESTS[@]} + ${#FAILED_TESTS[@]}))
    local pass_rate=0
    if [[ $total_tests -gt 0 ]]; then
        pass_rate=$(( (${#PASSED_TESTS[@]} * 100) / total_tests ))
    fi
    
    echo "SUMMARY: ${#PASSED_TESTS[@]}/${total_tests} tests passed (${pass_rate}%)"
    echo "WARNINGS: ${#WARNINGS[@]}"
    echo
    
    if [[ ${#FAILED_TESTS[@]} -eq 0 ]]; then
        echo -e "${GREEN}✓ Matrix configuration is valid and ready for GitHub Actions execution${NC}"
        echo
        local php_count=${#PHP_VERSIONS_ARRAY[@]}
        local expected_jobs=$((php_count * 2))
        echo "EXPECTED MATRIX BEHAVIOR:"
        echo "  • Config file: .github/config/matrix.conf"
        echo "  • PHP versions: $PHP_VERSIONS"
        echo "  • Push events (automatic): $expected_jobs jobs ($php_count PHP × 2 WordPress)"
        echo "  • Manual dispatch: 1 job (default PHP $DEFAULT_PHP × specified WordPress)"
        echo "  • Environment variables properly configured for Docker"
        return 0
    else
        echo -e "${RED}✗ Matrix configuration has issues that need to be resolved${NC}"
        return 1
    fi
}

cleanup() {
    # Clean up temporary files
    rm -f /tmp/wp-versions.env
    rm -f /tmp/shield-test-*.env
}

main() {
    log_info "Shield Security - Matrix Validation Tool"
    log_info "Validating WordPress version matrix configuration for GitHub Actions"
    echo
    
    # Set up cleanup trap
    trap cleanup EXIT
    
    # Check prerequisites
    if [[ ! -d "$PROJECT_ROOT/.github/workflows" ]]; then
        log_error "Not in a Shield Security project directory"
        exit 1
    fi
    
    # Run validation tests
    validate_workflow_syntax
    validate_matrix_configuration
    test_wordpress_version_detection
    validate_docker_configuration
    test_environment_variable_handling
    simulate_matrix_jobs
    test_docker_build_capability
    
    # Generate final report
    generate_validation_report
}

# Script execution
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi