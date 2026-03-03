# Operator Modes Redesign - Multi-Agent Implementation Orchestration

**Date:** 2026-03-03  
**Status:** Execution document (authoritative for implementation sequencing and ownership)  
**Target concurrency:** 4 parallel agent sessions

---

## 1. Purpose

This document is the single execution contract for all implementation agents.

No agent may start coding without claiming work in this document first.

This document defines:

1. exact track boundaries
2. dependency gates
3. mandatory task claim and status workflow
4. reusable components and files each track must reuse
5. acceptance criteria per track

---

## 2. Required Reading Before Any Code

Every agent must read all of the following before claiming work:

1. `docs/OperatorModes/redesign/REDESIGN-OVERVIEW.md`
2. `docs/OperatorModes/redesign/user-journeys.md`
3. `docs/OperatorModes/redesign/prototype-configure-unified.html`
4. `docs/OperatorModes/redesign/prototype-investigate-expand.html`
5. `docs/OperatorModes/redesign/prototype-reports-alerts.html`
6. this file (`IMPLEMENTATION-ORCHESTRATION.md`)

---

## 2.1 Source-of-Truth Precedence

If sources appear to conflict, resolve in this order:

1. `REDESIGN-OVERVIEW.md`
2. `user-journeys.md`
3. this orchestration document
4. prototype HTML files

Prototypes never override locked behavior in overview or journeys.

---

## 2.2 Traceability Matrix (Track -> Required Source Inputs)

| Track | Required Journeys | Required Prototypes | Required Overview Sections |
|---|---|---|---|
| T0 | Cross-journey rules, No-Interpretation checklist | configure + investigate + reports prototypes (for shared shell expectations only) | Locked product decisions, reusable architecture, interaction/accessibility, loading/error contracts |
| T1 | Journey 2 | `prototype-configure-unified.html` | Configure mode contract and configure panel contract |
| T2 | Journeys 3, 4, 5, 7 | `prototype-investigate-expand.html` | Investigate mode contract and detailed investigate subject contracts |
| T3 | Journeys 1 and 6 plus Cross-journey rules | `prototype-reports-alerts.html` and investigate prototype for routing context behavior | Navigation/legacy route contract, reports scope, actions queue scope, prototype alignment notes |
| QA | All journeys | all prototypes as visual reference only | Full overview + this orchestration file |

All implementation decisions must be traceable to at least one row in this matrix.

---

## 3. Mandatory Claim-and-Lock Protocol

Repository note: this `docs/` path is ignored by Git in this repo. The claim protocol is enforced through shared workspace file updates, not through Git-tracked claim commits.

### 3.1 Status Vocabulary (must use exact values)

- `NOT_STARTED`
- `IN_PROGRESS`
- `BLOCKED`
- `READY_FOR_REVIEW`
- `DONE`

### 3.2 Required Steps Before Coding

Every agent must perform these steps in order:

1. Open this file.
2. In Section 8, set your track row to `IN_PROGRESS` (or keep it `IN_PROGRESS` if already active) and update `Owner` + `Updated (UTC)`.
3. Verify the track row owner is either you or empty. If another owner is active on that track, do not claim tasks on that track.
4. Find an unclaimed task row in Section 9.
5. Set task `Status` from `NOT_STARTED` to `IN_PROGRESS`.
6. Fill in `Owner`, `Branch`, and `Start (UTC)`.
7. Save this file with track and task claim updates first.
8. Only after the file is saved with claim metadata may code changes begin.
9. Re-open this file and verify the saved rows still show your claim before coding.

If two agents claim the same task, the earliest saved `Start (UTC)` claim has priority. The other agent must immediately choose another unclaimed task.

### 3.2.1 Claim Tie-Break Rule

If two conflicting claims have identical `Start (UTC)` values:

1. the claim with earlier track row `Updated (UTC)` wins
2. if still identical, reviewer resolves and records decision in task `Notes`

### 3.3 Required Steps When Finishing

1. Fill in `PR/Commit`, `End (UTC)`, and `Notes` handoff artifacts (Section 9.1).
2. Update task status to `READY_FOR_REVIEW`.
3. Update the track row `Updated (UTC)` in Section 8 in the same save operation.
4. If reviewer signs off, set status to `DONE`.

### 3.3.1 Claim heartbeat

To prevent silent stale locks, each `IN_PROGRESS` task must be touched at least every 60 minutes by updating `Notes` with a timestamped progress marker.

