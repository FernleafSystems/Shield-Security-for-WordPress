#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DETECT_SCRIPT="$ROOT_DIR/.github/scripts/detect-wp-versions.sh"
TEMP_DIR="$(mktemp -d)"
MOCK_BIN="$TEMP_DIR/bin"
TEST_STATE="$TEMP_DIR/state"
TRACE_FILE="$TEST_STATE/transport-trace"
CAPTURED_STDOUT="$TEST_STATE/captured-stdout"
CAPTURED_STDERR="$TEST_STATE/captured-stderr"
CAPTURED_STATUS=0

cleanup() {
	rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

fail() {
	echo "[FAIL] $*" >&2
	exit 1
}

assert_equals() {
	local expected="$1"
	local actual="$2"
	if [[ "$actual" != "$expected" ]]; then
		fail "Expected '$expected', got '$actual'"
	fi
}

assert_contains() {
	local file="$1"
	local expected="$2"
	if ! grep -Fqx -- "$expected" "$file"; then
		fail "Expected line not found: $expected"
	fi
}

assert_file_contains() {
	local file="$1"
	local expected="$2"
	if ! grep -Fq -- "$expected" "$file"; then
		fail "Expected text not found: $expected"
	fi
}

assert_file_lacks() {
	local file="$1"
	local unexpected="$2"
	if grep -Fq -- "$unexpected" "$file"; then
		fail "Unexpected text found: $unexpected"
	fi
}

assert_trace_empty() {
	if [[ -s "$TRACE_FILE" ]]; then
		fail "Detector invoked external transport: $(tr '\n' ' ' < "$TRACE_FILE")"
	fi
}

run_detector() {
	local working_dir="$1"
	shift
	(
		cd "$working_dir"
		SHIELD_WP_API_CACHE_DIR="${DETECTOR_CACHE_DIR:-$TEST_STATE/cache}" \
		MOCK_TRACE_FILE="$TRACE_FILE" \
		PATH="$MOCK_BIN:$PATH" \
		bash "$DETECT_SCRIPT" "$@"
	)
}

capture_detector() {
	local working_dir="$1"
	local cache_dir="$2"
	shift 2

	: > "$TRACE_FILE"
	: > "$CAPTURED_STDOUT"
	: > "$CAPTURED_STDERR"

	set +e
	DETECTOR_CACHE_DIR="$cache_dir" run_detector "$working_dir" "$@" > "$CAPTURED_STDOUT" 2> "$CAPTURED_STDERR"
	CAPTURED_STATUS=$?
	set -e
}

assert_rejected() {
	local test_name="$1"
	shift
	local cache_dir="$TEST_STATE/rejected-$REJECTED_CASE"
	REJECTED_CASE=$((REJECTED_CASE + 1))

	rm -rf "$cache_dir"
	capture_detector "$ROOT_DIR" "$cache_dir" "$@"
	if [[ "$CAPTURED_STATUS" -ne 2 ]]; then
		fail "$test_name expected exit 2, got $CAPTURED_STATUS"
	fi
	assert_trace_empty
	if [[ -e "$cache_dir" ]]; then
		fail "$test_name initialized detector cache"
	fi
}

mkdir -p "$MOCK_BIN" "$TEST_STATE"

cat > "$MOCK_BIN/curl" <<'CURL'
#!/usr/bin/env bash
set -euo pipefail

printf '%s\n' 'curl' >> "${MOCK_TRACE_FILE:?}"
url="${!#}"
case "${MOCK_CURL_MODE:-primary}" in
	primary)
		printf '%sHTTPSTATUS:200' '{"offers":[{"version":"9.4.2"},{"version":"9.3.7"}]}'
		;;
	secondary)
		if [[ "$url" == *"stable-check"* ]]; then
			printf '%sHTTPSTATUS:200' '{"latest":"9.4.2","9.3.7":"9.3.7","9.4.2":"9.4.2"}'
		else
			exit 1
		fi
		;;
	*)
		exit 1
		;;
esac
CURL

cat > "$MOCK_BIN/git" <<'GIT'
#!/usr/bin/env bash
set -euo pipefail

printf '%s\n' 'git' >> "${MOCK_TRACE_FILE:?}"
if [[ "${MOCK_GIT_MODE:-fail}" == "tags" ]]; then
	printf '%s\n' \
		'1111111111111111111111111111111111111111	refs/tags/9.3.7' \
		'2222222222222222222222222222222222222222	refs/tags/9.4.2'
	exit 0
fi
exit 1
GIT

cat > "$MOCK_BIN/docker" <<'DOCKER'
#!/usr/bin/env bash
printf '%s\n' 'docker' >> "${MOCK_TRACE_FILE:?}"
exit 1
DOCKER

cat > "$MOCK_BIN/sleep" <<'SLEEP'
#!/usr/bin/env bash
printf '%s\n' 'sleep' >> "${MOCK_TRACE_FILE:?}"
exit 0
SLEEP

chmod +x "$MOCK_BIN/curl" "$MOCK_BIN/git" "$MOCK_BIN/docker" "$MOCK_BIN/sleep"

command -v jq >/dev/null 2>&1 || fail 'jq is required for detector regression tests'

