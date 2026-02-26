#!/usr/bin/env bash

set -euo pipefail

RETENTION_DAYS=3
TARGET_BUILDKIT_GB=7
DRY_RUN=false
LIST_LIMIT=5000

usage() {
    cat <<'EOF'
Usage: cleanup-actions-cache.sh [options]

Options:
  --retention-days <days>       Delete BuildKit cache entries older than this many days (default: 3)
  --target-buildkit-gb <gb>     Keep BuildKit cache at or below this size budget in GiB (default: 7)
  --dry-run <true|false>        Show planned deletions without deleting (default: false)
  -h, --help                    Show this help message
EOF
}

log_info() {
    echo "[INFO] $*"
}

log_warn() {
    echo "[WARN] $*"
}

log_error() {
    echo "[ERROR] $*" >&2
}

bytes_to_gib() {
    awk -v b="$1" 'BEGIN { printf "%.3f", b / 1024 / 1024 / 1024 }'
}

bytes_to_mib() {
    awk -v b="$1" 'BEGIN { printf "%.2f", b / 1024 / 1024 }'
}

fetch_buildkit_json() {
    gh cache list \
        --limit "$LIST_LIMIT" \
        --sort last_accessed_at \
        --order asc \
        --json id,key,sizeInBytes,lastAccessedAt \
    | jq '
        def normalize_iso8601:
            sub("\\.[0-9]+Z$"; "Z");

        def parse_epoch_strict($entry):
            if (($entry.lastAccessedAt | type) != "string") or ($entry.lastAccessedAt | length == 0) then
                error("Missing lastAccessedAt for cache id=\($entry.id) key=\($entry.key)")
            else
                ($entry.lastAccessedAt | normalize_iso8601) as $normalized
                | (try ($normalized | fromdateiso8601) catch error("Invalid lastAccessedAt for cache id=\($entry.id) key=\($entry.key) value=\($entry.lastAccessedAt). Expected ISO8601 UTC timestamp, e.g. 2026-02-25T12:49:04Z"))
            end;

        [
            .[]
            | select((.key | startswith("buildkit-")) or (.key | startswith("index-buildkit-")))
            | . + {
                lastAccessEpoch: parse_epoch_strict(.)
            }
        ]
        | sort_by(.lastAccessEpoch)
    '
}

fetch_buildkit_json_or_fail() {
    local context="$1"
    local output

    if ! output="$(fetch_buildkit_json 2>&1)"; then
        log_error "$context: failed to parse BuildKit cache metadata."
        log_error "$output"
        log_error "Diagnostic command: gh cache list --limit 20 --sort last_accessed_at --order asc --json id,key,lastAccessedAt"
        echo "::error::BuildKit cache metadata parse failed. Expected lastAccessedAt in ISO8601 UTC format (example: 2026-02-25T12:49:04Z)." >&2
        exit 5
    fi

    printf '%s\n' "$output"
}

