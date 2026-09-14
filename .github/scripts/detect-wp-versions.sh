#!/bin/bash

#
# WordPress Version Detection Script
#
# This script implements a comprehensive WordPress version detection system with:
# - WordPress.org API integration (primary and secondary endpoints)
# - Local API response caching for resilience
# - Git tag fallback for reliability
# - Retry logic with exponential backoff
# - Comprehensive error handling and edge case management
set -euo pipefail

# Script configuration
readonly SCRIPT_NAME="$(basename "$0")"
readonly CACHE_DIR="${SHIELD_WP_API_CACHE_DIR:-${HOME}/.wp-api-cache}"
readonly MAX_RETRIES=3
readonly INITIAL_BACKOFF=2  # Initial backoff in seconds

# WordPress.org API endpoints
readonly PRIMARY_API="https://api.wordpress.org/core/version-check/1.7/"
readonly SECONDARY_API="https://api.wordpress.org/core/stable-check/1.0/"

# Color codes for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[0;33m'
readonly BLUE='\033[0;34m'
readonly CYAN='\033[0;36m'
readonly NC='\033[0m' # No Color

#
# Logging functions
#
log_info() {
    echo -e "${BLUE}[INFO]${NC} $*" >&2
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $*" >&2
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $*" >&2
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $*" >&2
}

log_debug() {
    if [[ "${DEBUG:-0}" == "1" ]]; then
        echo -e "${CYAN}[DEBUG]${NC} $*" >&2
    fi
}

#
# Utility functions
#
create_cache_dir() {
    if [[ ! -d "$CACHE_DIR" ]]; then
        mkdir -p "$CACHE_DIR"
        log_debug "Created cache directory: $CACHE_DIR"
    fi
}

get_cache_file() {
    local api_url="$1"
    local cache_key
    cache_key=$(echo -n "$api_url" | sha256sum | cut -d' ' -f1)
    echo "$CACHE_DIR/wp-api-${cache_key}.json"
}

#
# Network and API functions
#
fetch_with_retry() {
    local url="$1"
    local max_retries="$2"
    local backoff="$INITIAL_BACKOFF"
    local attempt=1
    
    while [[ $attempt -le $max_retries ]]; do
        log_debug "Attempting API call (attempt $attempt/$max_retries): $url"
        
        local response
        local http_code
        
        # Use curl with comprehensive options for reliability
        if response=$(curl -s -f -L \
            --max-time 30 \
            --connect-timeout 10 \
            --retry 0 \
            --user-agent "Shield-Security-Plugin-CI/1.0 (WordPress-Version-Detection)" \
            -w "HTTPSTATUS:%{http_code}" \
            "$url" 2>/dev/null); then
            
            http_code=$(echo "$response" | grep -o "HTTPSTATUS:[0-9]*" | cut -d: -f2)
            response=$(echo "$response" | sed 's/HTTPSTATUS:[0-9]*$//')
            
            if [[ "$http_code" -eq 200 ]] && [[ -n "$response" ]]; then
                log_debug "API call successful (HTTP $http_code)"
                echo "$response"
                return 0
            else
                log_warn "API call returned HTTP $http_code or empty response"
            fi
        else
            log_warn "API call failed (attempt $attempt/$max_retries): $url"
        fi
        
        if [[ $attempt -lt $max_retries ]]; then
            log_debug "Waiting ${backoff}s before retry..."
            sleep "$backoff"
            backoff=$((backoff * 2))
        fi
        
        ((attempt++))
    done
    
    log_error "All retry attempts failed for: $url"
    return 1
}

fetch_api_data() {
    local api_url="$1"
    local cache_file
    cache_file=$(get_cache_file "$api_url")
    
    # Level 1: Try fresh API call
    local api_response
    if api_response=$(fetch_with_retry "$api_url" "$MAX_RETRIES"); then
        log_debug "Fresh API data received from: $api_url"
        
        # Validate JSON structure
        if echo "$api_response" | jq empty 2>/dev/null; then
            # Cache the successful response
            echo "$api_response" > "$cache_file"
            log_debug "Cached API response to: $cache_file"
            echo "$api_response"
            return 0
        else
            log_warn "Invalid JSON response from API: $api_url"
        fi
    fi
    
    # Level 2: Try cached response (even if expired as fallback)
    if [[ -f "$cache_file" ]] && [[ -s "$cache_file" ]]; then
        log_warn "Using cached API response as fallback: $cache_file"
        local cached_response
        if cached_response=$(cat "$cache_file") && echo "$cached_response" | jq empty 2>/dev/null; then
            echo "$cached_response"
            return 0
        else
            log_warn "Cached response is invalid JSON"
        fi
    fi
    
    return 1
}

