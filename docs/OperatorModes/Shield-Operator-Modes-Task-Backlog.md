# Shield Operator Modes - Delivery Task Backlog (Validated)

Date: 2026-02-21  
Validated against source: `src/`, `templates/twig/`, `assets/js/`, `assets/css/`, `tests/`  
Source plan: `docs/OperatorModes/Shield-Operator-Modes-Plan.md`  
Prototype reference: `docs/OperatorModes/prototype-b-hero-strip.html`

## 1) Objective And Scope Controls

Primary objective: reorganize Shield admin UX around 4 operator intents (`actions`, `investigate`, `configure`, `reports`) with separated channels for:
1. Configuration posture score (settings state only)
2. Action queue status (event-driven/security findings)

Scope controls (hard rules):
1. Prefer additive, low-blast-radius changes first.
2. Reuse existing data providers, render paths, and status color logic.
3. Do not add new fallback systems unless a concrete break path is proven.
4. Do not remove legacy Simple/Advanced flow in the first implementation slice.
5. For Phase A execution: no PHPCS run required, no integration test run required.

## 2) Validated Findings That Change The Plan

### VF-1: Action-channel split must include scan-result meter components

If only maintenance components are moved to `action`, the score remains event-driven because scan-result components still affect `MeterOverallConfig`.

Evidence:
1. `src/Modules/Plugin/Lib/MeterAnalysis/Meter/MeterOverallConfig.php` builds from all components except `AllComponents`.
2. `src/Modules/Plugin/Lib/MeterAnalysis/Components.php` includes:
   `ScanResultsWcf`, `ScanResultsWpv`, `ScanResultsMal`, `ScanResultsPtg`, `ScanResultsApc`.
3. `src/Modules/Plugin/Lib/MeterAnalysis/Component/ScanResultsBase.php` is result-count driven (`countResults()`, dynamic `weight()`).

Design correction:
Mark all `ScanResults*` components as `action` channel alongside maintenance components.

### VF-2: Channel filtering introduces divide-by-zero risk

`BuildMeter` computes percentages using `$totalWeight` without zero guard.

Evidence:
1. `src/Modules/Plugin/Lib/MeterAnalysis/BuildMeter.php` computes `score_as_percent`, `weight_as_percent`, and totals percentage with division by `$totalWeight`.

Design correction:
Add hard guard when `totalWeight <= 0` and return stable zeroed totals/status.

### VF-3: Static meter cache must be channel-aware

`Handler` caches by slug only; channel-specific retrieval would collide.

Evidence:
1. `src/Modules/Plugin/Lib/MeterAnalysis/Handler.php` uses static `BuiltMeters` keyed by meter slug.

Design correction:
Cache key must include channel dimension (e.g. `summary|config`, `summary|action`, `summary|combined`).

## 3) Phase Map (Revised For Low-Risk Start)

| Phase | Goal | Exit Criteria |
|---|---|---|
| P0 | Decision lock + risk controls | Validated findings and scope controls agreed |
| P1 (Phase A) | Meter channel foundation only | Config-only score available; action-channel components correctly classified; no consumer regressions |
| P2 (Starter UI) | Minimal UI adaptation using existing dashboard plumbing | Existing dashboard can render channel-specific metrics without nav/routing rewrite |
| P3 | Mode model + operator preference + selector landing | Operator constants/preference/landing introduced additively |
| P4 | Mode-aware nav and WP submenu | Sidebar and WP submenu switch to mode model |
| P5 | Mode cleanup + legacy removal | Simple/Advanced toggle and deprecated artifacts removed safely |
| P6+ | Investigate/Configure/Reports deepening + widget alignment | Remaining feature slices delivered incrementally |

## 4) Task Backlog

### P0 - Decision Lock

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-001 | Lock cross-cutting placement strategy (`Docs`, `Debug`, `Wizard`, `WhiteLabel`, `LoginHide`, `Integrations`) | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | None | Placement decision recorded before nav rewrite |
| OM-002 | Lock migration copy for score separation | `templates/twig/wpadmin/plugin_pages/inner/*` | None | Copy approved for user-facing score change messaging |
| OM-003 | Lock Investigate MVP (`By IP`, `By User`) and defer `By Plugin` | `src/ActionRouter/Actions/Render/PluginAdminPages/*` | None | Deferred scope recorded explicitly |

