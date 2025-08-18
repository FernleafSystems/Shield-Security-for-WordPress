#!/bin/bash

#
# Quick Matrix Execution Test
# Tests the WordPress version matrix locally without full Docker builds
#

set -euo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Color codes
readonly GREEN='\033[0;32m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $*"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $*"
}

main() {
    echo "============================================="
    echo "Shield Security - Matrix Execution Test"
    echo "============================================="
    echo
    
    log_info "Testing WordPress version detection..."
    
    # Run the version detection script
    local detection_script="$PROJECT_ROOT/.github/scripts/detect-wp-versions.sh"
    
    if [[ -f "$detection_script" ]]; then
        chmod +x "$detection_script"
        
        log_info "Running WordPress version detection..."
        local output
        if output=$(cd "$PROJECT_ROOT" && "$detection_script" --debug 2>&1); then
            echo "$output"
            echo
            
            # Extract versions
            local latest_version previous_version
            latest_version=$(echo "$output" | grep "LATEST_VERSION=" | cut -d= -f2 | tail -n1)
            previous_version=$(echo "$output" | grep "PREVIOUS_VERSION=" | cut -d= -f2 | tail -n1)
            
            if [[ -n "$latest_version" ]] && [[ -n "$previous_version" ]]; then
                log_success "WordPress versions detected successfully"
                echo "  Latest:   $latest_version"
                echo "  Previous: $previous_version"
                echo
                
                log_info "Matrix would execute with:"
                echo "  Job 1: PHP 7.4 + WordPress $latest_version"
                echo "  Job 2: PHP 7.4 + WordPress $previous_version"
                echo
                log_success "Matrix configuration ready for GitHub Actions"
            else
                echo "ERROR: Failed to extract WordPress versions"
                exit 1
            fi
        else
            echo "ERROR: WordPress version detection failed"
            echo "$output"
            exit 1
        fi
    else
        echo "ERROR: Detection script not found: $detection_script"
        exit 1
    fi
}

main "$@"