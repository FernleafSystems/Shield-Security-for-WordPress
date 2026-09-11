# Shield Security

Shield Security detects and reports security-relevant changes in a WordPress installation.

## AFS scanning

**Stored snapshot**:
A persisted file-hash map for one exact asset identity and version, reusable by multiple AFS runs while valid. It is not owned by or copied into a scan.
_Avoid_: Per-run snapshot, scan snapshot

**Snapshot preparation**:
The coordinator action that ensures a usable shared stored snapshot exists and verifies against the current exact asset identity and version. Preparation does not by itself request a scan.
_Avoid_: Preparing a snapshot for a scan, scan-time snapshot build

**Scan-time snapshot read**:
File processing reads the usable shared stored snapshot for the exact asset identity and version. It does not request remote hashes, build a snapshot, or replace a snapshot.
_Avoid_: Live lookup during scanning, scan-time snapshot preparation

**Snapshot promotion**:
The coordinator replacement of an exact-version local-baseline snapshot with a validated published-reference snapshot. Promotion waits until no AFS scan is queued, building, built, or running.
_Avoid_: Scan-time replacement, snapshot generation

**AFS run**:
One execution of AFS presented and completed as one scan.
_Avoid_: Queue batch, scan item

**Ordinary full scan**:
An AFS run whose file population may cover WordPress core, installed plugins and themes, and other configured areas.
_Avoid_: Global scan

**Targeted asset scan**:
An AFS run whose file population is limited to one plugin or one theme and whose required snapshot is prepared before it starts.
_Avoid_: Partial scan, restricted scan

**Asset follow-up scan**:
A deduplicated targeted asset scan enqueued by the coordinator after an asset lifecycle event, a full-scan concurrency exception, or successful snapshot promotion. Routine reconciliation alone does not request one.
_Avoid_: Replacement scan, superseding scan

**Asset comparison coverage**:
Whether one AFS run completed file-change comparison for an exact plugin or theme identity and version. File-change finding effects are all-or-nothing when incompleteness is known before persistence. In the accepted rare mid-execution mismatch, earlier persisted observations are not rolled back; later unsafe comparison and stale resolution stop. Malware coverage is separate.
_Avoid_: Integrity status, scan health

**Malware scan eligibility**:
Whether the existing premium entitlement, feature enablement, scan configuration, and other malware-scan gates permit malware scanning. Snapshot availability or comparison coverage must not add another gate, but nothing in the snapshot lifecycle enables malware scanning or bypasses those existing gates.
_Avoid_: Malware always runs, malware fallback

**Comparison basis**:
The source of the snapshot actually used to produce a plugin/theme file-change finding. Persist it in existing result metadata as `published_reference` or `local_baseline`; do not infer it later from the asset's current snapshot.
_Avoid_: Integrity level, current snapshot source

**File population**:
The complete set of file paths frozen for one AFS run.
_Avoid_: Live filesystem, scan results

**Queue batch**:
A bounded subset of an AFS run's file population processed as one queue unit. It is not a separate scan.
_Avoid_: Asset scan, child scan, scan result

**Asset work group**:
A possible internal partition of one ordinary full scan's file population by exact asset ownership. It is not a separate AFS run and may contain multiple queue batches.
_Avoid_: Child scan, targeted asset scan, asset follow-up scan

**File-change observation**:
The outcome of comparing one file during the current AFS run. It normally affects findings only when comparison coverage completes for its exact asset identity and version. An observation persisted before a rare mid-execution mismatch may remain provisionally under specification A1/A10; it is not rolled back.
_Avoid_: Finding, coverage

**Finding**:
A persisted, currently relevant issue reported by scanning.
_Avoid_: Queue item, file population, observation

**Scan-result notification**:
An automated report or alert whose user-facing content is derived from persisted scan findings.
_Avoid_: Security alert

**Non-scan instant alert**:
An event-driven notification independent of scan-result records, such as an admin login, admin-account change, firewall block, Shield deactivation, FileLocker change, or cloaked-plugin detection.
_Avoid_: Security alert, scan-result notification

**Scan audit event**:
An activity-log record describing scan execution or observations; it is not an email notification.
_Avoid_: Scan-result notification, finding

**Notification-ready scan state**:
The state in which no scan is queued, building, built, or running and no retryable AFS asset-follow-up work remains pending.
_Avoid_: Queue completed, scan completed

**Accepted notification-readiness race**:
Readiness is a fresh point-in-time decision made immediately before an automatic scan-result notification path begins its protected side effects. Another request may very rarely create an active scan or retryable AFS asset-follow-up after that decision and before notification processing completes. This bounded race is accepted; do not add locks, transactions, leases, repeated per-side-effect checks, persisted readiness state, or broader coordination to eliminate it. This acceptance does not permit cached readiness or any side effect when the fresh check returns not ready or fails.
_Avoid_: Notification readiness lock, readiness lease
