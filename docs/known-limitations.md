# Known Limitations and Deferred Development

This register records understood limitations that have been deliberately left outside the scope of a completed change. An entry is not a release blocker while its stated assumptions hold, but it is not a claim that the limitation is harmless.

Reviewers should not report a registered limitation as a new finding without new evidence, changed preconditions, or a material worsening in the reviewed change. New evidence and regressions should still be reported.

## Status and review rules

- **Accepted**: current behaviour is intentionally retained.
- **Deferred**: remediation is understood but postponed because its present cost or complexity is disproportionate to the demonstrated risk.
- Re-open an entry when one of its revisit triggers occurs.
- Remove an entry only when the limitation is fixed and verified; preserve its history in the resolving change or tracking issue.

## Active limitations

| ID | Area | Status | Summary | Tracking |
| --- | --- | --- | --- | --- |
| KL-MALAI-001 | MALai reconciliation | Deferred | Run-level hash memoization grows with the number of unique hashes reconciled. | [SHI-2328](https://linear.app/fernleafsystems/issue/SHI-2328/harden-malai-reconciliation-and-alert-lifecycle) |
| KL-MALAI-002 | MALai reconciliation | Deferred | A missing API response for a hash can leave duplicate records on different result pages temporarily inconsistent. | [SHI-2328](https://linear.app/fernleafsystems/issue/SHI-2328/harden-malai-reconciliation-and-alert-lifecycle) |

## KL-MALAI-001 - Run-level hash memoization

**Risk:** Low likelihood, potentially material operational impact on an unusually high-cardinality incident.

**Preconditions:** One reconciliation run processes many pages containing a very large number of unique hashes that require a MALai lookup.

**Current behaviour:** `RetrieveMalwareMalaiStatus` retains attempted hashes and returned statuses for the duration of the run. This guarantees that a hash repeated on a later page is requested only once and receives the same verdict. Memory therefore grows as O(U), where U is the number of unique hashes requested during that run, even though result retrieval and database writes are bounded to pages of 200.

**Potential impact:** At an extreme unique-hash count, the retained PHP arrays could contribute to memory exhaustion and prevent that reconciliation run from completing.

**Existing containment:** Ordinary sites and normal malware incidents are expected to have far fewer unique active hashes than would be required to approach the PHP memory limit. An incomplete run leaves findings available for later post-scan or hourly reconciliation and alert processing.

**Reason deferred:** No production evidence or representative measurement currently demonstrates a practical failure. Bounding this state while preserving exact cross-page behaviour would require a run cap, deferred processing, or persistent state, each of which changes the reconciliation contract and adds complexity beyond the current remediation.

**Revisit triggers:**

- a support case, reproducible test, or telemetry shows reconciliation memory exhaustion;
- a representative benchmark approaches the minimum supported PHP memory limit;
- reconciliation gains an existing bounded-run or persistent-work mechanism that can be reused without adding a parallel state model.

**Relevant code:** `src/Scans/Afs/Processing/RetrieveMalwareMalaiStatus.php`

## KL-MALAI-002 - Missing response across result pages

**Risk:** Rare compound edge case with local correctness impact.

**Preconditions:** More than one result page contains records with the same file hash, those records have conflicting durable MALai states, and the MALai response omits that requested hash.

**Current behaviour:** The hash is marked attempted for the run, but no verdict is cached. A matching record on a later page is not queried again during that run. Its existing durable state may therefore differ from a matching record processed on an earlier page.

**Potential impact:** A later-page record with an older clean status could be reconciled independently while another matching record remains pending. The inconsistency lasts until the file is detected again or a later eligible reconciliation receives a usable verdict.

**Existing containment:** The one-hour alert-deferral ceiling prevents pending findings from being hidden indefinitely. The pending record remains eligible for later reconciliation, and subsequent scans can rediscover the file state.

**Reason deferred:** The failure requires pagination, a duplicate hash, conflicting stored states, and an omitted API result together. Coordinating absent responses across all pages would expand run-level state or introduce ordering and persistence complexity for an unobserved edge case.

**Revisit triggers:**

- MALai is observed omitting requested hashes with meaningful frequency;
- a reproducible case causes an incorrect cleanup or missed alert;
- the cross-page reconciliation model changes for another required reason.

**Relevant code:** `src/Scans/Afs/Processing/RetrieveMalwareMalaiStatus.php`
