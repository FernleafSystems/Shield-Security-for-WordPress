#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
VERIFY_SCRIPT="$ROOT_DIR/.github/scripts/verify-admin-bundle-safety.sh"

fail() {
	echo "[FAIL] $*" >&2
	exit 1
}

assert_contains() {
	local file="$1"
	local pattern="$2"
	if ! grep -Fq -- "$pattern" "$file"; then
		fail "Expected output to contain: $pattern"
	fi
}

run_expect_success() {
	local fixture_root="$1"
	local output_file="$2"
	shift 2

	if ! bash "$VERIFY_SCRIPT" --root "$fixture_root" "$@" >"$output_file" 2>&1; then
		cat "$output_file" >&2
		fail "Expected command to succeed."
	fi
}

run_expect_failure() {
	local fixture_root="$1"
	local output_file="$2"
	local expected_pattern="$3"
	shift 3

	if bash "$VERIFY_SCRIPT" --root "$fixture_root" "$@" >"$output_file" 2>&1; then
		cat "$output_file" >&2
		fail "Expected command to fail."
	fi
	assert_contains "$output_file" "$expected_pattern"
}

create_fixture_base() {
	local fixture_root="$1"
	mkdir -p "$fixture_root/assets/js" "$fixture_root/assets/dist"

	cat > "$fixture_root/assets/js/plugin-wpadmin.js" <<'EOF'
import "./wpadmin-app.js";
EOF

	cat > "$fixture_root/assets/js/wpadmin-app.js" <<'EOF'
console.log( "wpadmin app" );
EOF

	cat > "$fixture_root/assets/js/plugin-mainwp_server.js" <<'EOF'
import "./mainwp-app.js";
EOF

	cat > "$fixture_root/assets/js/mainwp-app.js" <<'EOF'
import $ from "jquery";
console.log( $.fn ? "jquery available" : "jquery missing" );
EOF

	cat > "$fixture_root/assets/dist/shield-wpadmin.bundle.js" <<'EOF'
/* wpadmin bundle fixture */
EOF

	cat > "$fixture_root/assets/dist/shield-mainwp_server.bundle.js" <<'EOF'
/* mainwp_server bundle fixture */
EOF
}

tmp_root="$(mktemp -d)"
trap 'rm -rf "$tmp_root"' EXIT

[[ -f "$VERIFY_SCRIPT" ]] || fail "Missing verify script: $VERIFY_SCRIPT"

pass_fixture="$tmp_root/pass"
create_fixture_base "$pass_fixture"
run_expect_success "$pass_fixture" "$tmp_root/pass.out"

wpadmin_bootstrap_fixture="$tmp_root/wpadmin-bootstrap"
create_fixture_base "$wpadmin_bootstrap_fixture"
cat > "$wpadmin_bootstrap_fixture/assets/js/wpadmin-app.js" <<'EOF'
import { Modal } from "bootstrap";
console.log( Modal );
EOF
run_expect_failure "$wpadmin_bootstrap_fixture" "$tmp_root/wpadmin-bootstrap.out" "Forbidden import 'bootstrap' reachable in target 'wpadmin'"

wpadmin_jquery_fixture="$tmp_root/wpadmin-jquery"
create_fixture_base "$wpadmin_jquery_fixture"
cat > "$wpadmin_jquery_fixture/assets/js/wpadmin-app.js" <<'EOF'
import $ from "jquery";
console.log( $ );
EOF
run_expect_failure "$wpadmin_jquery_fixture" "$tmp_root/wpadmin-jquery.out" "Forbidden import 'jquery' reachable in target 'wpadmin'"

mainwp_bootstrap_fixture="$tmp_root/mainwp-bootstrap"
create_fixture_base "$mainwp_bootstrap_fixture"
cat > "$mainwp_bootstrap_fixture/assets/js/mainwp-app.js" <<'EOF'
import "bootstrap";
console.log( "bootstrap loaded" );
EOF
run_expect_failure "$mainwp_bootstrap_fixture" "$tmp_root/mainwp-bootstrap.out" "Forbidden import 'bootstrap' reachable in target 'mainwp_server'"

bundle_marker_fixture="$tmp_root/bundle-marker"
create_fixture_base "$bundle_marker_fixture"
cat > "$bundle_marker_fixture/assets/dist/shield-wpadmin.bundle.js" <<'EOF'
throw new TypeError( 'No method named "setting"' );
EOF
run_expect_failure "$bundle_marker_fixture" "$tmp_root/bundle-marker.out" "Bundle marker indicates Bootstrap-style jQuery bridge collision risk for 'wpadmin'" --targets wpadmin

echo "[PASS] verify-admin-bundle-safety.sh regression tests passed"
