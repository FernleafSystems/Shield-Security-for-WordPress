#!/bin/bash
# Deprecated compatibility shim.
# Active test/analysis routing now lives in `php bin/shield`.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

MODE_COMMAND="test:package-full"

for arg in "$@"; do
	case "$arg" in
		--analyze-package)
			MODE_COMMAND="analyze:package"
			;;
		--help|-h)
			cat <<'EOF'
Usage: ./bin/run-docker-tests.legacy.sh [--analyze-package]

Deprecated:
  This script is retained only for backwards compatibility.
  Use `php bin/shield test:package-full` or `php bin/shield analyze:package`.
EOF
			exit 0
			;;
		*)
			echo "Error: Unknown argument: $arg" >&2
			echo "Use --help for usage." >&2
			exit 1
			;;
	esac
done

if ! command -v php >/dev/null 2>&1; then
	echo "Error: PHP is required to run bin/shield" >&2
	exit 1
fi

echo "Warning: ./bin/run-docker-tests.legacy.sh is deprecated. Use php bin/shield ${MODE_COMMAND}" >&2
exec php "$PROJECT_ROOT/bin/shield" "$MODE_COMMAND"