build_cleanup_plan() {
    local buildkit_json="$1"

    jq \
        --argjson now "$now_epoch" \
        --argjson retention "$retention_seconds" \
        --argjson target "$target_bytes" \
        '
        def total_bytes:
            (map(.sizeInBytes) | add // 0);

        def mark_retention($now; $retention):
            map(
                . + {
                    reason: (
                        if (($now - .lastAccessEpoch) > $retention) then
                            "retention"
                        else
                            null
                        end
                    )
                }
            );

        def mark_cap($target):
            (map(select(.reason != null) | .sizeInBytes) | add // 0) as $already_selected
            | (total_bytes - $already_selected) as $remaining_initial
            | reduce .[] as $entry (
                {
                    remaining: $remaining_initial,
                    entries: []
                };
                if $entry.reason != null then
                    .entries += [$entry]
                elif .remaining <= $target then
                    .entries += [$entry]
                else
                    .remaining = (.remaining - $entry.sizeInBytes)
                    | .entries += [$entry + {reason: "cap"}]
                end
            )
            | .entries;

        mark_retention($now; $retention) as $with_retention
        | ($with_retention | total_bytes) as $total_before
        | ($with_retention | mark_cap($target)) as $planned_entries
        | ($planned_entries | map(select(.reason != null) | .sizeInBytes) | add // 0) as $selected_bytes
        | ($planned_entries | map(select(.reason != null)) | length) as $planned_count
        | {
            total_before: $total_before,
            selected_bytes: $selected_bytes,
            planned_count: $planned_count,
            entries: $planned_entries
        }
        ' <<<"$buildkit_json"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --retention-days)
            RETENTION_DAYS="$2"
            shift 2
            ;;
        --target-buildkit-gb)
            TARGET_BUILDKIT_GB="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            log_error "Unknown argument: $1"
            usage
            exit 1
            ;;
    esac
done

if ! [[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]]; then
    log_error "--retention-days must be a non-negative integer"
    exit 1
fi

if ! [[ "$TARGET_BUILDKIT_GB" =~ ^[0-9]+$ ]] || [[ "$TARGET_BUILDKIT_GB" -le 0 ]]; then
    log_error "--target-buildkit-gb must be a positive integer"
    exit 1
fi

if [[ "$DRY_RUN" != "true" && "$DRY_RUN" != "false" ]]; then
    log_error "--dry-run must be true or false"
    exit 1
fi

for required_cmd in gh jq; do
    if ! command -v "$required_cmd" >/dev/null 2>&1; then
        log_error "Required command not found: $required_cmd"
        exit 1
    fi
done

now_epoch="$(date -u +%s)"
retention_seconds="$((RETENTION_DAYS * 24 * 60 * 60))"
target_bytes="$((TARGET_BUILDKIT_GB * 1024 * 1024 * 1024))"

log_info "Starting GitHub Actions cache cleanup"
log_info "Dry run: $DRY_RUN"
log_info "Retention: ${RETENTION_DAYS} day(s)"
log_info "Target BuildKit budget: ${TARGET_BUILDKIT_GB} GiB"

buildkit_json="$(fetch_buildkit_json_or_fail "Initial cache fetch")"
plan_json="$(build_cleanup_plan "$buildkit_json")"
buildkit_count="$(jq '.entries | length' <<<"$plan_json")"

if [[ "$buildkit_count" -eq 0 ]]; then
    log_info "No BuildKit caches found. Nothing to do."
    exit 0
fi

total_before="$(jq '.total_before' <<<"$plan_json")"
selected_bytes="$(jq '.selected_bytes' <<<"$plan_json")"
planned_count="$(jq '.planned_count' <<<"$plan_json")"

log_info "BuildKit cache count: $buildkit_count"
log_info "BuildKit size before cleanup: $(bytes_to_gib "$total_before") GiB"
log_info "Planned deletions: $planned_count cache entries ($(bytes_to_gib "$selected_bytes") GiB)"

if [[ "$planned_count" -eq 0 ]]; then
    log_info "No deletions required. Cache is already within policy."
    exit 0
fi

deleted_count=0
deleted_bytes=0
failed_count=0

while IFS=$'\t' read -r id reason size_bytes key last_accessed; do
    log_info "Delete[$reason] id=$id size=$(bytes_to_mib "$size_bytes") MiB last_accessed=$last_accessed key=$key"

    if [[ "$DRY_RUN" == "true" ]]; then
        continue
    fi

    if gh cache delete "$id" >/dev/null 2>&1; then
        deleted_count="$((deleted_count + 1))"
        deleted_bytes="$((deleted_bytes + size_bytes))"
    else
        failed_count="$((failed_count + 1))"
        log_warn "Failed to delete cache id=$id; continuing"
    fi
done < <(jq -r '.entries[] | select(.reason != null) | [.id, .reason, .sizeInBytes, .key, .lastAccessedAt] | @tsv' <<<"$plan_json")

if [[ "$DRY_RUN" == "true" ]]; then
    estimated_after="$((total_before - selected_bytes))"
    log_info "Dry run complete"
    log_info "Estimated BuildKit size after cleanup: $(bytes_to_gib "$estimated_after") GiB"
    exit 0
fi

after_json="$(fetch_buildkit_json_or_fail "Post-delete cache fetch")"
after_total="$(jq '[.[].sizeInBytes] | add // 0' <<<"$after_json")"

log_info "Deleted cache entries: $deleted_count"
log_info "Deleted bytes: $(bytes_to_gib "$deleted_bytes") GiB"
log_info "Failed deletions: $failed_count"
log_info "BuildKit size after cleanup: $(bytes_to_gib "$after_total") GiB"

if (( failed_count > 0 )); then
    log_warn "Cleanup completed with partial failures."
fi
