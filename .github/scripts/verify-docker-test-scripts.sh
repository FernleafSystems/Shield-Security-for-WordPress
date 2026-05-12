#!/usr/bin/env bash

set -euo pipefail

scripts=(
  "bin/run-tests-docker.sh"
  "bin/install-wp-tests.sh"
)

has_errors=0
for script in "${scripts[@]}"; do
  if [[ ! -f "$script" ]]; then
    echo "::error title=Missing script::Required script not found: $script"
    has_errors=1
    continue
  fi

  if [[ ! -x "$script" ]]; then
    perms="$(stat -c '%A %a' "$script" 2>/dev/null || true)"
    if [[ -z "$perms" ]]; then
      perms="$(ls -l "$script")"
    fi
    echo "::error title=Script not executable::$script must be committed with executable bit (+x). Current permissions: $perms"
    echo "::error::Fix locally with: git update-index --chmod=+x -- $script"
    has_errors=1
    continue
  fi

  echo "OK: $script is executable"
done

if [[ "$has_errors" -ne 0 ]]; then
  echo "One or more required test scripts are not executable. Failing early before Docker runtime checks."
  exit 1
fi
