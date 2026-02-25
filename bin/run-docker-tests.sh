#!/bin/bash
# Thin delegator to the PHP runner.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
RUNNER="$PROJECT_ROOT/bin/run-docker-tests.php"

if [ ! -f "$RUNNER" ]; then
	echo "Error: runner not found: $RUNNER" >&2
	exit 1
fi

if ! command -v php >/dev/null 2>&1; then
	echo "Error: PHP is required to run bin/run-docker-tests.php" >&2
	exit 1
fi

exec php "$RUNNER" "$@"