### P1 (Phase A) - Meter Channel Separation (First Coding Slice)

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-101 | Add channel contract to meter component base | `src/Modules/Plugin/Lib/MeterAnalysis/Component/Base.php` | OM-002 | Built component payload includes `channel` with default `config` |
| OM-102 | Classify action-channel components (maintenance + scan results) | `src/Modules/Plugin/Lib/MeterAnalysis/Component/WpUpdates.php`, `src/Modules/Plugin/Lib/MeterAnalysis/Component/WpPluginsUpdates.php`, `src/Modules/Plugin/Lib/MeterAnalysis/Component/WpThemesUpdates.php`, `src/Modules/Plugin/Lib/MeterAnalysis/Component/WpPluginsInactive.php`, `src/Modules/Plugin/Lib/MeterAnalysis/Component/WpThemesInactive.php`, `src/Modules/Plugin/Lib/MeterAnalysis/Component/ScanResultsWcf.php`, `src/Modules/Plugin/Lib/MeterAnalysis/Component/ScanResultsWpv.php`, `src/Modules/Plugin/Lib/MeterAnalysis/Component/ScanResultsMal.php`, `src/Modules/Plugin/Lib/MeterAnalysis/Component/ScanResultsPtg.php`, `src/Modules/Plugin/Lib/MeterAnalysis/Component/ScanResultsApc.php` | OM-101 | All event/maintenance components return `action` channel |
| OM-103 | Thread optional channel filter through meter build/retrieval | `src/Modules/Plugin/Lib/MeterAnalysis/Meter/MeterBase.php`, `src/Modules/Plugin/Lib/MeterAnalysis/BuildMeter.php`, `src/Modules/Plugin/Lib/MeterAnalysis/Handler.php` | OM-101, OM-102 | Can retrieve combined/default, config-only, and action-only views |
| OM-104 | Make `Handler` cache channel-aware and backward-compatible | `src/Modules/Plugin/Lib/MeterAnalysis/Handler.php` | OM-103 | Cache keys include channel, old callers unchanged |
| OM-105 | Add zero-weight safety in score math | `src/Modules/Plugin/Lib/MeterAnalysis/BuildMeter.php` | OM-103 | No division by zero when filtered component set is empty |
| OM-106 | Add focused unit coverage for channel split behavior | `tests/Unit/**` | OM-103, OM-104, OM-105 | Unit tests cover classification, filtering, and zero-weight guard |
| OM-107 | Preserve current consumers in Phase A (no nav/routing/legacy removal) | `src/Modules/Integrations/Lib/MainWP/Client/Actions/Sync.php`, `src/ActionRouter/Actions/Render/Components/Widgets/WpDashboardSummary.php`, `src/ActionRouter/Actions/Render/Components/SiteHealth/Analysis.php` | OM-103 | Existing callers keep combined behavior unless explicitly migrated |