#
# Version parsing and validation functions
#
extract_major_minor() {
    local version="$1"
    echo "$version" | sed -E 's/^([0-9]+\.[0-9]+)(\.[0-9]+.*)?$/\1/'
}

compare_versions() {
    local ver1="$1"
    local ver2="$2"
    
    # Convert versions to comparable format
    local v1_normalized v2_normalized
    v1_normalized=$(echo "$ver1" | sed 's/[^0-9.]//g')
    v2_normalized=$(echo "$ver2" | sed 's/[^0-9.]//g')
    
    # Use sort -V for version comparison
    if [[ "$v1_normalized" == "$v2_normalized" ]]; then
        echo "0"
    elif [[ "$(printf '%s\n' "$v1_normalized" "$v2_normalized" | sort -V | head -n1)" == "$v1_normalized" ]]; then
        echo "-1"
    else
        echo "1"
    fi
}

validate_version_format() {
    local version="$1"
    
    # Basic semantic version validation
    if [[ "$version" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?(-[a-zA-Z0-9-]+)?$ ]]; then
        return 0
    else
        log_warn "Invalid version format: $version"
        return 1
    fi
}

#
# WordPress version detection functions
#
detect_versions_primary_api() {
    log_info "Attempting primary API (version-check/1.7/)..."
    
    local api_response
    if ! api_response=$(fetch_api_data "$PRIMARY_API"); then
        log_warn "Primary API failed"
        return 1
    fi
    
    # Extract and validate latest version
    local latest_version
    if ! latest_version=$(echo "$api_response" | jq -r '.offers[0].version // empty' 2>/dev/null) || [[ -z "$latest_version" ]]; then
        log_warn "Could not extract latest version from primary API"
        return 1
    fi
    
    if ! validate_version_format "$latest_version"; then
        log_warn "Invalid latest version format from primary API: $latest_version"
        return 1
    fi
    
    # Calculate previous major version
    local latest_major_minor previous_version
    latest_major_minor=$(extract_major_minor "$latest_version")
    
    # Try to find previous major.minor series
    local latest_major latest_minor
    latest_major=$(echo "$latest_major_minor" | cut -d. -f1)
    latest_minor=$(echo "$latest_major_minor" | cut -d. -f2)
    
    # First try previous minor version in same major
    local previous_minor=$((latest_minor - 1))
    local previous_major_minor="${latest_major}.${previous_minor}"
    
    previous_version=$(echo "$api_response" | jq -r \
        --arg pm "$previous_major_minor" \
        '.offers[] | select(.version | startswith($pm)) | .version' 2>/dev/null | head -n1)
    
    # If not found, try previous major version
    if [[ -z "$previous_version" ]]; then
        local previous_major=$((latest_major - 1))
        previous_version=$(echo "$api_response" | jq -r \
            --arg pm "$previous_major" \
            '.offers[] | select(.version | startswith($pm)) | .version' 2>/dev/null | head -n1)
    fi
    
    if [[ -z "$previous_version" ]]; then
        log_warn "Could not determine previous version from primary API"
        return 1
    fi
    
    if ! validate_version_format "$previous_version"; then
        log_warn "Invalid previous version format from primary API: $previous_version"
        return 1
    fi
    
    log_success "Primary API detection successful"
    log_info "Latest: $latest_version"
    log_info "Previous: $previous_version"
    
    echo "$latest_version|$previous_version"
    return 0
}