rm -rf "$TEST_STATE/cache"
: > "$TRACE_FILE"
primary_output="$(MOCK_CURL_MODE=primary MOCK_GIT_MODE=fail run_detector "$ROOT_DIR")"
assert_equals $'LATEST_VERSION=9.4.2\nPREVIOUS_VERSION=9.3.7' "$primary_output"
assert_file_contains "$TRACE_FILE" 'curl'
if ! find "$TEST_STATE/cache" -type f -name 'wp-api-*.json' -print -quit | grep -q .; then
	fail 'Primary detection did not populate isolated cache'
fi

rm -rf "$TEST_STATE/cache"
: > "$TRACE_FILE"
secondary_output="$(MOCK_CURL_MODE=secondary MOCK_GIT_MODE=fail run_detector "$ROOT_DIR")"
assert_equals $'LATEST_VERSION=9.4.2\nPREVIOUS_VERSION=9.3.7' "$secondary_output"

rm -rf "$TEST_STATE/cache"
: > "$TRACE_FILE"
git_output="$(MOCK_CURL_MODE=fail MOCK_GIT_MODE=tags run_detector "$ROOT_DIR")"
assert_equals $'LATEST_VERSION=9.4.2\nPREVIOUS_VERSION=9.3.7' "$git_output"

help_case=0
for help_argument in -h --help; do
	help_cache="$TEST_STATE/help-$help_case"
	help_case=$((help_case + 1))
	rm -rf "$help_cache"
	capture_detector "$ROOT_DIR" "$help_cache" "$help_argument"
	assert_equals "0" "$CAPTURED_STATUS"
	assert_file_contains "$CAPTURED_STDOUT" 'Usage: detect-wp-versions.sh [-h|--help]'
	assert_file_lacks "$CAPTURED_STDOUT" '--version'
	assert_file_lacks "$CAPTURED_STDOUT" '--debug'
	assert_trace_empty
	if [[ -e "$help_cache" ]]; then
		fail "$help_argument initialized detector cache"
	fi
done

REJECTED_CASE=0
assert_rejected 'Short version option' -v
assert_rejected 'Long version option' --version
assert_rejected 'Short debug option' -d
assert_rejected 'Long debug option' --debug
assert_rejected 'Unknown option' --invalid-option
assert_rejected 'Unknown then version' --invalid-option --version
assert_rejected 'Version then unknown' --version --invalid-option
assert_rejected 'Help mixed with unknown' --help --invalid-option
assert_rejected 'Duplicate help' --help --help

debug_cache="$TEST_STATE/debug-cache"
rm -rf "$debug_cache"
DEBUG=1 MOCK_CURL_MODE=primary MOCK_GIT_MODE=fail capture_detector "$ROOT_DIR" "$debug_cache"
assert_equals "0" "$CAPTURED_STATUS"
assert_equals $'LATEST_VERSION=9.4.2\nPREVIOUS_VERSION=9.3.7' "$(cat "$CAPTURED_STDOUT")"
assert_file_contains "$CAPTURED_STDERR" '[DEBUG]'

cache_test_root="$TEMP_DIR/cache-isolation-root"
cache_a="$TEST_STATE/cache-a"
cache_b="$TEST_STATE/cache-b"
mkdir -p "$cache_test_root"
rm -rf "$cache_a" "$cache_b"

MOCK_CURL_MODE=primary MOCK_GIT_MODE=fail capture_detector "$cache_test_root" "$cache_a"
assert_equals "0" "$CAPTURED_STATUS"
assert_equals $'LATEST_VERSION=9.4.2\nPREVIOUS_VERSION=9.3.7' "$(cat "$CAPTURED_STDOUT")"

MOCK_CURL_MODE=fail MOCK_GIT_MODE=fail capture_detector "$cache_test_root" "$cache_a"
assert_equals "0" "$CAPTURED_STATUS"
assert_equals $'LATEST_VERSION=9.4.2\nPREVIOUS_VERSION=9.3.7' "$(cat "$CAPTURED_STDOUT")"

MOCK_CURL_MODE=fail MOCK_GIT_MODE=fail capture_detector "$cache_test_root" "$cache_b"
if [[ "$CAPTURED_STATUS" -eq 0 ]]; then
	fail 'Empty cache root unexpectedly reused another cache'
fi

github_output="$TEMP_DIR/github-output"
rm -rf "$TEST_STATE/cache"
: > "$TRACE_FILE"
GITHUB_ACTIONS=true GITHUB_OUTPUT="$github_output" MOCK_CURL_MODE=primary MOCK_GIT_MODE=fail run_detector "$ROOT_DIR" >/dev/null
assert_contains "$github_output" 'latest=9.4.2'
assert_contains "$github_output" 'previous=9.3.7'
assert_contains "$github_output" 'lts=9.3.7'
assert_contains "$github_output" 'matrix_ready=true'
assert_file_lacks "$github_output" 'detection_method='
assert_file_lacks "$github_output" 'cache_used='

capture_detector "$ROOT_DIR" "$TEST_STATE/help-cleanup" --help
assert_file_lacks "$CAPTURED_STDOUT" 'Cache TTL'
assert_file_lacks "$CAPTURED_STDOUT" 'SUPPORTED PHP VERSIONS'
assert_file_lacks "$CAPTURED_STDOUT" 'detection_method'
assert_file_lacks "$CAPTURED_STDOUT" 'cache_used'

echo '[PASS] detect-wp-versions.sh hermetic regression tests passed'
