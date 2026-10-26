#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DETECT_SCRIPT="$ROOT_DIR/.github/scripts/detect-wp-versions.sh"
TEMP_DIR="$(mktemp -d)"
MOCK_BIN="$TEMP_DIR/bin"
TRACE_FILE="$TEMP_DIR/transport-trace"
STDOUT_FILE="$TEMP_DIR/stdout"
STDERR_FILE="$TEMP_DIR/stderr"
STATUS=0

cleanup() { rm -rf "$TEMP_DIR"; }
trap cleanup EXIT
fail() { echo "[FAIL] $*" >&2; exit 1; }
assert_equals() { [[ "$1" == "$2" ]] || fail "Expected '$1', got '$2'"; }
assert_file_contains() { grep -Fq -- "$2" "$1" || fail "Expected '$2' in $1"; }
assert_file_lacks() { ! grep -Fq -- "$2" "$1" || fail "Unexpected '$2' in $1"; }

mkdir -p "$MOCK_BIN"

cat > "$MOCK_BIN/curl" <<'CURL'
#!/usr/bin/env bash
set -euo pipefail
url="${!#}"
if [[ "$url" == *"version-check"* ]]; then
	printf '%s\n' primary >> "${MOCK_TRACE_FILE:?}"
	case "${MOCK_MODE:-primary}" in
		primary) printf '%sHTTPSTATUS:200' '{"offers":[{"version":"12.9.4"},{"version":"13.0-beta1"},{"version":"12.10.1"},{"version":"12.9.8"},{"version":"12.10.3"},{"version":"12.10.3"}]}' ;;
		x0) printf '%sHTTPSTATUS:200' '{"offers":[{"version":"12.9.8"},{"version":"13.0.2"},{"version":"12.8.9"}]}' ;;
		prefix) printf '%sHTTPSTATUS:200' '{"offers":[{"version":"12.1.9"},{"version":"12.10.3"},{"version":"12.10.1"}]}' ;;
		insufficient) printf '%sHTTPSTATUS:200' '{"offers":[{"version":"12.10.3"},{"version":"12.10.1"}]}' ;;
		*) exit 1 ;;
	esac
elif [[ "$url" == *"stable-check"* ]]; then
	printf '%s\n' secondary >> "${MOCK_TRACE_FILE:?}"
	case "${MOCK_MODE:-primary}" in
		secondary) printf '%sHTTPSTATUS:200' '{"12.9.8":"outdated","12.10.1":"outdated","12.10.3":"latest","13.0.1":"insecure","13.1-beta1":"latest"}' ;;
		insufficient) printf '%sHTTPSTATUS:200' '{"12.10.1":"outdated","12.10.3":"latest"}' ;;
		*) exit 1 ;;
	esac
else
	exit 1
fi
CURL

cat > "$MOCK_BIN/git" <<'GIT'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' git >> "${MOCK_TRACE_FILE:?}"
if [[ "${MOCK_MODE:-}" == "git" ]]; then
	printf '%s\n' \
		'1111111111111111111111111111111111111111 refs/tags/12.9.4' \
		'2222222222222222222222222222222222222222 refs/tags/12.10.1' \
		'3333333333333333333333333333333333333333 refs/tags/12.9.8' \
		'4444444444444444444444444444444444444444 refs/tags/12.10.3' \
		'5555555555555555555555555555555555555555 refs/tags/13.0-beta1'
	exit 0
fi
exit 1
GIT

cat > "$MOCK_BIN/docker" <<'DOCKER'
#!/usr/bin/env bash
printf '%s\n' docker >> "${MOCK_TRACE_FILE:?}"
exit 1
DOCKER

cat > "$MOCK_BIN/sleep" <<'SLEEP'
#!/usr/bin/env bash
exit 0
SLEEP

chmod +x "$MOCK_BIN/curl" "$MOCK_BIN/git" "$MOCK_BIN/docker" "$MOCK_BIN/sleep"
command -v jq >/dev/null 2>&1 || fail 'jq is required for detector regression tests'

capture_detector() {
	: > "$TRACE_FILE"
	: > "$STDOUT_FILE"
	: > "$STDERR_FILE"
	set +e
	MOCK_TRACE_FILE="$TRACE_FILE" PATH="$MOCK_BIN:$PATH" MOCK_MODE="$1" \
		bash "$DETECT_SCRIPT" "${@:2}" > "$STDOUT_FILE" 2> "$STDERR_FILE"
	STATUS=$?
	set -e
}

assert_success() {
	local mode="$1" expected="$2"
	capture_detector "$mode"
	assert_equals 0 "$STATUS"
	assert_equals "$expected" "$(cat "$STDOUT_FILE")"
}

# Unordered candidates, duplicates, multiple patches, numeric series ordering,
# and prerelease rejection.
assert_success primary $'LATEST_VERSION=12.10.3\nPREVIOUS_VERSION=12.9.8'

# X.0 boundary and exact 12.1 versus 12.10 grouping.
assert_success x0 $'LATEST_VERSION=13.0.2\nPREVIOUS_VERSION=12.9.8'
assert_success prefix $'LATEST_VERSION=12.10.3\nPREVIOUS_VERSION=12.1.9'

# Primary failure followed by secondary success. Insecure and prerelease keys
# must not participate in selection.
assert_success secondary $'LATEST_VERSION=12.10.3\nPREVIOUS_VERSION=12.9.8'
assert_equals $'primary\nprimary\nprimary\nsecondary' "$(cat "$TRACE_FILE")"

# Both APIs failing followed by Git success, using the same selector.
assert_success git $'LATEST_VERSION=12.10.3\nPREVIOUS_VERSION=12.9.8'
assert_file_contains "$TRACE_FILE" git

# Every source has fewer than two stable series or is unavailable.
capture_detector insufficient
[[ "$STATUS" -ne 0 ]] || fail 'Insufficient release series unexpectedly succeeded'
assert_file_contains "$STDERR_FILE" 'Primary API, secondary API, and Git tag detection failed'

# All transports unavailable.
capture_detector fail
[[ "$STATUS" -ne 0 ]] || fail 'Unavailable sources unexpectedly succeeded'
assert_file_contains "$TRACE_FILE" primary
assert_file_contains "$TRACE_FILE" secondary
assert_file_contains "$TRACE_FILE" git

for help_argument in -h --help; do
	capture_detector fail "$help_argument"
	assert_equals 0 "$STATUS"
	assert_file_contains "$STDOUT_FILE" 'Usage: detect-wp-versions.sh [-h|--help]'
	assert_file_lacks "$STDOUT_FILE" 'CACHE'
	[[ ! -s "$TRACE_FILE" ]] || fail "$help_argument invoked an external transport"
done

for rejected in -v --version -d --debug --invalid-option; do
	capture_detector fail "$rejected"
	assert_equals 2 "$STATUS"
	[[ ! -s "$TRACE_FILE" ]] || fail "$rejected invoked an external transport"
done

DEBUG=1 capture_detector primary
assert_equals 0 "$STATUS"
assert_file_contains "$STDERR_FILE" '[DEBUG]'

github_output="$TEMP_DIR/github-output"
GITHUB_ACTIONS=true GITHUB_OUTPUT="$github_output" capture_detector primary
assert_equals 0 "$STATUS"
[[ ! -s "$github_output" ]] || fail 'Detector unexpectedly wrote legacy GitHub outputs'

echo '[PASS] detect-wp-versions.sh hermetic regression tests passed'