detect_versions_secondary_api() {
    log_info "Attempting secondary API (stable-check/1.0/)..."
    
    local api_response
    if ! api_response=$(fetch_api_data "$SECONDARY_API"); then
        log_warn "Secondary API failed"
        return 1
    fi
    
    # Extract latest stable version
    local latest_version
    if ! latest_version=$(echo "$api_response" | jq -r 'keys[] | select(. != "latest")' 2>/dev/null | sort -V | tail -n1) || [[ -z "$latest_version" ]]; then
        log_warn "Could not extract versions from secondary API"
        return 1
    fi
    
    if ! validate_version_format "$latest_version"; then
        log_warn "Invalid version format from secondary API: $latest_version"
        return 1
    fi
    
    # For secondary API, we have limited version info, so calculate previous
    local latest_major_minor previous_version
    latest_major_minor=$(extract_major_minor "$latest_version")
    local latest_major latest_minor
    latest_major=$(echo "$latest_major_minor" | cut -d. -f1)
    latest_minor=$(echo "$latest_major_minor" | cut -d. -f2)
    
    # Try previous minor
    local previous_minor=$((latest_minor - 1))
    if [[ $previous_minor -ge 0 ]]; then
        previous_version="${latest_major}.${previous_minor}.0"
    else
        # Try previous major
        local previous_major=$((latest_major - 1))
        previous_version="${previous_major}.9.0"
    fi
    
    # Validate against available versions from secondary API
    local available_versions
    available_versions=$(echo "$api_response" | jq -r 'keys[] | select(. != "latest")' 2>/dev/null | sort -V)
    
    # Find the best match for previous version
    local best_previous=""
    while IFS= read -r version; do
        if [[ "$(compare_versions "$version" "$latest_version")" -lt 0 ]]; then
            best_previous="$version"
        fi
    done <<< "$available_versions"
    
    if [[ -n "$best_previous" ]]; then
        previous_version="$best_previous"
    fi
    
    if ! validate_version_format "$previous_version"; then
        log_warn "Invalid previous version format from secondary API: $previous_version"
        return 1
    fi
    
    log_success "Secondary API detection successful"
    log_info "Latest: $latest_version"
    log_info "Previous: $previous_version"
    
    echo "$latest_version|$previous_version"
    return 0
}

detect_versions_git_tags() {
    log_info "Attempting Git tag fallback (wordpress-develop)..."

    local remote_repo="https://github.com/WordPress/wordpress-develop.git"
    local tag_output=""

    # First try local git.
    if command -v git >/dev/null 2>&1; then
        if tag_output=$(git ls-remote --tags --refs "$remote_repo" 2>/dev/null); then
            log_debug "Fetched wordpress-develop tags via local git"
        else
            tag_output=""
            log_debug "Local git ls-remote failed"
        fi
    fi

    # Fallback for environments with host TLS issues (common on Windows runners):
    # use dockerized git if Docker is available.
    if [[ -z "$tag_output" ]] && command -v docker >/dev/null 2>&1; then
        if tag_output=$(docker run --rm alpine/git ls-remote --tags --refs "$remote_repo" 2>/dev/null); then
            log_debug "Fetched wordpress-develop tags via dockerized git"
        else
            tag_output=""
            log_debug "Dockerized git ls-remote failed"
        fi
    fi

    if [[ -z "$tag_output" ]]; then
        log_warn "Git tag fallback unavailable"
        return 1
    fi

    local versions
    versions=$(echo "$tag_output" | sed -nE 's#.*refs/tags/([0-9]+\.[0-9]+(\.[0-9]+)?)$#\1#p' | sort -V | uniq)

    if [[ -z "$versions" ]]; then
        log_warn "No version tags could be parsed from wordpress-develop tags"
        return 1
    fi

    local latest_version latest_series previous_series previous_version
    latest_version=$(echo "$versions" | tail -n1)
    latest_series=$(extract_major_minor "$latest_version")

    previous_series=$(echo "$versions" | sed -E 's/^([0-9]+\.[0-9]+).*/\1/' | uniq | grep -v "^${latest_series}$" | tail -n1 || true)

    if [[ -n "$previous_series" ]]; then
        previous_version=$(echo "$versions" | grep -E "^${previous_series}(\\.[0-9]+)?$" | tail -n1 || true)
    fi

    if [[ -z "${previous_version:-}" ]]; then
        previous_version=$(echo "$versions" | grep -v "^${latest_version}$" | tail -n1 || true)
    fi

    if [[ -z "$latest_version" ]] || [[ -z "$previous_version" ]]; then
        log_warn "Unable to determine latest/previous versions from parsed git tags"
        return 1
    fi

    if ! validate_version_format "$latest_version" || ! validate_version_format "$previous_version"; then
        log_warn "Git tag fallback produced invalid version format"
        return 1
    fi

    log_success "Git tag fallback detection successful"
    log_info "Latest: $latest_version"
    log_info "Previous: $previous_version"

    echo "$latest_version|$previous_version"
    return 0
}

