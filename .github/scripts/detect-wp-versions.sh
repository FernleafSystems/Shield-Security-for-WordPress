#!/usr/bin/env bash

set -euo pipefail

readonly SCRIPT_NAME="$(basename "$0")"
readonly MAX_RETRIES=3
readonly INITIAL_BACKOFF=2
readonly PRIMARY_API="https://api.wordpress.org/core/version-check/1.7/"
readonly SECONDARY_API="https://api.wordpress.org/core/stable-check/1.0/"

readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[0;33m'
readonly BLUE='\033[0;34m'
readonly CYAN='\033[0;36m'
readonly NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $*" >&2; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $*" >&2; }
log_error() { echo -e "${RED}[ERROR]${NC} $*" >&2; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $*" >&2; }
log_debug() {
	if [[ "${DEBUG:-0}" == "1" ]]; then
		echo -e "${CYAN}[DEBUG]${NC} $*" >&2
	fi
}

fetch_with_retry() {
	local url="$1"
	local max_retries="$2"
	local backoff="$INITIAL_BACKOFF"
	local attempt=1

	while [[ $attempt -le $max_retries ]]; do
		log_debug "Attempting API call (attempt $attempt/$max_retries): $url"

		local response http_code
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
				echo "$response"
				return 0
			fi
			log_warn "API call returned HTTP $http_code or an empty response"
		else
			log_warn "API call failed (attempt $attempt/$max_retries): $url"
		fi

		if [[ $attempt -lt $max_retries ]]; then
			sleep "$backoff"
			backoff=$((backoff * 2))
		fi
		attempt=$((attempt + 1))
	done

	log_error "All retry attempts failed for: $url"
	return 1
}

fetch_api_data() {
	local api_url="$1"
	local api_response

	if ! api_response=$(fetch_with_retry "$api_url" "$MAX_RETRIES"); then
		return 1
	fi

	if ! echo "$api_response" | jq empty 2>/dev/null; then
		log_warn "Invalid JSON response from API: $api_url"
		return 1
	fi

	echo "$api_response"
}

# Reads version candidates from stdin and emits "latest|previous". A valid
# result contains the highest stable patch from each of the two greatest,
# distinct X.Y release series.
select_latest_release_series() {
	local versions series latest_series previous_series latest previous

	versions=$(cat | sed '/^[[:space:]]*$/d' | grep -E '^[0-9]+\.[0-9]+(\.[0-9]+)?$' | sort -Vu || true)
	if [[ -z "$versions" ]]; then
		return 1
	fi

	series=$(echo "$versions" \
		| sed -E 's/^([0-9]+\.[0-9]+)(\.[0-9]+)?$/\1/' \
		| sort -t. -k1,1n -k2,2n -u)
	if [[ $(echo "$series" | wc -l) -lt 2 ]]; then
		return 1
	fi

	latest_series=$(echo "$series" | tail -n1)
	previous_series=$(echo "$series" | tail -n2 | head -n1)
	latest=$(echo "$versions" | grep -E "^${latest_series//./\\.}(\\.[0-9]+)?$" | tail -n1)
	previous=$(echo "$versions" | grep -E "^${previous_series//./\\.}(\\.[0-9]+)?$" | tail -n1)

	[[ -n "$latest" && -n "$previous" ]] || return 1
	echo "$latest|$previous"
}

detect_versions_primary_api() {
	log_info "Attempting primary API (version-check/1.7/)..."
	local api_response versions

	if ! api_response=$(fetch_api_data "$PRIMARY_API"); then
		log_warn "Primary API failed"
		return 1
	fi

	if ! versions=$(echo "$api_response" | jq -r '.offers[]?.version // empty' 2>/dev/null | select_latest_release_series); then
		log_warn "Primary API did not provide two stable release series"
		return 1
	fi

	log_success "Primary API detection successful"
	echo "$versions"
}

detect_versions_secondary_api() {
	log_info "Attempting secondary API (stable-check/1.0/)..."
	local api_response versions

	if ! api_response=$(fetch_api_data "$SECONDARY_API"); then
		log_warn "Secondary API failed"
		return 1
	fi

	if ! versions=$(echo "$api_response" \
		| jq -r 'to_entries[] | select((.value == "latest") or (.value == "outdated")) | .key' 2>/dev/null \
		| select_latest_release_series); then
		log_warn "Secondary API did not provide two trustworthy release series"
		return 1
	fi

	log_success "Secondary API detection successful"
	echo "$versions"
}

detect_versions_git_tags() {
	log_info "Attempting Git tag fallback (wordpress-develop)..."
	local remote_repo="https://github.com/WordPress/wordpress-develop.git"
	local tag_output="" versions

	if command -v git >/dev/null 2>&1; then
		tag_output=$(git ls-remote --tags --refs "$remote_repo" 2>/dev/null || true)
	fi

	if [[ -z "$tag_output" ]] && command -v docker >/dev/null 2>&1; then
		tag_output=$(docker run --rm alpine/git ls-remote --tags --refs "$remote_repo" 2>/dev/null || true)
	fi

	if [[ -z "$tag_output" ]]; then
		log_warn "Git tag fallback unavailable"
		return 1
	fi

	if ! versions=$(echo "$tag_output" \
		| sed -nE 's#.*refs/tags/([0-9]+\.[0-9]+(\.[0-9]+)?)$#\1#p' \
		| select_latest_release_series); then
		log_warn "Git tags did not provide two stable release series"
		return 1
	fi

	log_success "Git tag fallback detection successful"
	echo "$versions"
}

detect_wordpress_versions() {
	local versions
	log_info "Starting WordPress version detection"

	if versions=$(detect_versions_primary_api); then
		echo "$versions"
		return 0
	fi
	if versions=$(detect_versions_secondary_api); then
		echo "$versions"
		return 0
	fi
	if versions=$(detect_versions_git_tags); then
		echo "$versions"
		return 0
	fi

	log_error "Primary API, secondary API, and Git tag detection failed"
	return 1
}

print_help() {
	cat <<EOF
WordPress Version Detection Script

This script detects the highest stable patch from each of the two newest
WordPress release series using WordPress.org APIs and a Git tag fallback.

Usage: $SCRIPT_NAME [-h|--help]

OPTIONS:
    -h, --help      Show this help message

FALLBACK LEVELS:
    1. Primary version-check/1.7/ API
    2. Secondary stable-check/1.0/ API
    3. Stable tags from wordpress-develop

OUTPUT:
    LATEST_VERSION: Highest patch in the newest stable release series
    PREVIOUS_VERSION: Highest patch in the previous stable release series

EXIT CODES:
    0: Success
    1: Version detection failed
    2: Unsupported command-line arguments
    3: Invalid detection result
EOF
}

main() {
	case "$#" in
		0) ;;
		1)
			if [[ "$1" == "-h" || "$1" == "--help" ]]; then
				print_help
				return 0
			fi
			log_error "Unsupported argument: $1"
			print_help
			return 2
			;;
		*)
			log_error "Expected no arguments or exactly one help argument"
			print_help
			return 2
			;;
	esac

	local versions latest previous
	if ! versions=$(detect_wordpress_versions); then
		return 1
	fi
	IFS='|' read -r latest previous <<< "$versions"
	if [[ -z "$latest" || -z "$previous" ]]; then
		log_error "Invalid version detection result: '$versions'"
		return 3
	fi

	log_success "WordPress version detection completed successfully"
	log_info "Latest WordPress version: $latest"
	log_info "Previous release series: $previous"
	echo "LATEST_VERSION=$latest"
	echo "PREVIOUS_VERSION=$previous"
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
	main "$@"
fi
