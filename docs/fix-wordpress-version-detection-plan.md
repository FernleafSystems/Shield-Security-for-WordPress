# Fix WordPress Version Detection Failures

- **Status**: Plan v4.1 (Addresses review issues #1, #3, #4, #5, #8)
- **Date**: December 2024
- **Scope**: Fix error handling in calling scripts, update fallback versions

## Executive Summary

The WordPress version detection script (`.github/scripts/detect-wp-versions.sh`) **works correctly**. The issues are in how it's called and the outdated fallback versions. This plan makes **minimal targeted fixes** rather than rewriting working code.

### What's Working (Don't Touch)
- Detection script correctly searches API for full version numbers (e.g., "6.8.3")
- Detection script outputs full versions as required by Docker builds
- 5-level fallback system provides reliability
- GitHub Actions integration works

### What's Broken (Fix These)
1. `bin/run-docker-tests.sh` suppresses errors with `2>/dev/null`
2. Fallback versions are outdated (6.8.2/6.7.1)
3. No timeout on detection script call
4. No validation of parsed output

---

## 1. Root Cause Analysis

### 1.1 The Problem
When version detection fails, tests use outdated fallback versions. Users don't see error messages because stderr is suppressed.

### 1.2 Why Previous "Simplification" Plans Were Wrong

The detection script searches the API for **actual downloadable versions**. The Docker infrastructure uses these versions directly:

```yaml
# tests/docker/docker-compose.yml - versions passed to Docker build
WP_VERSION: ${WP_VERSION_LATEST:-6.8.2}
```

```dockerfile
# tests/docker/Dockerfile - versions used in URLs
svn co https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/
curl -L https://wordpress.org/wordpress-${WP_VERSION}.tar.gz
```

The detection script correctly:
1. Fetches latest version from API (e.g., "6.9")
2. Searches API response for actual previous version (e.g., "6.8.3")
3. Falls back to hardcoded versions if API fails

The API returns versions that have corresponding SVN tags and download URLs. The detection script preserves this—**it does not calculate or guess versions**.

**This logic is correct and must be preserved.**

---

## 2. Current State

### 2.1 Detection Script (WORKING - DO NOT MODIFY)

**File**: `.github/scripts/detect-wp-versions.sh` (743 lines)

The script correctly:
- Calls WordPress API with retry and backoff (lines 133-182)
- Parses latest version from `.offers[0].version` (line 295)
- **Searches API for actual previous version** (lines 318-328):
  ```bash
  previous_version=$(echo "$api_response" | jq -r \
      --arg pm "$previous_major_minor" \
      '.offers[] | select(.version | startswith($pm)) | .version' 2>/dev/null | head -n1)
  ```
- Has 5-level fallback: Primary API → Secondary API → GitHub cache → Repository file → Hardcoded (lines 462-501)
- Uses emergency fallback versions if all else fails (lines 38-39, 450-456)
- Outputs to stdout (lines 720-722):
  ```
  LATEST_VERSION=<version>
  PREVIOUS_VERSION=<version>
  ```

**Verified**: Current API returns `6.9` for latest and `6.8.3` for previous.

**The detection script works correctly. Do not rewrite it.**

### 2.2 Calling Script (NEEDS FIXES)

**File**: `bin/run-docker-tests.sh` (lines 38-51)

**Current (broken)**:
```bash
# Detect WordPress versions (exactly like CI does)
echo "📱 Detecting WordPress versions..."
if ! VERSIONS_OUTPUT=$(./.github/scripts/detect-wp-versions.sh 2>/dev/null); then
    echo "❌ WordPress version detection failed, using fallback versions"
    LATEST_VERSION="6.8.2"
    PREVIOUS_VERSION="6.7.1"
else
    LATEST_VERSION=$(echo "$VERSIONS_OUTPUT" | grep "LATEST_VERSION=" | cut -d'=' -f2)
    PREVIOUS_VERSION=$(echo "$VERSIONS_OUTPUT" | grep "PREVIOUS_VERSION=" | cut -d'=' -f2)
fi
```

**Problems**:
1. `2>/dev/null` suppresses all error messages
2. Fallback versions are outdated
3. No timeout protection
4. No validation that parsed versions are non-empty

---

## 3. Fixes Required

### 3.1 Fix `bin/run-docker-tests.sh` (lines 38-51)

**Replace the entire version detection block with**:

```bash
# Detect WordPress versions (exactly like CI does)
echo "📱 Detecting WordPress versions..."

# Run detection script with timeout to prevent hangs
# Using if/else pattern because script has set -e (line 5)
# The if/else ensures non-zero exit codes don't terminate the script
VERSIONS_OUTPUT=""
DETECTION_ERROR=""

if command -v timeout >/dev/null 2>&1; then
    # Linux/Git Bash: use timeout command (60 seconds should be plenty)
    if VERSIONS_OUTPUT=$(timeout 60 ./.github/scripts/detect-wp-versions.sh 2>&1); then
        DETECTION_ERROR=""
    else
        DETECTION_ERROR=$?
        # timeout returns 124 when command times out
        if [[ "$DETECTION_ERROR" == "124" ]]; then
            echo "   ⚠️  Detection script timed out after 60 seconds"
        fi
    fi
else
    # Systems without timeout command (rare - most Git Bash has it)
    if VERSIONS_OUTPUT=$(./.github/scripts/detect-wp-versions.sh 2>&1); then
        DETECTION_ERROR=""
    else
        DETECTION_ERROR=$?
    fi
fi

# Parse the output (head -1 for defensive parsing in case of duplicate lines)
LATEST_VERSION=$(echo "$VERSIONS_OUTPUT" | grep "^LATEST_VERSION=" | head -1 | cut -d'=' -f2 | tr -d '[:space:]')
PREVIOUS_VERSION=$(echo "$VERSIONS_OUTPUT" | grep "^PREVIOUS_VERSION=" | head -1 | cut -d'=' -f2 | tr -d '[:space:]')

# Validate we got versions; use fallback if not
if [[ -z "$LATEST_VERSION" ]] || [[ -z "$PREVIOUS_VERSION" ]]; then
    echo "   ⚠️  Could not detect versions, using fallback"
    # Provide context about what went wrong
    if [[ -n "$DETECTION_ERROR" ]]; then
        echo "   Detection script failed (exit code $DETECTION_ERROR):"
    elif [[ -z "$VERSIONS_OUTPUT" ]]; then
        echo "   Detection script produced no output"
    else
        echo "   Could not parse versions from output:"
    fi
    echo "$VERSIONS_OUTPUT" | head -20 | sed 's/^/      /'
    
    # Fallback versions - UPDATE THESE when WordPress releases new versions
    # Latest: Current major version from https://wordpress.org/download/
    # Previous: Latest patch of previous major from https://wordpress.org/download/releases/
    LATEST_VERSION="6.9"
    PREVIOUS_VERSION="6.8.3"
fi

echo "   Latest WordPress: $LATEST_VERSION"
echo "   Previous WordPress: $PREVIOUS_VERSION"
```

**Why these specific changes**:

| Change | Reason | Source |
|--------|--------|--------|
| `if/else` instead of `\|\|` | Script uses `set -e` (line 5); `if/else` is safer and matches existing code style | Review issue #1, existing code pattern |
| Check for exit code 124 | `timeout` command returns 124 on timeout | `man timeout` |
| `head -1` in parsing | Defensive: ensures only first match is used if output has duplicates | Review issue #3 |
| Three-way error context | Distinguishes: script failed vs no output vs parse failure | Review issue #4 |
| Comment about "Systems without timeout" | More accurate than "Windows PowerShell" (bash scripts don't run in PowerShell) | Review issue #8 |

### 3.2 Update Fallback Versions in Detection Script

**File**: `.github/scripts/detect-wp-versions.sh` (lines 38-39)

**Current**:
```bash
readonly EMERGENCY_LATEST="6.8.2"
readonly EMERGENCY_PREVIOUS="6.7.1"
```

**Updated**:
```bash
# Emergency fallback versions - MUST be full version numbers (major.minor.patch)
# Update these when WordPress releases new major versions
# Check current versions at: https://wordpress.org/download/releases/
readonly EMERGENCY_LATEST="6.9"
readonly EMERGENCY_PREVIOUS="6.8.3"
```

### 3.3 Update Fallback Versions in docker-compose.yml

**File**: `tests/docker/docker-compose.yml`

Update the default values:

**Lines 45, 53, 56 (test-runner-latest)**:
```yaml
WP_VERSION: ${WP_VERSION_LATEST:-6.9}
TEST_WP_VERSION: ${WP_VERSION_LATEST:-6.9}
command: bin/run-tests-docker.sh wordpress_test_latest root testpass mysql-latest ${WP_VERSION_LATEST:-6.9}
```

**Lines 64, 72, 75 (test-runner-previous)**:
```yaml
WP_VERSION: ${WP_VERSION_PREVIOUS:-6.8.3}
TEST_WP_VERSION: ${WP_VERSION_PREVIOUS:-6.8.3}
command: bin/run-tests-docker.sh wordpress_test_previous root testpass mysql-previous ${WP_VERSION_PREVIOUS:-6.8.3}
```

**Lines 83, 91, 94 (legacy test-runner - for backward compatibility)**:
```yaml
WP_VERSION: ${WP_VERSION:-6.8.3}
TEST_WP_VERSION: ${WP_VERSION:-6.8.3}
command: bin/run-tests-docker.sh wordpress_test root testpass mysql ${WP_VERSION:-6.8.3}
```

**Note**: The legacy `test-runner` service currently defaults to `6.4`, which is very outdated. Updating it to `6.8.3` maintains backward compatibility while using a supported version.

### 3.4 Update Repository Fallback File (Optional)

**File**: `.github/data/wp-versions-fallback.txt`

**Current**:
```
6.8.2|6.7.1
```

**Updated**:
```
6.9|6.8.3
```

---

## 4. Version Format Details

### 4.1 What the WordPress API Returns

Verified by calling `https://api.wordpress.org/core/version-check/1.7/`:

| Version Type | Example | Format |
|--------------|---------|--------|
| Latest (current major) | `"6.9"` | Major.Minor (no patch when it's the initial release) |
| Previous (older major) | `"6.8.3"` | Major.Minor.Patch (latest patch of that major) |

### 4.2 What Download URLs Exist

The API provides download URLs that match the version format:
- Latest: `https://downloads.wordpress.org/release/wordpress-6.9.zip` ✅
- Previous: `https://downloads.wordpress.org/release/wordpress-6.8.3.zip` ✅

The Dockerfile uses `.tar.gz` format (`wordpress.org/wordpress-${WP_VERSION}.tar.gz`), which WordPress also provides.

### 4.3 How Detection Script Gets the Previous Version

The detection script searches the API response for the actual previous version (lines 318-320):

```bash
previous_version=$(echo "$api_response" | jq -r \
    --arg pm "$previous_major_minor" \
    '.offers[] | select(.version | startswith($pm)) | .version' 2>/dev/null | head -n1)
```

Given latest `6.9`, it:
1. Calculates target prefix: `6.8`
2. Searches API for versions starting with `6.8`
3. Finds `6.8.3` (the current release in that series)

This ensures we get the actual downloadable version, not just the major.minor prefix.

---

## 5. Implementation Steps

### Step 1: Update `bin/run-docker-tests.sh`

Replace lines 38-51 with the code from Section 3.1.

### Step 2: Update Detection Script Fallbacks

Edit `.github/scripts/detect-wp-versions.sh` lines 38-39 as shown in Section 3.2.

### Step 3: Update docker-compose.yml Fallbacks

Edit `tests/docker/docker-compose.yml` as shown in Section 3.3.

### Step 4: Update Repository Fallback File

Edit `.github/data/wp-versions-fallback.txt` as shown in Section 3.4.

### Step 5: Test Locally

```bash
# Test detection script directly
./.github/scripts/detect-wp-versions.sh

# Expected output (full version numbers):
# LATEST_VERSION=6.9
# PREVIOUS_VERSION=6.8.3

# Test the calling script
./bin/run-docker-tests.sh

# Verify Docker build works with detected versions
```

### Step 6: Test Error Handling

```bash
# Test with network disabled (should use fallback)
# Temporarily rename the script to simulate failure
mv .github/scripts/detect-wp-versions.sh .github/scripts/detect-wp-versions.sh.bak
./bin/run-docker-tests.sh
# Should show warning and use fallback versions
mv .github/scripts/detect-wp-versions.sh.bak .github/scripts/detect-wp-versions.sh
```

---

## 6. Verification Checklist

| Test | Expected Result |
|------|-----------------|
| Run detection script | Outputs `LATEST_VERSION=X.Y` (or `X.Y.Z`) and `PREVIOUS_VERSION=X.Y.Z` |
| Version format | Latest can be `6.9` or `6.9.1`; Previous should be full like `6.8.3` |
| Detection script failure | Shows error message with context (exit code or "no output" or "parse failure") |
| Detection script timeout | Shows "timed out after 60 seconds" message |
| Docker build with detected versions | SVN checkout and WordPress download succeed |
| Network unavailable | Falls back gracefully with warning and fallback versions |

---

## 7. Files Changed Summary

| File | Change Type | Lines | Description |
|------|-------------|-------|-------------|
| `bin/run-docker-tests.sh` | Modify | 38-51 → ~40 lines | Fix error suppression, add timeout with `set -e` safe pattern, add contextual error messages, update fallbacks |
| `.github/scripts/detect-wp-versions.sh` | Modify | 38-39 | Update emergency fallback versions only |
| `tests/docker/docker-compose.yml` | Modify | 45,53,56,64,72,75,83,91,94 | Update default versions for all three services (latest, previous, legacy) |
| `.github/data/wp-versions-fallback.txt` | Modify | 1 line | Update fallback versions |

**Total lines changed**: ~40 lines across 4 files

---

## 8. What This Plan Does NOT Do

1. ❌ Does NOT rewrite the detection script
2. ❌ Does NOT change version format (keeps full versions)
3. ❌ Does NOT remove any fallback levels
4. ❌ Does NOT change the output format
5. ❌ Does NOT affect GitHub Actions workflow

---

## 9. Lessons Learned

### 9.1 Why "Simplification" Was Wrong

The original detection script is 743 lines because it:
- Handles multiple API endpoints with fallback
- Implements retry with exponential backoff
- Caches responses for reliability
- **Searches API for actual previous versions (full version numbers)**
- Provides comprehensive error handling

All of this complexity exists for good reasons. Trying to "simplify" it broke the core requirement of outputting full version numbers.

### 9.2 The Right Approach

1. **Understand before changing** - Trace how data flows through the entire system
2. **Fix what's broken** - The calling script had issues, not the detection script
3. **Minimal changes** - Don't rewrite working code
4. **Preserve requirements** - Full version numbers are required throughout

---

## 10. Maintenance Notes

### 10.1 Updating Fallback Versions

When WordPress releases a new major version, update these files:

1. `.github/scripts/detect-wp-versions.sh` (lines 38-39)
2. `bin/run-docker-tests.sh` (fallback section)
3. `tests/docker/docker-compose.yml` (default values)
4. `.github/data/wp-versions-fallback.txt`

**Always use full version numbers** (e.g., "6.8.3" not "6.8").

Find current versions at: https://wordpress.org/download/releases/

---

## 11. Review Issues Addressed

| Issue # | Description | Status | Resolution |
|---------|-------------|--------|------------|
| #1 | Timeout exit code handling with `set -e` | ✅ Fixed | Changed to `if/else` pattern; added timeout exit code 124 check |
| #2 | Latest version format inconsistency | ✅ Not an issue | Verified: API returns "6.9", download URLs exist for this format |
| #3 | Missing `head -1` in parsing | ✅ Fixed | Added `head -1` for defensive parsing |
| #4 | Error code check logic | ✅ Fixed | Added three-way error context (script failed / no output / parse failure) |
| #5 | docker-compose.yml legacy service | ✅ Fixed | Added update for legacy `test-runner` service (was using 6.4) |
| #6 | Latest version fallback format | ✅ Not an issue | Fallback "6.9" matches API format; noted in maintenance section |
| #7 | Variable initialization order | ✅ Not an issue | Current approach is correct |
| #8 | Comment about Windows PowerShell | ✅ Fixed | Changed to "Systems without timeout command" |
| #9 | Missing format validation | ⚪ Deferred | Detection script already validates; adding more is over-engineering |
| #10 | Documentation format | ✅ Fixed | Clarified in verification checklist |

---

## End of Plan

This plan makes **4 targeted fixes** totaling ~40 lines of changes:
1. Remove error suppression in calling script
2. Add timeout protection with proper `set -e` handling
3. Add output validation with contextual error messages
4. Update outdated fallback versions in 4 files

The detection script (743 lines) is preserved as-is because it works correctly.