detect_versions_api_level() {
    local versions
    if versions=$(detect_versions_primary_api); then
        echo "$versions"
        return 0
    fi
    if versions=$(detect_versions_secondary_api); then
        echo "$versions"
        return 0
    fi
    return 1
}

#
# Main version detection
#
detect_wordpress_versions() {
    log_info "Starting WordPress version detection"
    
    create_cache_dir
    
    local versions=""
    
    # Level 1: API (primary endpoint, then secondary endpoint)
    if versions=$(detect_versions_api_level); then
        log_success "Level 1: API level successful"
        echo "$versions"
        return 0
    fi
    
    # Level 2: Git tags from wordpress-develop repository
    if versions=$(detect_versions_git_tags); then
        log_success "Level 2: Git tag fallback successful"
        echo "$versions"
        return 0
    fi

    log_error "API and Git tag detection failed"
    return 1
}

#
# GitHub Actions integration
#
set_github_outputs() {
    local latest="$1"
    local previous="$2"
    
    if [[ "${GITHUB_ACTIONS:-false}" == "true" ]] && [[ -n "${GITHUB_OUTPUT:-}" ]]; then
        log_info "Setting GitHub Actions outputs"
        
        {
            echo "latest=$latest"
            echo "previous=$previous"
            echo "lts=$previous"  # For compatibility
            echo "matrix_ready=true"
        } >> "$GITHUB_OUTPUT"
        
        log_success "GitHub Actions outputs set successfully"
    else
        log_info "Not in GitHub Actions environment, skipping output setting"
    fi
}

print_help() {
    cat << EOF
WordPress Version Detection Script

This script detects the latest and previous major WordPress versions using
WordPress.org APIs, cached API responses, and a Git tag fallback.

Usage: $SCRIPT_NAME [-h|--help]

OPTIONS:
    -h, --help      Show this help message

FALLBACK LEVELS:
    1. API level (primary version-check/1.7/, then secondary stable-check/1.0/)
    2. Git tags from wordpress-develop

OUTPUTS:
    When run in GitHub Actions, sets these outputs:
    - latest: Latest stable WordPress version
    - previous: Previous major WordPress version
    - lts: Alias for previous (compatibility)
    - matrix_ready: Boolean indicating success

CACHE:
    Cache directory: $CACHE_DIR
    Successful API responses are reused only when fresh retrieval fails.

EXIT CODES:
    0: Success
    1: General error
    2: Unsupported command-line arguments
    3: Invalid version detected

EOF
}

#
# Main execution function
#
main() {
    case "$#" in
        0)
            ;;
        1)
            case "$1" in
                -h|--help)
                    print_help
                    return 0
                    ;;
                *)
                    log_error "Unsupported argument: $1"
                    print_help
                    return 2
                    ;;
            esac
            ;;
        *)
            log_error "Expected no arguments or exactly one help argument"
            print_help
            return 2
            ;;
    esac

    log_info "$SCRIPT_NAME starting..."

    # Detect WordPress versions
    local versions
    if ! versions=$(detect_wordpress_versions); then
        log_error "All fallback levels failed"
        exit 1
    fi
    
    # Parse the versions
    local latest previous
    IFS='|' read -r latest previous <<< "$versions"
    
    if [[ -z "$latest" ]] || [[ -z "$previous" ]]; then
        log_error "Invalid version detection result: '$versions'"
        exit 3
    fi
    
    # Final validation
    if ! validate_version_format "$latest" || ! validate_version_format "$previous"; then
        log_error "Final version validation failed"
        log_error "Latest: $latest"
        log_error "Previous: $previous"
        exit 3
    fi
    
    # Set GitHub Actions outputs if applicable
    set_github_outputs "$latest" "$previous"
    
    # Output results
    log_success "WordPress version detection completed successfully"
    log_info "Latest WordPress version: $latest"
    log_info "Previous major version: $previous"
    
    # Output in format expected by calling scripts
    echo "LATEST_VERSION=$latest"
    echo "PREVIOUS_VERSION=$previous"
    
    return 0
}

# Handle script termination gracefully
cleanup() {
    local exit_code=$?
    if [[ $exit_code -ne 0 ]]; then
        log_error "Script terminated with exit code $exit_code"
    fi
    exit $exit_code
}

# Set up signal handlers
trap cleanup EXIT
trap 'cleanup' INT TERM

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
