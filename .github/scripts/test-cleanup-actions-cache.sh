#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCRIPT_PATH="$ROOT_DIR/.github/scripts/cleanup-actions-cache.sh"

fail() {
	echo "[FAIL] $*" >&2
	exit 1
}

assert_contains() {
	local file="$1"
	local pattern="$2"
	if ! grep -Fq -- "$pattern" "$file"; then
		fail "Expected pattern not found: $pattern"
	fi
}

assert_exit_code() {
	local actual="$1"
	local expected="$2"
	if [[ "$actual" -ne "$expected" ]]; then
		fail "Expected exit code $expected, got $actual"
	fi
}

run_cleanup() {
	local list_json="$1"
	local output_file="$2"
	local delete_log="$3"
	local expected_exit="$4"
	shift 4

	set +e
	MOCK_GH_LIST_JSON="$list_json" \
	MOCK_GH_DELETE_LOG="$delete_log" \
	PATH="$MOCK_BIN:$PATH" \
	"$SCRIPT_PATH" "$@" >"$output_file" 2>&1
	local exit_code=$?
	set -e

	assert_exit_code "$exit_code" "$expected_exit"
}

tmp_root="$(mktemp -d)"
trap 'rm -rf "$tmp_root"' EXIT

MOCK_BIN="$tmp_root/bin"
mkdir -p "$MOCK_BIN"

cat > "$MOCK_BIN/gh" <<'GH'
#!/usr/bin/env bash
set -euo pipefail

if [[ "${1:-}" != "cache" ]]; then
	echo "unexpected gh args: $*" >&2
	exit 1
fi

case "${2:-}" in
	list)
		printf '%s\n' "${MOCK_GH_LIST_JSON:-[]}"
		;;
	delete)
		printf '%s\n' "${3:-}" >> "${MOCK_GH_DELETE_LOG}"
		;;
	*)
		echo "unexpected gh cache subcommand: ${2:-}" >&2
		exit 1
		;;
esac
GH

chmod +x "$MOCK_BIN/gh"
chmod +x "$SCRIPT_PATH"

command -v jq >/dev/null 2>&1 || fail "jq is required for tests"

fresh_ts="$(date -u -d '1 day ago' +%Y-%m-%dT%H:%M:%S).123456Z"
older_ts="$(date -u -d '2 days ago' +%Y-%m-%dT%H:%M:%S).111111Z"
stale_ts="$(date -u -d '10 days ago' +%Y-%m-%dT%H:%M:%S).654321Z"

# Case 1: Fractional seconds parse and retention selects stale cache.
deletes_case1="$tmp_root/deletes_case1.log"
output_case1="$tmp_root/case1.out"
run_cleanup "$(cat <<JSON
[
  {"id": 101, "key": "buildkit-fresh", "sizeInBytes": 1048576, "lastAccessedAt": "$fresh_ts"},
  {"id": 202, "key": "index-buildkit-old", "sizeInBytes": 1048576, "lastAccessedAt": "$stale_ts"}
]
JSON
)" "$output_case1" "$deletes_case1" 0 \
	--dry-run false --retention-days 3 --target-buildkit-gb 100

assert_contains "$output_case1" "Deleted cache entries: 1"
assert_contains "$output_case1" "Delete[retention] id=202"
if [[ "$(wc -l < "$deletes_case1")" -ne 1 ]]; then
	fail "Expected exactly one delete in case 1"
fi
assert_contains "$deletes_case1" "202"

# Case 2: Cap selection removes oldest entries when within retention.
deletes_case2="$tmp_root/deletes_case2.log"
output_case2="$tmp_root/case2.out"
run_cleanup "$(cat <<JSON
[
  {"id": 301, "key": "buildkit-cap-oldest", "sizeInBytes": 5368709120, "lastAccessedAt": "$older_ts"},
  {"id": 302, "key": "buildkit-cap-newer", "sizeInBytes": 3221225472, "lastAccessedAt": "$fresh_ts"}
]
JSON
)" "$output_case2" "$deletes_case2" 0 \
	--dry-run false --retention-days 30 --target-buildkit-gb 2

assert_contains "$output_case2" "Delete[cap] id=301"
if [[ "$(wc -l < "$deletes_case2")" -ne 2 ]]; then
	fail "Expected two deletes in case 2"
fi
assert_contains "$deletes_case2" "301"
assert_contains "$deletes_case2" "302"

# Case 3: Dry run plans deletion but performs none.
deletes_case3="$tmp_root/deletes_case3.log"
output_case3="$tmp_root/case3.out"
run_cleanup "$(cat <<JSON
[
  {"id": 401, "key": "buildkit-dry-run", "sizeInBytes": 1048576, "lastAccessedAt": "$stale_ts"}
]
JSON
)" "$output_case3" "$deletes_case3" 0 \
	--dry-run true --retention-days 3 --target-buildkit-gb 100

assert_contains "$output_case3" "Planned deletions: 1 cache entries"
assert_contains "$output_case3" "Dry run complete"
if [[ -s "$deletes_case3" ]]; then
	fail "Expected no delete calls in dry-run case"
fi

# Case 4: Missing timestamp fails fast with explicit parse error.
deletes_case4="$tmp_root/deletes_case4.log"
output_case4="$tmp_root/case4.out"
run_cleanup '[
  {"id": 501, "key": "buildkit-missing-ts", "sizeInBytes": 1048576}
]' "$output_case4" "$deletes_case4" 5 \
	--dry-run true --retention-days 3 --target-buildkit-gb 100

assert_contains "$output_case4" "failed to parse BuildKit cache metadata"
assert_contains "$output_case4" "Missing lastAccessedAt for cache id=501 key=buildkit-missing-ts"
assert_contains "$output_case4" "::error::BuildKit cache metadata parse failed."

# Case 5: Invalid timestamp fails fast with explicit parse error.
deletes_case5="$tmp_root/deletes_case5.log"
output_case5="$tmp_root/case5.out"
run_cleanup '[
  {"id": 601, "key": "buildkit-invalid-ts", "sizeInBytes": 1048576, "lastAccessedAt": "not-a-date"}
]' "$output_case5" "$deletes_case5" 5 \
	--dry-run true --retention-days 3 --target-buildkit-gb 100

assert_contains "$output_case5" "Invalid lastAccessedAt for cache id=601 key=buildkit-invalid-ts value=not-a-date"
assert_contains "$output_case5" "::error::BuildKit cache metadata parse failed."

echo "[PASS] cleanup-actions-cache.sh regression tests passed"
