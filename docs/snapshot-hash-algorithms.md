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

AFS plugin/theme comparison reads the stored exact-version snapshot and infers MD5, SHA1, or SHA256 from each reference digest. Full scans retain their snapshot eligibility and incomplete-comparison rules; scoped scans also use stored snapshots. Comparison preserves raw-byte checks and supported LF/CRLF/lone-CR equivalence. That equivalence and existing lowercased paths do not establish byte-identical archives.

CSHASHES remains separate: crowd generation still independently reads installed files and produces its existing normalized SHA1 map. Submission payloads, collection identities, endpoint contracts, and scheduling are unchanged. Result rechecks and repair helpers preserve CSHASHES-first retrieval and any-matching-reference semantics; selecting SHA256 for snapshots does not impose SHA256-only verification.

Core checksums, FileLocker SHA1 records, optimiser fingerprints, malware hashes, and telemetry/cache identifiers retain their own contracts. This feature adds no provider coverage, database migration, admin setting, or dependency change.