### P2 - Starter UI (Minimal Change Path)

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-201 | Reuse existing dashboard simple surface for first channel-aware UI pass | `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverview.php`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple_body.twig` | P1 complete | UI displays channel-correct metrics with current render architecture |
| OM-202 | Reuse existing status/traffic mapping for red/orange/green | `src/Modules/Plugin/Lib/MeterAnalysis/BuildMeter.php`, existing Twig status classes | OM-201 | No duplicate color/status logic introduced |
| OM-203 | Keep existing JS/render pipeline unchanged in starter pass | `assets/js/components/meters/ProgressMeters.js` (no rewrite), existing placeholders/templates | OM-201 | No new JS data-pipeline added for starter pass |

### P3 - Mode Model + Preference + Landing

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-301 | Add operator mode constants/helpers | `src/Controller/Plugin/PluginNavs.php` | P1 complete | Canonical mode IDs/labels available |
| OM-302 | Add operator mode user preference service | `src/Modules/Plugin/Lib/OperatorModePreference.php` | OM-301 | `shield_default_operator_mode` read/write/sanitize implemented |
| OM-303 | Add operator mode landing page handler + template | `src/ActionRouter/Actions/Render/PluginAdminPages/PageOperatorModeLanding.php`, `templates/twig/wpadmin/plugin_pages/inner/operator_mode_landing.twig`, `src/ActionRouter/Constants.php` | OM-302 | Landing renders with channel-separated metrics using existing services |

### P4 - Mode-Aware Navigation

**Implementation status (2026-02-20):** OM-401, OM-401a, OM-401b, OM-401c, OM-401d, OM-402, and OM-403 are complete.

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-401 | ~~Make sidebar nav mode-aware~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-301 | **Done** - two-state sidebar implemented in `build()` using `buildModeSelector()` and `buildModeNav()`, with shared normalization extracted to `normalizeMenu()`. |
| OM-401a | ~~Fix `resolveCurrentMode()` to return empty string for dashboard nav~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401 | **Done** - dashboard/empty nav now resolves to `''` mode and no longer falls back to `MODE_ACTIONS`. |
| OM-401b | ~~Add `buildModeSelector()` method for State 1 sidebar~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401a | **Done** - mode selector state returns 4 flat mode entries plus Go PRO/License using existing `PluginNavs` entries/labels. |
| OM-401c | ~~Add `buildModeNav()` method for State 2 sidebar (back link + mode items)~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401a | **Done** - mode state prepends `mode-selector-back` item (`mode-back-link`) and reuses mode-filtered nav items plus Go PRO/License. |
| OM-401d | ~~Remove `NAV_DASHBOARD` from `allowedNavsForMode()`~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401c | **Done** - dashboard removed from mode allowlists; back-link now owns selector return path. |
| OM-402 | ~~Replace WP submenu with mode entries~~ | `src/ActionRouter/Actions/PluginAdmin/PluginAdminPageHandler.php` | OM-301 | **Done** - `addSubMenuItems()` registers Dashboard, Actions Queue, Investigate, Configure, Reports, Go PRO. |
| OM-403 | ~~Update breadcrumbs for mode pathing~~ | `src/Utilities/Navigation/BuildBreadCrumbs.php` | OM-401 | **Done** - `parse()` builds mode-aware breadcrumbs: Shield Security -> Mode Label -> Current Page. |

#### Implementation notes for OM-401a-d (completed)

**File:** `src/Modules/Plugin/Lib/NavMenuBuilder.php`

Implemented structure:
1. `build()` now resolves mode once and branches to `buildModeSelector()` or `buildModeNav()`.
2. Existing normalization logic was extracted to `normalizeMenu(array $menu): array` and reused for both paths.
3. Mode selector state returns exactly 4 mode entries plus Go PRO/License.
4. In-mode state prepends a `mode-selector-back` item and then reuses existing mode filtering + Go PRO/License.
5. `allowedNavsForMode()` no longer includes `NAV_DASHBOARD`.

Validation completed for this slice:
1. `php -l src/Modules/Plugin/Lib/NavMenuBuilder.php`
2. `composer test:unit -- tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php`
3. `composer test:unit -- tests/Unit/Utilities/Navigation/BuildBreadCrumbsOperatorModesTest.php`
4. `composer test:unit -- tests/Unit/Modules/Plugin/Lib/OperatorModePreferenceTest.php`

### P4.5 - Known Sidebar Gaps (After OM-401a-d, Before P5)

**Implementation status (2026-02-21):** OM-410 and OM-411 are complete.

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-410 | Add Security Grades as a direct link in Configure mode sidebar | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401c | When in Configure mode, sidebar includes a "Security Grades" link pointing to `NAV_DASHBOARD / SUBNAV_DASHBOARD_GRADES`. Currently this page is a sub-nav of Dashboard and does not appear in Configure mode's `allowedNavsForMode()`. Simplest approach: add it as a hardcoded item in `buildModeNav()` when mode is `MODE_CONFIGURE`. |
| OM-411 | Optional: Style back link distinctly | `assets/css/components/nav_sidebar_menu.scss` | OM-401c | The "<- Shield Security" back link has a `.mode-back-link` class and renders with a smaller font/muted style to visually separate it from mode nav items. This is a polish task, not a functional requirement. |

Validation completed for this slice:
1. `php -l src/Modules/Plugin/Lib/NavMenuBuilder.php`
2. `php -l tests/Unit/Modules/Plugin/Lib/NavMenuBuilderOperatorModesTest.php`
3. `php -l tests/Unit/NavSidebarModeBackLinkStyleTest.php`
4. `php vendor/phpunit/phpunit/phpunit -c phpunit-unit.xml tests/Unit/Modules/Plugin/Lib/NavMenuBuilderOperatorModesTest.php`
5. `php vendor/phpunit/phpunit/phpunit -c phpunit-unit.xml tests/Unit/NavSidebarModeBackLinkStyleTest.php`
6. `php vendor/phpunit/phpunit/phpunit -c phpunit-unit.xml tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php`
7. `php vendor/phpunit/phpunit/phpunit -c phpunit-unit.xml tests/Unit/Utilities/Navigation/BuildBreadCrumbsOperatorModesTest.php`
8. `php vendor/phpunit/phpunit/phpunit -c phpunit-unit.xml tests/Unit/NavSidebarTemplateTest.php`
9. `php vendor/phpunit/phpunit/phpunit -c phpunit-unit.xml tests/Unit/Modules/Plugin/Lib/OperatorModePreferenceTest.php`

Implementation notes:
1. `buildModeNav()` now prepends a Configure-only synthetic `mode-configure-grades` item after `mode-selector-back` and before filtered Configure nav items.
2. The synthetic grades item reuses dashboard `img`/`subtitle` metadata when available from base menu data.
3. `.mode-back-link` styling is scoped in `assets/css/components/nav_sidebar_menu.scss` and does not alter Twig markup.
4. `allowedNavsForMode()`, `resolveCurrentMode()`, `PluginNavs::modeForNav()`, and meter severity/status mapping were unchanged.

### P5 - Cleanup And Legacy Removal (Trigger-Based)

**Implementation status (2026-02-21):** OM-501 through OM-508 are complete.

| ID | Task | Trigger | Files | Done When |
|---|---|---|---|---|
| OM-501 | ~~Unwire legacy dashboard toggle runtime (no deletions)~~ | After P4 ships | `templates/twig/wpadmin/plugin_pages/base_inner_page.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview.twig`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverview.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/BasePluginAdminPage.php`, `src/Modules/Plugin/Lib/AssetsCustomizer.php` | **Done** - dashboard overview now renders a single operator-mode landing flow and no active UI/runtime path depends on `dashboard_view_toggle`. |
| OM-502 | ~~Remove deprecated Simple/Advanced classes/templates (hard removal phase)~~ | After OM-501 stabilization | `src/Modules/Plugin/Lib/Dashboard/DashboardViewPreference.php`, `src/ActionRouter/Actions/DashboardViewToggle.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverviewSimple.php`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple_body.twig`, `src/ActionRouter/Constants.php` | **Done** - deprecated runtime/template artifacts were deleted and registration references cleaned. |
| OM-503 | ~~Replace/retire tests tied to old toggle behavior~~ | After OM-502 | `tests/Integration/ActionRouter/DashboardViewToggleIntegrationTest.php`, `tests/Unit/Modules/Plugin/Lib/Dashboard/DashboardViewPreferenceTest.php`, affected assertions in `tests/Integration/ActionRouter/DashboardOverviewRoutingIntegrationTest.php` | **Done** - legacy toggle tests were removed and overview integration assertions were aligned to single-flow behavior. |

Deferred cleanup note (P5 scope lock):
1. `PageDashboardOverview.php` intentionally retains currently-unused payload elements in this pass.
2. Payload pruning is deferred to a future cleanup slice.
3. Any future payload removal must be confirmed with operator before implementation because some fields may be reused by other components.

### P6-A - Mode Landing Pack (Low Complexity, Shared Surface Area)

Purpose:
1. Finish dedicated entry landings for mode-first navigation without adding new data services.
2. Keep this pack additive by reusing current queue/meter/report providers.

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-601 | Add dedicated Actions/Configure/Reports landing page handlers and templates | `src/ActionRouter/Actions/Render/PluginAdminPages/PageActionsQueueLanding.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageConfigureLanding.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageReportsLanding.php`, `templates/twig/wpadmin/plugin_pages/inner/actions_queue_landing.twig`, `templates/twig/wpadmin/plugin_pages/inner/configure_landing.twig`, `templates/twig/wpadmin/plugin_pages/inner/reports_landing.twig`, `src/ActionRouter/Constants.php` | P5 complete | Each mode has a first-class landing page that renders via existing services/components only. |
| OM-602 | Wire mode default entries to new landing routes (not directly to tool pages) | `src/Controller/Plugin/PluginNavs.php`, `src/ActionRouter/Actions/PluginAdmin/PluginAdminPageHandler.php` | OM-601 | Entering Actions/Configure/Reports from selector/sidebar/WP submenu lands on the corresponding landing page. |
| OM-603 | Keep sidebar and breadcrumbs coherent for new landing routes | `src/Modules/Plugin/Lib/NavMenuBuilder.php`, `src/Utilities/Navigation/BuildBreadCrumbs.php` | OM-602 | Mode sidebar highlights and breadcrumb path remain consistent for landing pages and child pages. |
| OM-604 | Add focused tests for landing routing and mode-entry contracts | `tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php`, `tests/Unit/Utilities/Navigation/BuildBreadCrumbsOperatorModesTest.php`, `tests/Unit/Modules/Plugin/Lib/NavMenuBuilderOperatorModesTest.php` | OM-602, OM-603 | Unit coverage verifies mode entry targets and no regression in mode-aware sidebar/breadcrumb behavior. |

### P6-B - Investigate MVP Pack (Medium Complexity, Single Domain)

Purpose:
1. Deliver the initial investigation selectors promised by the Operator Modes plan.
2. Keep scope locked to By User + By IP, with By Plugin explicitly deferred.

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-611 | Add Investigate landing page with subject selectors and links to existing log tools | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig`, `src/ActionRouter/Constants.php` | OM-601 | Investigate mode has a dedicated landing page linking to Activity/Traffic/IP Rules plus subject selectors. |
| OM-612 | Promote By IP to first-class Investigate navigation entry using existing IP analysis flow | `src/Controller/Plugin/PluginNavs.php`, `src/Modules/Plugin/Lib/NavMenuBuilder.php`, `src/Controller/Plugin/PluginURLs.php` | OM-611 | Users can start IP investigation directly from Investigate mode without first opening another table row. |
| OM-613 | Add By User data helpers in existing data layer | `src/Modules/UserManagement/Lib/Session/FindSessions.php`, `src/DBs/ActivityLogs/LoadLogs.php` | OM-611 | Data-layer contracts can fetch sessions/logs by user ID without duplicating query implementations. |
| OM-614 | Add By User investigation page and template | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByUser.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_user.twig`, `src/ActionRouter/Constants.php` | OM-613 | User investigation view renders activity, sessions, and related IP/request context for selected user. |
| OM-615 | Add focused tests for Investigate selectors and By User flow contracts | `tests/Unit/**`, `tests/Integration/**` | OM-612, OM-614 | Routing, filtering, and empty-state behavior for Investigate MVP pass test coverage. |

### P6-C - Dashboard Widget Alignment Pack (Low Complexity, Isolated Surface)

Purpose:
1. Align the WordPress dashboard widget to the two-indicator operator model.
2. Limit this pack to widget payload/template changes only.

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-621 | Reduce widget payload to config posture + action count using existing contracts | `src/ActionRouter/Actions/Render/Components/Widgets/WpDashboardSummary.php` | OM-601 | Widget data keeps `BuildMeter::trafficFromPercentage()` and queue severity mapping while removing unrelated tables/feeds payload. |
| OM-622 | Simplify widget Twig markup to the two-indicator view | `templates/twig/admin/admin_dashboard_widget_v2.twig` | OM-621 | Widget UI shows only config posture and action queue summary with clear CTA links. |
| OM-623 | Add/adjust tests for simplified widget data and rendering expectations | `tests/Unit/**`, `tests/Integration/**` | OM-621, OM-622 | Test coverage confirms new payload shape and no rendering errors for refresh/all-clear/non-clear states. |

## 5) Prototype B Translation Tasks (When Landing Is Introduced)

| ID | Task | Done When |
|---|---|---|
| UI-101 | Actions hero first layout | Actions card is visually primary and first in reading order |
| UI-102 | Dual hero states (`issues`/`all clear`) | Accent/copy/icon responds to queue state |
| UI-103 | 3-mode strip for Investigate/Configure/Reports | Equal-weight secondary entry cards |
| UI-104 | Responsive behavior | Strip collapses cleanly on narrow widths |
| UI-105 | Accessibility pass | Keyboard/focus/semantic heading-link order passes review |

## 6) Overall Execution Status

| Phase | Status | Notes |
|---|---|---|
| P0 | Complete | Decisions locked |
| P1 (Phase A) | Complete | Meter channel separation done (OM-101-107). See Section 9. |
| P2 | Complete | Dashboard renders channel-aware metrics via `PageOperatorModeLanding`. |
| P3 | Complete | Mode constants (`PluginNavs`), user preference (`OperatorModePreference`), landing page (`PageOperatorModeLanding` + `operator_mode_landing.twig`) all implemented. |
| P4 | Complete | Sidebar two-state navigation (OM-401a-d), WP submenu (OM-402), breadcrumbs (OM-403), and P4.5 sidebar gap closure (OM-410/OM-411) are complete. |
| P5 | Complete | Legacy Simple/Advanced runtime and artifacts removed (OM-501 through OM-508). Operator-mode landing path is now the only overview flow. |
| P6+ | Planned | Grouped packs defined: P6-A (`OM-601` to `OM-604`), P6-B (`OM-611` to `OM-615`), P6-C (`OM-621` to `OM-623`). |

## 7) Next Slice: P6 Kickoff

Recommended grouped execution order:
1. Group 1 (shared mode-entry surfaces): run P6-A (`OM-601` to `OM-604`) as one batch.
2. Group 2 (single-domain investigation MVP): run P6-B (`OM-611` to `OM-615`) as one batch.
3. Group 3 (isolated widget alignment): run P6-C (`OM-621` to `OM-623`) as one batch.

Immediate implementation recommendation:
1. Start with Group 1 + Group 2 in the same delivery train (same branch/PR series) because both touch operator-mode entry flow and Investigate UX.
2. Hold Group 3 for a follow-up pass once mode landing and investigation routes are stable.

Acceptance focus for the next slice:
1. Keep status/severity and traffic mapping contracts unchanged (`BuildMeter::trafficFromPercentage()`, queue `good|warning|critical`).
2. Reuse existing rendering/services and avoid duplicate data-pipeline logic.
3. Keep implementation additive where possible; avoid introducing fallback frameworks.

## 8) Tracking Format

Use:

`[ ] OM-### - owner - branch - status note - date`

Example:

`[ ] OM-104 - pgoodchild - feat/operator-meters-phase-a - in progress: channel-aware cache keying added, unit tests pending - 2026-02-20`

## 9) Phase A Execution Status (Implemented)

Execution date: 2026-02-20  
Scope held to P1 only (`OM-101` to `OM-107`).

[x] OM-101 - codex - local workspace - completed: `Component/Base.php` now includes channel contract and component payload channel output - 2026-02-20  
[x] OM-102 - codex - local workspace - completed: maintenance components + `ScanResultsBase` classified as action channel - 2026-02-20  
[x] OM-103 - codex - local workspace - completed: optional channel threaded through `MeterBase`, `BuildMeter`, `Handler`, and component build path - 2026-02-20  
[x] OM-104 - codex - local workspace - completed: channel-aware meter cache added while preserving combined/default cache behavior - 2026-02-20  
[x] OM-105 - codex - local workspace - completed: divide-by-zero guards added for component percentages and meter totals - 2026-02-20  
[x] OM-106 - codex - local workspace - completed: focused unit tests added for classification, zero-weight safety, channel cache isolation/validation, and `AllComponents` channel propagation - 2026-02-20  
[x] OM-107 - codex - local workspace - completed: consumer files unchanged and continue to use combined/default meter retrieval - 2026-02-20

Validation notes:
1. Focused unit suite pass: `composer test:unit -- tests/Unit/Modules/Plugin/Lib/MeterAnalysis` => `OK (10 tests, 27 assertions)`.
2. Historical note: legacy toggle unit coverage passed at this phase checkpoint; that legacy test file was intentionally removed during P5 cleanup.
3. Integration run attempt for `WpDashboardSummaryIntegrationTest` was skipped because WordPress integration environment is not available in this workspace.
4. No PHPCS run performed (per scope rule).
5. No nav/breadcrumb/operator-landing/toggle-removal/UI-system rewrite changes were introduced in this slice.

## 9.1) P4.5 Execution Status (Implemented)

Execution date: 2026-02-21  
Scope held to P4.5 only (`OM-410` and `OM-411`).

[x] OM-410 - codex - local workspace - completed: Configure mode now includes synthetic `mode-configure-grades` linking to `NAV_DASHBOARD / SUBNAV_DASHBOARD_GRADES` and preserving existing mode filtering/order - 2026-02-21  
[x] OM-411 - codex - local workspace - completed: `.mode-back-link` styling added in `assets/css/components/nav_sidebar_menu.scss` with muted/smaller text, scoped hover, and hidden subtitle - 2026-02-21

Validation notes:
1. New focused unit tests pass: `NavMenuBuilderOperatorModesTest` and `NavSidebarModeBackLinkStyleTest`.
2. Existing related unit tests pass: `PluginNavsOperatorModesTest`, `BuildBreadCrumbsOperatorModesTest`, `NavSidebarTemplateTest`.
3. Scope lock held: only `src/Modules/Plugin/Lib/NavMenuBuilder.php`, `assets/css/components/nav_sidebar_menu.scss`, and two new unit test files changed.
4. No PHPCS and no integration tests were run.

## 10) P5 Artifact Cleanup Execution Status (Implemented)

Execution date: 2026-02-21  
Scope held to P5 artifact cleanup only (`OM-502` to `OM-508`) plus completion lock for `OM-501`.

[x] OM-501 - codex - local workspace - completed: runtime already unwired; dashboard overview contract locked to direct `operator_mode_landing` render path - 2026-02-21  
[x] OM-502 - codex - local workspace - completed: removed `DashboardViewPreference`, `DashboardViewToggle`, `PageDashboardOverviewSimple`, and deprecated simple templates; removed legacy action registration from `Constants::ACTIONS` - 2026-02-21  
[x] OM-503 - codex - local workspace - completed: removed legacy toggle tests and updated `DashboardOverviewRoutingIntegrationTest` to remove toggle-specific assertions and imports - 2026-02-21  
[x] OM-504 - codex - local workspace - completed: removed `DashboardViewToggle` JS import/init and deleted `assets/js/components/general/DashboardViewToggle.js` - 2026-02-21  
[x] OM-505 - codex - local workspace - completed: removed backend action/preference artifacts and all runtime registrations/contracts for `dashboard_view_toggle` and `shield_dashboard_view` - 2026-02-21  
[x] OM-506 - codex - local workspace - completed: removed deprecated simple dashboard renderer/templates and include indirection - 2026-02-21  
[x] OM-507 - codex - local workspace - completed: removed only legacy `.dashboard-view-switch*`, `.inner-page-header-view-toggle`, and `.dashboard-overview-panels*` CSS blocks - 2026-02-21  
[x] OM-508 - codex - local workspace - completed: retired legacy tests and added targeted replacement unit contracts for action registration and dashboard template path - 2026-02-21  

Validation notes:
1. Mandatory legacy reference post-scan returned zero matches in `src`, `templates`, `assets/js`, `assets/css`, and `tests`.
2. Targeted unit test commands for operator modes/template contracts all passed, including:
   `PluginNavsOperatorModesTest`, `OperatorModePreferenceTest`, `NavSidebarTemplateTest`, `NavSidebarModeBackLinkStyleTest`, `ConstantsLegacyDashboardCleanupTest`, `DashboardOverviewTemplateContractTest`.
3. `npm run build` completed successfully.
4. No PHPCS and no integration tests were run for this pass (per scope lock).
5. `BuildMeter::trafficFromPercentage()`, `NeedsAttentionQueue`, and `PageOperatorModeLanding` status logic were not modified.