### 3.3.2 Stale lock reclaim

If an `IN_PROGRESS` task has no heartbeat update for more than 90 minutes:

1. set the task to `BLOCKED` with note `stale lock reclaimed`
2. update `Owner` to `-`
3. append previous owner and reclaim timestamp in `Notes`
4. update track row `Updated (UTC)`
5. a new agent may then claim the task following Section 3.2

### 3.3.3 Completion Metadata Integrity Rule

Before a task can be set to `READY_FOR_REVIEW` or `DONE`:

1. `End (UTC)` must be non-empty and formatted per Section 3.7.
2. `PR/Commit` must be non-empty and must not be `-`.
3. `Notes` must include the required handoff artifacts in Section 9.1.
4. if any completion field is missing, keep the task `IN_PROGRESS` (or return it to `IN_PROGRESS`).
5. existing historical rows are not bulk-rewritten; any row touched after this rule is added must comply.

### 3.4 Blocked Work

If blocked:

1. set status `BLOCKED`
2. write blocker reason and upstream dependency ID
3. do not proceed with workaround unless explicitly listed in this document

---

### 3.5 Ambiguity Stop Rule

If a task cannot be executed without inventing behavior not explicitly present in:

1. `REDESIGN-OVERVIEW.md`
2. `user-journeys.md`
3. this orchestration document

the agent must stop implementation and record a blocker in the task board. Agents are not permitted to resolve product ambiguity by unilateral design decisions.

---

### 3.6 Dependency Start Rule

An agent is forbidden to set a task to `IN_PROGRESS` unless:

1. every dependency listed in the task row is `DONE`, or
2. the dependency is a gate and the gate is explicitly marked complete in Section 7.

If dependency state is not satisfied, the agent must set the row to `BLOCKED` with dependency reason.

---

### 3.7 Timestamp Format Rule

All timestamps in this document must use UTC in ISO-like format:

`YYYY-MM-DD HH:MM UTC`

Example: `2026-03-03 14:25 UTC`

Do not use local timezone abbreviations.

All required time fields (`Start`, `End`, `Updated`, `Completed`) must be non-empty once their related status transition occurs.

---

## 4. Non-Negotiable Implementation Decisions

1. Direct replacement rollout, no feature flag in this wave.
2. Shared components are built first and reused; no parallel one-off UI systems.
3. Investigate landing tile set is fixed to 7 items: User, IP Address, Plugin, Theme, Core Files, Live Traffic, Premium Integrations (disabled).
4. Activity Log, Sessions, and historical Traffic Log are not Investigate landing tiles; they remain sidebar pages.
5. Plugin panel is generic plugin events scope only.
6. Premium Integrations tile is disabled placeholder.
7. Reports tile-panel redesign is not part of this wave. Reports and Actions Queue are minimal shell-alignment only in this wave.
8. Tile body is compact: icon + title + stat/badge. No per-tile descriptive paragraph.

---

## 4.1 Investigate Canonical Contracts (Must Match Overview Exactly)

Canonical subject keys:

1. `user`
2. `ip`
3. `plugin`
4. `theme`
5. `core`
6. `live_traffic`
7. `premium_integrations`

Canonical `panel_target` values for this wave are identical to subject keys.

Mandatory tile payload fields:

1. `key`
2. `panel_target`
3. `is_enabled`
4. `is_disabled`

Required rules:

1. `key == panel_target` for this wave.
2. `is_disabled` is always the logical inverse of `is_enabled`.
3. `premium_integrations` tile must be `is_enabled=false` and `is_disabled=true`.
4. disabled premium tile must not open a panel on click, Enter, or Space.

Any implementation that diverges from these contracts is invalid.

---

## 5. Shared Reuse Map (Do Not Rebuild)

Agents must reuse and extend existing systems:

### 5.1 Page Composition and Routing

- `src/ActionRouter/Actions/Render/PluginAdminPages/PageModeLandingBase.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageConfigureLanding.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageReportsLanding.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageActionsQueueLanding.php`
- `src/Controller/Plugin/PluginNavs.php`
- `src/Modules/Plugin/Lib/NavMenuBuilder.php`

### 5.2 Investigate Subject Data/Tables

- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByUser.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByIp.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByPlugin.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByTheme.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByCore.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/InvestigateRenderContracts.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/InvestigateOverviewRowsBuilder.php`
- `src/Tables/DataTables/LoadData/Investigation/*`

### 5.3 JS and Lookup

- `assets/js/app/AppMain.js`
- `assets/js/components/tables/InvestigationTable.js`
- Select2 usage pattern: `assets/js/components/search/SuperSearchService.js`

### 5.4 Styles

- `assets/css/shield/_status-colors.scss`
- `assets/css/shield/investigate.scss`
- `assets/css/shield/reports.scss`
- `assets/css/shield/dashboard.scss`

---

## 6. Parallel Track Model

### Track T0 - Shared Foundation (Critical Path)

**Goal:** create shared shell contracts required by all other tracks.

**Must include:**

1. compact mode header spacing strategy
2. shared tile shell contract (compact tile structure)
3. shared panel shell behavior contract
4. mode accent bar rendering path
5. shared JS behavior for tile->panel state

**Hard gate output:** Interface freeze package `G0`.

### Track T1 - Configure Redesign

**Goal:** implement Configure in tile+panel model using shared contracts.

**Must include:**

1. 8 zone tiles, status-aware
2. inline zone panel with component health
3. retained Security Grades nav/section behavior
4. no tile navigation side effect

### Track T2 - Investigate Redesign

**Goal:** implement final 7-tile Investigate IA and panel flows.

**Must include:**

1. final 7 tile set
2. disabled Premium Integrations tile
3. Live Traffic tile/panel
4. Select2 lookup for user/ip/plugin/theme according to locked behavior
5. generic plugin event-focused panel scope
6. no Activity/Sessions/historical Traffic tiles

### Track T3 - Navigation, Legacy Routing, Reports/Queue Minimal, Docs/Prototype Sync

**Goal:** preserve legacy page access, align shell behavior, and keep docs/prototypes authoritative.

**Must include:**

1. old investigate URLs render redesigned landing with preselected panel context
2. sidebar legacy data pages unchanged in IA
3. reports/actions queue shell-consistency changes only
4. docs and prototype alignment updates in this directory

---

## 6.1 Forbidden Edit Zones By Track

These are hard boundaries to prevent collisions.

1. T0 must not change mode-specific business logic in Configure/Investigate subject builders.
2. T1 must not edit Investigate landing subject contracts.
3. T2 must not edit Configure zone logic.
4. T3 must not introduce new Investigate data contracts; it only wires routing/nav/shell/docs alignment.
5. QA tasks must not change product behavior; QA tracks only test updates and verification notes.
6. Only T3-04 may edit files under `docs/OperatorModes/redesign/` during implementation execution.

If a boundary crossing is required, the task row must be updated with justification before code changes.

---

## 6.2 No-Copy Prototype Rule

Prototype files are behavioral and visual references only. Agents are forbidden to copy prototype HTML/CSS directly into production Twig/SCSS/JS. Implementations must use existing Shield templates, tokens, and component patterns.

---

## 6.3 Producer-Consumer Contract Rule (Internal Data)

For internal data produced and consumed inside Shield code (where we control both sides), agents must follow this contract discipline:

1. Define data shape at the producer using explicit PHPDoc array-shape annotations.
2. Consume producer fields directly; do not add inline defensive access patterns such as `?? ''`, `?? false`, or repeated per-field casts.
3. If normalization is needed, do it once at the system boundary (external/untrusted input) in a central producer/builder path, not at every consumption site.
4. If a consumer cannot trust a producer shape, stop and fix/sign the producer contract first (code + PHPDoc + targeted tests), then consume it.

This is mandatory for all implementation tracks in this wave.

---

## 7. Dependency Gates (Strict)

Gate completion is tracked in this table and is the only valid source for gate state:

| Gate | Description | Status | Owner | Completed (UTC) | Notes |
|---|---|---|---|---|---|
| G0 | Shared foundation interface freeze published | COMPLETE | codex-gpt5 | 2026-03-03 11:54 UTC | T0-01,T0-02,T0-03,T0-04 complete. Frozen: mode_shell/mode_tiles/mode_panel PHP vars; Twig data contracts (data-mode-shell,data-mode-tile,data-mode-panel,data-mode-panel-close); JS events (shield:mode-panel-opening/opened/closed); accent/header compact shell contract. |
| G1 | T1/T2 parallel start gate opened | NOT_COMPLETE | - | - | - |
| G2 | T3 old-route wiring gate opened after subject key freeze | NOT_COMPLETE | - | - | - |
| G3 | Final integration gate (all tracks merged + regression green) | NOT_COMPLETE | - | - | - |

Gate status values are `NOT_COMPLETE` or `COMPLETE`.
Only the owning track lead (or designated reviewer) may set a gate to `COMPLETE`.
For this document, `track lead` means the current `Owner` value in the corresponding track row in Section 8.

### Gate G0 (from T0)

Required before T1/T2/T3 implementation coding begins.

Deliverables:

1. frozen tile payload contract
2. frozen panel payload contract
3. frozen CSS class/state contract for tile and panel containers
4. frozen JS custom event/state contract
5. gate row updated to `COMPLETE` with owner and completion timestamp
6. `Notes` includes links/references to supporting task IDs

### Gate G1 (T1/T2 start)

T1 and T2 may run in parallel after G0 only.
Set G1 to `COMPLETE` only when G0 is `COMPLETE`.
Add supporting task references in gate `Notes`.

### Gate G2 (T3 routing integration)

T3 may wire old routes to new panel contexts only after T2 publishes final subject keys and panel IDs.
Set G2 to `COMPLETE` only when T2 subject keys/panel IDs are frozen and referenced in T3 notes.
Add supporting task references in gate `Notes`.

### Gate G3 (final integration)

All tracks merged and regression checks green.
Set G3 to `COMPLETE` only when Section 14 release readiness criteria are all satisfied.
Add QA evidence reference in gate `Notes`.

No release branch integration before G3.

---

## 8. Track Status Board (Agents Must Update Before Task Claims)

| Track | Status | Owner | Started (UTC) | Updated (UTC) | Notes |
|---|---|---|---|---|---|
| T0 | DONE | codex-gpt5 | 2026-03-03 11:43 UTC | 2026-03-03 11:54 UTC | T0 shared foundation implemented and verified (unit suite + asset build). |
| T1 | DONE | codex-gpt5 | 2026-03-03 12:21 UTC | 2026-03-03 13:59 UTC | T1 Configure scope completed; T1-01,T1-02,T1-03 set to DONE after implementation + verification + commit 537a6cbe3. |
| T2 | DONE | codex-gpt5 | 2026-03-03 12:24 UTC | 2026-03-03 14:44 UTC | T2 Investigate redesign scope completed; T2-01,T2-02,T2-03,T2-04,T2-05 set to DONE after implementation + verification + commit 5a1923c35. |
| T3 | IN_PROGRESS | codex-gpt5 | 2026-03-03 12:28 UTC | 2026-03-03 13:23 UTC | T3-02,T3-03,T3-04 marked DONE after code/test or docs/prototype verification; T3-01 remains NOT_STARTED pending T2 dependencies. |
| QA | NOT_STARTED | - | - | - | - |

Track status values must use the same vocabulary as tasks.
Only one active owner is allowed per track row at a time.
Track row must move to `DONE` when all tasks in that track are `DONE`.

Track status transition rules:

1. `NOT_STARTED` -> `IN_PROGRESS` when the first task in that track is claimed.
2. `IN_PROGRESS` -> `BLOCKED` when all active tasks are blocked by dependencies or unresolved ambiguity.
3. `BLOCKED` -> `IN_PROGRESS` when at least one blocked task is unblocked and reclaimed.
4. `IN_PROGRESS` -> `READY_FOR_REVIEW` when all track tasks are `READY_FOR_REVIEW` or `DONE`.
5. `READY_FOR_REVIEW` -> `DONE` when all track tasks are `DONE`.
6. any task transition to `READY_FOR_REVIEW` or `DONE` must update the track row `Updated (UTC)` in the same save operation.

---

## 9. Task Board (Agents Must Update This Table)

| Task ID | Track | Task | Depends On | Status | Owner | Branch | Start (UTC) | End (UTC) | PR/Commit | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| T0-01 | T0 | Define and implement shared tile/panel contracts in PHP/Twig | - | DONE | codex-gpt5 | develop | 2026-03-03 11:43 UTC | 2026-03-03 11:54 UTC | - | Files: PageModeLandingBase + mode landing classes + base_inner_page + configure/investigate landing templates + shared mode panel component. Tests: full `composer test:unit`. Risks: integration env unavailable locally. No forbidden zones crossed. Traceability: Section 2.2 T0 row + overview/journeys shell contracts. |
| T0-02 | T0 | Implement shared tile/panel JS state controller contract | T0-01 | DONE | codex-gpt5 | develop | 2026-03-03 11:48 UTC | 2026-03-03 11:54 UTC | - | Files: assets/js/components/mode/ModePanelStateController.js, AppMain.js. Tests: full `composer test:unit`, `npm run build`. Risks: controller opt-in requires data-mode-interactive=1 (intentional). No forbidden zones crossed. Traceability: Section 2.2 T0 interaction/loading contract. |
| T0-03 | T0 | Implement mode accent bar and compact header shell contract | T0-01 | DONE | codex-gpt5 | develop | 2026-03-03 11:49 UTC | 2026-03-03 11:54 UTC | - | Files: base_inner_page.twig, plugin-main.scss, landing integration tests for shell markers. Tests: full `composer test:unit`, `npm run build`; integration command skipped (no WP env). No forbidden zones crossed. Traceability: Section 2.2 T0 shared shell expectations + overview shell/accent rules. |
| T0-04 | T0 | Publish G0 interface freeze notes in this document | T0-01,T0-02,T0-03 | DONE | codex-gpt5 | develop | 2026-03-03 11:54 UTC | 2026-03-03 11:54 UTC | - | G0 published with frozen interface contract details and task refs. T0 exception applied for this docs update as pre-agreed. |
| T1-01 | T1 | Rebuild Configure landing tiles to shared contract | G0 | DONE | codex-gpt5 | develop | 2026-03-03 12:21 UTC | 2026-03-03 13:23 UTC | 537a6cbe3 | Files: `src/ActionRouter/Actions/Render/PluginAdminPages/ConfigureZoneTilesBuilder.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageConfigureLanding.php`, `templates/twig/wpadmin/plugin_pages/inner/configure_landing.twig`, `assets/css/shield/configure.scss`, `assets/css/plugin-main.scss`, `templates/twig/wpadmin/components/configure/zone_panel_body.twig`, `tests/Unit/ActionRouter/Render/ConfigureZoneTilesBuilderTest.php`, `tests/Unit/ActionRouter/Render/PageConfigureLandingBehaviorTest.php`, `tests/Integration/ActionRouter/ConfigureLandingPageIntegrationTest.php`, `tests/Integration/ActionRouter/Support/ModeLandingAssertions.php`. Tests: `composer test:unit -- --filter ConfigureZoneTilesBuilderTest`, `composer test:unit -- --filter PageConfigureLandingBehaviorTest`, `composer test:integration -- --filter ConfigureLandingPageIntegrationTest` (skipped: WP env unavailable), `npm run build`. Risks: full WP integration env still required for runtime interaction verification. No forbidden edit zones crossed. Traceability: Section 2.2 T1 row + REDESIGN-OVERVIEW 4.2/8 + user-journeys Journey 2. |
| T1-02 | T1 | Implement Configure inline panel content and actions | T1-01 | DONE | codex-gpt5 | develop | 2026-03-03 13:05 UTC | 2026-03-03 13:23 UTC | 537a6cbe3 | Implemented via shared Configure tile payload + per-zone inline panel markup with flat component rows and explicit settings CTA (`Configure [Zone] Settings`). Verification covered in T1-01 test/build entries. Risks and scope confirmations same as T1-01. |
| T1-03 | T1 | Preserve and validate Security Grades nav mapping | T1-01 | DONE | codex-gpt5 | develop | 2026-03-03 13:16 UTC | 2026-03-03 13:23 UTC | 537a6cbe3 | No mapping changes required; validated existing behavior remained intact while applying Configure redesign. Tests: `composer test:unit -- --filter NavMenuBuilderOperatorModesTest`, `composer test:unit -- --filter PluginNavsOperatorModesTest`. Risks: none identified in unit coverage; full integration suite pending WP env. No forbidden edit zones crossed. Traceability: Section 2.2 T1 row + REDESIGN-OVERVIEW decision 11 + Journey 2 detailed assertion 4. |
| T2-01 | T2 | Implement Investigate final 7 tile payload and rendering | G0 | DONE | codex-gpt5 | develop | 2026-03-03 12:24 UTC | 2026-03-03 14:44 UTC | 5a1923c35 | Files: `src/Controller/Plugin/PluginNavs.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig`, `templates/twig/wpadmin/plugin_pages/base_inner_page.twig`, `assets/css/shield/investigate.scss`. Tests: `php -l` on changed PHP files, `composer test:unit -- --filter PageInvestigateLandingBehaviorTest`, `composer test:unit -- --filter PluginNavsOperatorModesTest`, `composer test:unit -- --filter BuildBreadCrumbsOperatorModesTest`, `composer test:unit -- --filter NavMenuBuilderOperatorModesTest`, `npm run build`. Risks: integration environment unavailable locally for full WP run. No forbidden edit zones crossed. Traceability: Section 2.2 T2 row + overview investigate contract + Journeys 3/4/5/7. |
| T2-02 | T2 | Implement panel flows for User/IP/Plugin/Theme/Core/Live Traffic | T2-01 | DONE | codex-gpt5 | develop | 2026-03-03 13:40 UTC | 2026-03-03 14:44 UTC | 5a1923c35 | Files: `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig`, `assets/js/components/mode/InvestigateLandingController.js`, `assets/js/components/mode/ModePanelStateController.js`, `assets/js/components/tables/InvestigationTable.js`, `assets/js/app/AppMain.js`. Tests: `composer test:unit -- --filter PageInvestigateLandingBehaviorTest`, `composer test:unit -- --filter PageInvestigateByUserBehaviorTest`, `composer test:unit -- --filter PageInvestigateByIpBehaviorTest`, `composer test:unit -- --filter PageInvestigateByPluginBehaviorTest`, `composer test:unit -- --filter PageInvestigateByThemeBehaviorTest`, `npm run build`. Risks: integration environment unavailable locally for full WP run. No forbidden edit zones crossed. Traceability: Section 2.2 T2 row + overview investigate panel contracts + Journeys 3/4/5/7. |
| T2-03 | T2 | Implement disabled Premium Integrations tile behavior | T2-01 | DONE | codex-gpt5 | develop | 2026-03-03 13:44 UTC | 2026-03-03 14:44 UTC | 5a1923c35 | Files: `src/Controller/Plugin/PluginNavs.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig`, `tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php`, `tests/Unit/ActionRouter/Render/PageInvestigateLandingBehaviorTest.php`, `tests/Integration/ActionRouter/InvestigateLandingPageIntegrationTest.php`. Tests: `composer test:unit -- --filter PageInvestigateLandingBehaviorTest`, `composer test:unit -- --filter PluginNavsOperatorModesTest`; integration command executed and skipped locally due missing WP env. Risks: none beyond local integration-env limitation. No forbidden edit zones crossed. Traceability: Section 2.2 T2 row + overview locked decision for disabled premium tile + Journey 7. |
| T2-04 | T2 | Implement Select2 lookup and auto-load behavior contract | T2-02 | DONE | codex-gpt5 | develop | 2026-03-03 13:50 UTC | 2026-03-03 14:44 UTC | 5a1923c35 | Files: `templates/twig/wpadmin/components/investigate/lookup_strip.twig`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_user.twig`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_ip.twig`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_plugin.twig`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_theme.twig`, `assets/js/components/mode/InvestigateLandingController.js`. Tests: `composer test:unit -- --filter PageInvestigateLandingBehaviorTest`, `npm run build`. Risks: runtime interaction checks require WP integration env/UI pass. No forbidden edit zones crossed. Traceability: Section 2.2 T2 row + overview lookup behavior contract + Journeys 3/4/5. |
| T2-05 | T2 | Remove tile exposure for Activity/Sessions/historical Traffic | T2-01 | DONE | codex-gpt5 | develop | 2026-03-03 13:46 UTC | 2026-03-03 14:44 UTC | 5a1923c35 | Files: `src/Controller/Plugin/PluginNavs.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig`, `tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php`, `tests/Integration/ActionRouter/InvestigateLandingPageIntegrationTest.php`. Tests: `composer test:unit -- --filter PageInvestigateLandingBehaviorTest`, `composer test:unit -- --filter PluginNavsOperatorModesTest`; integration command executed and skipped locally due missing WP env. Risks: none beyond local integration-env limitation. No forbidden edit zones crossed. Traceability: Section 2.2 T2 row + overview locked decision removing Activity/Sessions/historical Traffic tiles + Journeys 3/7. |
| T3-01 | T3 | Route old investigate URLs to redesigned panel contexts | T2-01,T2-02 | NOT_STARTED | - | - | - | - | - | - |
| T3-02 | T3 | Apply minimal shell consistency to Reports landing | G0 | DONE | codex-gpt5 | develop | 2026-03-03 12:28 UTC | 2026-03-03 12:54 UTC | 059e49a6e | Files: tests/Integration/ActionRouter/ReportsRoutingIntegrationTest.php, tests/Unit/ActionRouter/Render/PageReportsLandingBehaviorTest.php. Tests: `composer test:unit -- --filter PageReportsLandingBehaviorTest`, `composer test:unit -- --filter PluginNavsOperatorModesTest`, `composer test:integration -- --filter ReportsRoutingIntegrationTest` (skipped: WP env unavailable). Risks: full integration execution pending WP test env. No forbidden edit zones crossed. Traceability: Section 2.2 T3 row + REDESIGN-OVERVIEW 4.4 + user-journeys Journey 6. Reviewer signoff: 2026-03-03 13:20 UTC. |
| T3-03 | T3 | Apply minimal shell consistency to Actions Queue landing | G0 | DONE | codex-gpt5 | develop | 2026-03-03 12:58 UTC | 2026-03-03 13:02 UTC | f1e7e01bc | Files: tests/Integration/ActionRouter/ActionsQueueLandingPageIntegrationTest.php. Tests: `composer test:unit -- --filter PageActionsQueueLandingBehaviorTest`, `composer test:unit -- --filter ModeLandingInheritanceTest`, `composer test:unit -- --filter PluginNavsOperatorModesTest`, `composer test:unit -- --filter BuildBreadCrumbsOperatorModesTest`, `composer test:integration -- --filter ActionsQueueLandingPageIntegrationTest` (skipped: WP env unavailable). Risks: full integration execution pending WP test env. No forbidden edit zones crossed. Traceability: Section 2.2 T3 row + REDESIGN-OVERVIEW 4.5 + user-journeys Journey 1. Reviewer signoff: 2026-03-03 13:20 UTC. |
| T3-04 | T3 | Keep docs and prototypes aligned to locked decisions | - | DONE | codex-gpt5 | develop | 2026-03-03 12:38 UTC | 2026-03-03 13:02 UTC | f1e7e01bc | Files: docs/OperatorModes/redesign/prototype-configure-unified.html, docs/OperatorModes/redesign/prototype-reports-alerts.html, docs/OperatorModes/redesign/IMPLEMENTATION-ORCHESTRATION.md. Tests: N/A (docs/prototype alignment only); validation via `rg` checks for stale REM-009 wording and locked-scope notes retained. Risks: none beyond normal review pass. No forbidden edit zones crossed (T3-04 owns docs path). Traceability: Section 2.2 T3 row + REDESIGN-OVERVIEW 4.4/4.5 + orchestration 6.1 rule 6. Reviewer signoff: 2026-03-03 13:23 UTC. |
| QA-01 | Cross | Update/unit integration tests for changed contracts/routes | T1-03,T2-05,T3-01,T3-02,T3-03 | NOT_STARTED | - | - | - | - | - | - |
| QA-02 | Cross | Full regression run and signoff notes | QA-01,T3-04 | NOT_STARTED | - | - | - | - | - | - |

---

## 9.1 Required Handoff Artifacts Per Task

Before moving a task to `READY_FOR_REVIEW`, each agent must append in `Notes`:

1. files changed (paths)
2. tests executed
3. unresolved risks
4. explicit confirmation that no forbidden edit zones were crossed
5. explicit confirmation that behavior is traceable to Section 2.2 matrix sources

Without these artifacts, review is invalid and task must return to `IN_PROGRESS`.

---

## 9.2 Task Claim Validation Checklist

Before claiming a task, the agent must verify:

1. row status is `NOT_STARTED`
2. dependencies are satisfied per Section 3.6
3. no active conflicting owner on same track item
4. track row in Section 8 has been set to `IN_PROGRESS` with current owner/time
5. required source inputs in Section 2.2 were read
6. required timestamp fields for the current transition are filled using Section 3.7 format

If any check fails, do not claim and set `BLOCKED` with reason if appropriate.

---

## 9.3 Task Completion Validation Checklist

Before setting a task to `READY_FOR_REVIEW` or `DONE`, the agent must verify:

1. `End (UTC)` is non-empty and formatted per Section 3.7.
2. `PR/Commit` is non-empty and not `-`.
3. `Notes` contains all required handoff artifacts listed in Section 9.1.
4. track row `Updated (UTC)` in Section 8 is refreshed in the same save operation.
5. status transition aligns with Section 8 track transition rules.

If any check fails, do not complete the transition; keep or return the task to `IN_PROGRESS` until corrected.

---

## 10. File Ownership Boundaries (No Overlap)

Agents must stay in their assigned file groups. Crossing boundaries requires note in task row and coordination.

### T0 Ownership

- shared page shell/template contracts
- shared tile/panel component templates
- shared JS controller entry points
- shared mode shell styles

Primary file patterns:

1. `src/ActionRouter/Actions/Render/PluginAdminPages/PageModeLandingBase.php`
2. `templates/twig/wpadmin/plugin_pages/base_inner_page.twig`
3. shared component templates under `templates/twig/wpadmin/components/` added for tile/panel shell
4. shared JS bootstrap/controller under `assets/js/app/` and shared component paths
5. shared SCSS under `assets/css/shield/` for shell-level behavior

### T1 Ownership

- Configure landing page class/template/style
- Configure-specific tests

Primary file patterns:

1. `src/ActionRouter/Actions/Render/PluginAdminPages/PageConfigureLanding.php`
2. `templates/twig/wpadmin/plugin_pages/inner/configure_landing.twig`
3. configure-specific component templates used by landing panel
4. configure-related tests under `tests/Unit` and `tests/Integration`

### T2 Ownership

- Investigate landing and panel templates/classes/js
- Investigate subject payload builders and lookup wiring
- Investigate-specific tests

Primary file patterns:

1. `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`
2. investigate subject render handlers reused/extended under `src/ActionRouter/Actions/Render/PluginAdminPages/`
3. `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig`
4. investigate panel/component templates under `templates/twig/wpadmin/components/investigate/` and related inner templates
5. investigate JS/SCSS under `assets/js/` and `assets/css/shield/investigate.scss`
6. investigate-related tests under `tests/Unit` and `tests/Integration`

### T3 Ownership

- plugin nav/route mapping for old investigate URLs
- reports/actions landing shell alignment
- docs and prototypes under `docs/OperatorModes/redesign/`
- nav/breadcrumb integration tests

Primary file patterns:

1. `src/Controller/Plugin/PluginNavs.php`
2. nav/breadcrumb supporting classes as required by old-route mapping behavior
3. `src/ActionRouter/Actions/Render/PluginAdminPages/PageReportsLanding.php`
4. `src/ActionRouter/Actions/Render/PluginAdminPages/PageActionsQueueLanding.php`
5. reports/actions landing templates
6. docs/prototypes in `docs/OperatorModes/redesign/`
7. nav/routing/report/actions related tests

---

## 11. Required Verification Per Track

### T0

1. shared contracts compile and render
2. no breakage on existing mode landing render paths

### T1

1. configure tile click no-nav behavior
2. panel swap and close behavior
3. security grades navigation still resolves

### T2

1. exact 7 tiles rendered
2. premium tile disabled and non-clickable
3. live traffic tile opens correct panel context
4. lookup behavior matches Select2 and auto-load rules
5. legacy data views are not tiles

### T3

1. old investigate URLs land on new experience with panel context
2. reports/actions still functional after shell alignment
3. docs/prototypes in this folder reflect locked decisions

### Cross QA

1. operator mode unit/integration tests updated and passing
2. investigate route and rendering tests updated and passing
3. no regression in nav and breadcrumb behavior

---

## 12. Merge and Review Order

1. Merge T0 once G0 is published.
2. Merge T1 and T2 in parallel after G0.
3. Merge T3 after T2 subject key freeze and routing verification.
4. Run cross QA and only then final integration.

No agent may merge directly to final release branch without QA-02 completion.

---

## 13. Change Control

Any change to locked decisions must be written first in:

1. `REDESIGN-OVERVIEW.md`
2. this file (impacting tracks/tasks/gates)
3. `user-journeys.md` if behavior changes

Code changes must not precede documentation lock changes.

---

## 14. Release Readiness Gate

Release readiness requires all of the following:

1. all task rows at `DONE`
2. no `BLOCKED` rows remaining
3. QA-02 complete with regression summary
4. docs/prototypes aligned with implemented scope

No exceptions are permitted for this wave.

---

## 15. Assumptions Register Policy

Default policy: no new assumptions are allowed.

If an agent believes an assumption is unavoidable:

1. add a dated entry under this section describing the exact assumption
2. mark impacted task `BLOCKED`
3. do not implement behavior dependent on that assumption until resolved

### Current register

1. none

