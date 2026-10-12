# Plugin and theme snapshot hash algorithms

Shield can request published plugin/theme file hashes and create local file-integrity baselines using `md5`, `sha1`, or `sha256`. Shield's full-scan preparation, scheduled snapshot maintenance, and plugin/theme update cleanup explicitly request SHA256. Public build and promotion helpers retain MD5 as their default when called without an algorithm.

## Selecting an algorithm

The caller passes the algorithm directly for a new snapshot or an eligible published promotion:

```php
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\Build;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\PromoteLocalBaseline;

( new Build() )->setAsset( $asset )->run( 'sha256' );
( new PromoteLocalBaseline() )->setAsset( $asset )->run( 'sha256' );
```

`ScheduleBuildAll::build( 'sha256' )` passes the same algorithm to missing-snapshot builds and due promotions in its batch. Each direct caller can choose its own algorithm.

Algorithm names must be lowercase. Unsupported names are rejected before a hash request, local fallback, or snapshot write: build helpers throw `InvalidArgumentException`, and promotion returns `false`. The public string parameters reject non-string arguments with `TypeError` when called from strict PHP code. Promotion still reloads the exact-version asset before retrieval and metadata generation.

## Published references and local baselines

Published requests use the selected algorithm for the installed asset version. Shield validates the complete returned path-to-digest map against that algorithm before persisting it. Missing, malformed, or mixed-algorithm responses are unavailable references.

The premium-support catalog is normalized when received. Malformed groups, rows, and identifier fields cannot establish support; valid slug, name, or file identifiers remain independent exact-match criteria. Only a valid catalog slug can be adopted for a plugin request.

For a missing snapshot, an unavailable published reference permits the existing local baseline fallback using the same selected algorithm. Local generation hashes raw file bytes. Snapshot metadata records the selected `algo` and `live_hashes=false`; a validated published reference records `live_hashes=true`.

A local baseline detects changes since creation. Matching it does not confer published-reference trust or authorize a known-valid malware-scan skip. Existing independent clean-malware cache rules still apply.

Promotion replaces an eligible local baseline only with validated exact-version published hashes while AFS is idle. Existing persisted verification, rollback, check timing, cache resets, and asset follow-up behavior remain in effect. Failed retrieval or replacement retains the original digests, algorithm, and provenance; a completed unsuccessful remote check may update its existing check timestamp.

## Existing snapshots and scan behavior

Selection affects new snapshots and eligible local-to-published promotions. Existing usable MD5/SHA1 snapshots, including legacy snapshots without an `algo` field, remain readable. Choosing SHA256 does not migrate them, invalidate them, or rebuild them from installed files. Existing published snapshots are not automatically refreshed to another algorithm.

AFS preflight makes at most one crowd-sourced lookup for each comparison-eligible plugin/theme at its installed version and normalizes it with `NormalizeHashMap`. A budget shared by the assets in that preflight stops further lookups after two consecutive request errors or 30 seconds of cumulative request time; remaining assets receive stored-snapshot copies. Successful empty responses reset the consecutive-error count. Each lookup retains the existing 10-second timeout, and an in-flight request may finish after the cumulative threshold. A later preflight starts with a fresh budget, including in the same WP-CLI process. It writes these references atomically to the flat `<cache dir>/scan-hashes/` directory, using `<type>-<first 16 hex characters of sha1(canonical asset key)>-<sanitized version>.json`. Metadata retains the exact type, canonical key and installed version, together with `source`, `trusted` and `created_at`. Full scans fill after snapshot eligibility is established; plugin/theme-scoped scans fill only their prepared asset, and core-scoped scans fill none. This applies to plans with `canScanPluginsThemesLocal()` and does not require the remote-scanning capability.

If the API fails or returns no usable hashes, preflight writes a copy of the usable stored snapshot into that directory with `source=snapshot`, retaining its own `live_hashes` trust. Otherwise it writes `source=crowd_sourced` and `trusted=true`. Successful empty responses followed by usable snapshot copies are quiet. Real request errors, write errors and missing usable snapshots are logged once per asset. This never changes the stored snapshot, its algorithms, or eligibility, and a fill failure never fails the scan.

Batches read each asset's cache file once per PHP request and require exact metadata and a non-empty map of valid lowercase MD5, SHA1 or SHA256 digest lists. A match against any cached reference passes; trusted matches use `published_reference`, and untrusted snapshot-copy matches use `local_baseline`. A missing or invalid cache, an absent path, or no matching cached hash falls back to the existing stored-snapshot verification unchanged. Full scans retain their eligibility, version and incomplete-comparison checks before cache lookup. Per-file code performs no remote lookup, cache write or snapshot mutation, so lifecycle rule 5.1.2 still holds. Cached-reference comparison hashes the file once per algorithm and builds supported LF/CRLF/lone-CR variants once per comparison, preserving raw-byte checks and the existing exact-path-then-lowercase lookup.

AFS initialization empties `scan-hashes/` unless another AFS record is queued, building, built or running. Queue completion deletes the directory when no scans are active; separate hourly maintenance deletes it when no AFS scan is active. Fill and cleanup run only on the main site of the main network; other sites retain stored-snapshot verification. The directory has no subdirectories or age-based expiry and is separate from malware-pattern and optimiser caches.

Successful preflight writes, initialization, cleanup and the existing resolver memo resets discard scan-cache memoization so later scans in the same WP-CLI process can see freshly filled references. If the hourly active-scan query fails, maintenance preserves the directory and returns without interrupting other hourly jobs.

CSHASHES remains separate: crowd generation still independently reads installed files and produces its existing normalized SHA1 map. Submission payloads, collection identities, endpoint contracts, and scheduling are unchanged. Result rechecks and repair helpers preserve CSHASHES-first retrieval and any-matching-reference semantics; selecting SHA256 for snapshots does not impose SHA256-only verification.

Core checksums, FileLocker SHA1 records, optimiser fingerprints, malware hashes, and telemetry/cache identifiers retain their own contracts. This feature adds no provider coverage, database migration, admin setting, or dependency change.
