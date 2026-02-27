#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
TARGETS_CSV="wpadmin,mainwp_server"

usage() {
	cat <<'EOF'
Usage: verify-admin-bundle-safety.sh [options]

Options:
  --root <path>         Repository root path (default: auto-detect from script location)
  --targets <csv>       Targets to validate (default: wpadmin,mainwp_server)
  -h, --help            Show this help message

Targets:
  wpadmin       Validates shared wp-admin bundle safety
  mainwp_server Validates MainWP extension admin bundle safety
EOF
}

log_info() {
	echo "[INFO] $*"
}

log_error() {
	echo "[ERROR] $*" >&2
}

fail() {
	log_error "$*"
	exit 1
}

trim_csv_item() {
	local value="$1"
	value="${value#"${value%%[![:space:]]*}"}"
	value="${value%"${value##*[![:space:]]}"}"
	printf '%s' "$value"
}

while [[ $# -gt 0 ]]; do
	case "$1" in
		--root)
			ROOT_DIR="$2"
			shift 2
			;;
		--targets)
			TARGETS_CSV="$2"
			shift 2
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			fail "Unknown argument: $1"
			;;
	esac
done

command -v node >/dev/null 2>&1 || fail "Node.js is required but not available in PATH."

ROOT_DIR="$(cd "$ROOT_DIR" && pwd)"

declare -a TARGETS=()
IFS=',' read -r -a raw_targets <<< "$TARGETS_CSV"
for raw_target in "${raw_targets[@]}"; do
	target="$(trim_csv_item "$raw_target")"
	[[ -n "$target" ]] && TARGETS+=( "$target" )
done

[[ ${#TARGETS[@]} -gt 0 ]] || fail "No targets supplied."

target_entry() {
	case "$1" in
		wpadmin) printf '%s' "assets/js/plugin-wpadmin.js" ;;
		mainwp_server) printf '%s' "assets/js/plugin-mainwp_server.js" ;;
		*) return 1 ;;
	esac
}

target_bundle() {
	case "$1" in
		wpadmin) printf '%s' "assets/dist/shield-wpadmin.bundle.js" ;;
		mainwp_server) printf '%s' "assets/dist/shield-mainwp_server.bundle.js" ;;
		*) return 1 ;;
	esac
}

target_forbidden_imports() {
	case "$1" in
		wpadmin) printf '%s' "bootstrap,jquery" ;;
		mainwp_server) printf '%s' "bootstrap" ;;
		*) return 1 ;;
	esac
}

check_import_graph() {
	local root_dir="$1"
	local target="$2"
	local entry_rel="$3"
	local forbidden_csv="$4"

	node - "$root_dir" "$target" "$entry_rel" "$forbidden_csv" <<'NODE'
const fs = require('fs');
const path = require('path');

const rootDir = process.argv[2];
const target = process.argv[3];
const entryRel = process.argv[4];
const forbiddenCsv = process.argv[5];
const forbiddenPkgs = forbiddenCsv.split(',').map((x) => x.trim()).filter(Boolean);

const entryAbs = path.resolve(rootDir, entryRel);

function fail(msg) {
	console.error(`[FAIL] ${msg}`);
	process.exit(1);
}

function normalizePath(p) {
	return p.split(path.sep).join('/');
}

function isForbiddenImport(spec) {
	return forbiddenPkgs.some((pkg) => spec === pkg || spec.startsWith(`${pkg}/`));
}

function resolveLocalImport(fromFile, spec) {
	const base = path.resolve(path.dirname(fromFile), spec);
	const candidates = [
		base,
		`${base}.js`,
		`${base}.mjs`,
		path.join(base, 'index.js'),
	];

	for (const candidate of candidates) {
		if (fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
			return candidate;
		}
	}
	return null;
}

if (!fs.existsSync(entryAbs)) {
	fail(`Entry file missing for target '${target}': ${entryRel}`);
}

const visited = new Set();
const queue = [entryAbs];

const importRegex = /\bimport\s+(?:[^'"]+\s+from\s+)?['"]([^'"]+)['"]/g;

while (queue.length > 0) {
	const fileAbs = queue.pop();
	if (visited.has(fileAbs)) {
		continue;
	}
	visited.add(fileAbs);

	const fileRel = normalizePath(path.relative(rootDir, fileAbs));
	const content = fs.readFileSync(fileAbs, 'utf8');

	let match;
	while ((match = importRegex.exec(content)) !== null) {
		const spec = match[1];

		if (isForbiddenImport(spec)) {
			fail(`Forbidden import '${spec}' reachable in target '${target}' via ${fileRel}`);
		}

		if (spec.startsWith('.')) {
			const resolved = resolveLocalImport(fileAbs, spec);
			if (resolved !== null && resolved.endsWith('.js')) {
				queue.push(resolved);
			}
		}
	}
}

console.log(`[PASS] Source dependency graph safe for target '${target}'`);
NODE
}

check_bundle_markers() {
	local root_dir="$1"
	local target="$2"
	local bundle_rel="$3"
	local bundle_path="$root_dir/$bundle_rel"

	[[ -f "$bundle_path" ]] || fail "Bundle file missing for target '$target': $bundle_rel"

	if grep -Fq 'No method named "' "$bundle_path"; then
		fail "Bundle marker indicates Bootstrap-style jQuery bridge collision risk for '$target': $bundle_rel"
	fi

	log_info "Bundle marker check passed for '$target'"
}

log_info "Verifying admin bundle safety in: $ROOT_DIR"

for target in "${TARGETS[@]}"; do
	entry_rel="$(target_entry "$target")" || fail "Unsupported target: $target"
	bundle_rel="$(target_bundle "$target")" || fail "Unsupported target: $target"
	forbidden_csv="$(target_forbidden_imports "$target")" || fail "Unsupported target: $target"

	log_info "Checking target '$target'"
	check_import_graph "$ROOT_DIR" "$target" "$entry_rel" "$forbidden_csv"
	check_bundle_markers "$ROOT_DIR" "$target" "$bundle_rel"
done

log_info "All requested targets passed admin bundle safety checks."
