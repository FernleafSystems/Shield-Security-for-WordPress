#!/usr/bin/env bash
# Load Strauss version for packaging/tests.
# Usage: source .github/scripts/read-packager-config.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
CONFIG_FILE="${PROJECT_ROOT}/.github/config/packager.conf"

if [ ! -f "${CONFIG_FILE}" ]; then
  echo "❌ packager config not found: ${CONFIG_FILE}" >&2
  exit 1
fi

# shellcheck source=/dev/null
source "${CONFIG_FILE}"

if [ -z "${STRAUSS_VERSION:-}" ]; then
  echo "❌ STRAUSS_VERSION is not set in ${CONFIG_FILE}" >&2
  exit 1
fi

# Strip leading "v" if present
STRAUSS_VERSION="${STRAUSS_VERSION#v}"

export STRAUSS_VERSION
export SHIELD_STRAUSS_VERSION="${SHIELD_STRAUSS_VERSION:-${STRAUSS_VERSION}}"

echo "Using STRAUSS_VERSION=${STRAUSS_VERSION}"

