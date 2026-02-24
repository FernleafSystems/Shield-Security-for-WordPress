# Shield Operator Modes - Delivery Task Backlog (Validated)

Date: 2026-02-22 (updated, re-baselined to current code state)
Validated against source: `src/`, `templates/twig/`, `assets/js/`, `assets/css/`, `tests/`
Source plan: `Shield-Operator-Modes-Plan.md`
Prototype references:
- Mode selector: `docs/OperatorModes/prototype-b-hero-strip.html`
- Investigate mode: `docs/OperatorModes/investigate-mode/` (4 files: `investigate-landing.html`, `investigate-user.html`, `investigate-ip.html`, `investigate-plugin.html`)

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

**Implementation status (2026-02-22):** Complete. OM-401 through OM-403 are implemented in code. The two-state sidebar behavior exists in `NavMenuBuilder` (`buildModeSelector()`, `buildModeNav()`, `resolveCurrentMode()` returning mode selector state for dashboard nav), and unit coverage exists in `tests/Unit/Modules/Plugin/Lib/NavMenuBuilderOperatorModesTest.php`.

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-401 | ~~Make sidebar nav mode-aware~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-301 | **Done** — mode selector state + in-mode state sidebar behavior implemented. |
| OM-401a | ~~Fix `resolveCurrentMode()` to return empty string for dashboard nav~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401 | **Done** — dashboard nav resolves to mode selector state (`''`). |
| OM-401b | ~~Add `buildModeSelector()` method for State 1 sidebar~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401a | **Done** — `build()` returns 4 mode entries in selector state. |
| OM-401c | ~~Add `buildModeNav()` method for State 2 sidebar (back link + mode items)~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401a | **Done** — in-mode menu prepends back link and mode-specific items. |
| OM-401d | ~~Remove `NAV_DASHBOARD` from `allowedNavsForMode()`~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401c | **Done** — dashboard entry is no longer mode-menu fallback. |
| OM-402 | ~~Replace WP submenu with mode entries~~ | `src/ActionRouter/Actions/PluginAdmin/PluginAdminPageHandler.php` | OM-301 | **Done** — `addSubMenuItems()` registers Dashboard, Actions Queue, Investigate, Configure, Reports, Go PRO. |
| OM-403 | ~~Update breadcrumbs for mode pathing~~ | `src/Utilities/Navigation/BuildBreadCrumbs.php` | OM-401 | **Done** — `parse()` builds mode-aware breadcrumbs: Shield Security → Mode Label → Current Page. |

#### Implementation notes for OM-401a–d (historical)

**File:** `src/Modules/Plugin/Lib/NavMenuBuilder.php`

**Current `build()` method structure (lines 30–104):**
1. Builds full 9-item menu array from private methods
2. Calls `filterMenuForMode($menu, $this->resolveCurrentMode())`
3. Normalizes items (defaults, active state, sub-item processing, security admin checks)
4. Returns processed menu

**Target `build()` method structure:**
1. Call `resolveCurrentMode()` (after OM-401a fix)
2. If mode is `''`: call `buildModeSelector()` → returns 4 mode items + gopro
3. If mode is non-empty: call `buildModeNav($mode)` → returns back-link + filtered items + gopro
4. Normalize items (same logic as current lines 44–101, extracted to a shared method)

**Key constraint:** The normalization loop (lines 44–101) must still run on whatever array is returned. Extract it to a `normalizeMenu(array $menu): array` method and call it from `build()` after either path.

**`nav_sidebar.twig` impact:** None expected. The template iterates `vars.navbar_menu` generically. The back-link item and mode-selector items are standard items with the same shape. If you want to style the back link differently, add `'mode-back-link'` to its `classes` array and add a CSS rule.

**Testing approach:** No unit test infrastructure changes needed. Verify manually:
1. Navigate to Dashboard (mode selector) → sidebar shows 4 mode entries + Go PRO
2. Click "Investigate" → sidebar shows back link + Activity Logs + Bots & IP Rules + Go PRO
3. Click "← Shield Security" → returns to mode selector, sidebar returns to State 1
4. Navigate to any page via WP admin sidebar → sidebar shows correct mode's items with back link

### P4.5 - Sidebar Follow-Up (Completed)

These were delivered after P4 and before P5 cleanup:

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-410 | ~~Add Security Grades as a direct link in Configure mode sidebar~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401c | **Done (2026-02-21)** — Configure mode inserts `mode-configure-grades` linking to dashboard grades. |
| OM-411 | ~~Optional: Style back link distinctly~~ | `assets/css/components/nav_sidebar_menu.scss`, `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401c | **Done (2026-02-21)** — `.mode-back-link` styling added and scoped to sidebar nav link. |

### P5 - Cleanup And Legacy Removal (Trigger-Based)

**Execution status:** Complete (2026-02-21). See archive execution plan for implementation notes.

| ID | Task | Trigger | Files | Done When |
|---|---|---|---|---|
| OM-501 | ~~Unwire legacy dashboard toggle runtime (no deletions)~~ | After P4 ships | `templates/twig/wpadmin/plugin_pages/base_inner_page.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview.twig`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverview.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/BasePluginAdminPage.php`, `src/Modules/Plugin/Lib/AssetsCustomizer.php` | **Done** — no active runtime path depends on `dashboard_view_toggle`. |
| OM-502 | ~~Remove deprecated Simple/Advanced classes/templates (hard removal phase)~~ | After OM-501 stabilization | `src/Modules/Plugin/Lib/Dashboard/DashboardViewPreference.php`, `src/ActionRouter/Actions/DashboardViewToggle.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverviewSimple.php`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple_body.twig`, `src/ActionRouter/Constants.php` | **Done** — deprecated assets removed and references cleaned. |
| OM-503 | ~~Replace/retire tests tied to old toggle behavior~~ | After OM-502 | `tests/Integration/ActionRouter/DashboardViewToggleIntegrationTest.php`, `tests/Unit/Modules/Plugin/Lib/Dashboard/DashboardViewPreferenceTest.php`, affected assertions in `tests/Integration/ActionRouter/DashboardOverviewRoutingIntegrationTest.php` | **Done** — legacy toggle tests removed/replaced. |

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
| P0 | ✅ Complete | Decisions locked |
| P1 (Phase A) | ✅ Complete | Meter channel separation done (OM-101–107). See Section 9. |
| P2 | ✅ Complete | Dashboard renders channel-aware metrics via `PageOperatorModeLanding`. |
| P3 | ✅ Complete | Mode constants (`PluginNavs`), user preference (`OperatorModePreference`), landing page (`PageOperatorModeLanding` + `operator_mode_landing.twig`) all implemented. |
| P4 | ✅ Complete | Two-state sidebar implemented (`buildModeSelector`, `buildModeNav`, mode selector fallback) plus WP submenu and breadcrumbs. |
| P4.5 | ✅ Complete | Configure-grade shortcut and back-link styling delivered (OM-410/OM-411). |
| P5 | ✅ Complete | Legacy Simple/Advanced toggle artifacts removed (runtime + source + tests). |
| P6-FOUND | ✅ Complete | **Shared Investigation Table Framework** implemented: `BaseInvestigationTable`, `BaseInvestigationData`, investigation Build/LoadData child classes, `InvestigationTable.js`, `InvestigationTableAction`, and shared Twig partials. |
| P6a | ✅ Complete | Investigate landing delivered as subject-selector + panel architecture with quick tools, transitional sub-nav routes, landing-consistent breadcrumbs, and behavioral unit coverage. |
| P6b | ⚠ Partially complete | By-user route/page exists, but implementation still uses static HTML tables and must be refactored to shared investigation DataTables. |
| P6c | ❌ Not started | Investigate IP — wraps IpAnalyse\Container with subject header. Prototype: `investigate-ip.html`. |
| P6d | ❌ Not started | Investigate Plugin — 4-tab analysis page. Prototype: `investigate-plugin.html`. |
| P6e | ❌ Not started | Investigate Theme + WP Core — reuses plugin pattern. |
| P6f | ❌ Not started | Cross-subject linking (IP↔User↔Plugin). |
| P7+ | ❌ Not started | Configure/Reports deepening, WP dashboard widget. |

## 7) Next Slice: P6 Integration Completion (OM-611+)

P6 foundation (`OM-600a` to `OM-600g`) is complete in code:
1. Investigation Build/LoadData base classes and child classes exist for Activity, Traffic, Sessions, and File Scan Results.
2. `InvestigationTable.js` exists and is bootstrapped.
3. `InvestigationTableAction` exists and routes by `table_type` + `subject_type` + `subject_id`.
4. Shared investigate partials (`subject_header.twig`, `table_container.twig`) exist.

Next execution focus:
1. OM-611 to OM-616: refactor investigate-by-user tabs from static rendering to shared investigation DataTables.
2. Continue with P6c+ once OM-611 integration is complete.

Verification targets for next slice:
1. At least one investigate tab rendered through `table_container.twig` with `data-investigation-table="1"`.
2. Search/filter/pagination for that tab round-trips through `InvestigationTableAction`.
3. No new static HTML tables are introduced.

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
2. Historical note: `DashboardViewPreferenceTest.php` was later removed in P5 legacy cleanup.
3. Integration run attempt for `WpDashboardSummaryIntegrationTest` was skipped because WordPress integration environment is not available in this workspace.
4. No PHPCS run performed (per scope rule).
5. No nav/breadcrumb/operator-landing/toggle-removal/UI-system rewrite changes were introduced in this slice.

## 10) P6 — Investigate Mode Implementation

**Date added:** 2026-02-22
**Prototype reference:** `docs/OperatorModes/investigate-mode/` (4 interactive HTML files)
**Plan reference:** Section 11 (UI Spec) + Section 12 (Implementation Architecture) of `Shield-Operator-Modes-Plan.md`

**Before starting ANY task in this section, implementors MUST:**
1. Read **Section 12 of the plan document** in full — it maps every reusable component in the plugin and gives explicit directives on what to extend vs. create
2. Read **Section 11** for UI specifications, tab definitions, and data sources
3. Open and review ALL prototype HTML files in `docs/OperatorModes/investigate-mode/` in a browser
4. Read the existing classes listed in Section 12.1's component inventory — understand the class hierarchy before writing code

**Guiding principles:**
- **DRY (Don't Repeat Yourself).** Every table, every link, every timestamp is rendered by a shared component. If you're writing timestamp formatting logic, stop — `BaseBuildTableData::getColumnContent_Date()` already does it. If you're generating an IP link, stop — `BaseBuildTableData::getColumnContent_LinkedIP()` already does it.
- **Extend, don't duplicate.** The existing DataTable infrastructure (`Build\Base`, `BaseBuildTableData`, `ShieldTableBase.js`) is battle-tested with 50,000+ installs. Investigation tables are narrower versions of full-page tables — create child classes that inherit the heavy lifting and only override what's different (subject filter, column selection, SearchPane removal).
- **One framework, all tables.** A change to `BaseInvestigationTable` or `InvestigationTable.js` must apply to every investigation table across every subject page. This is the test: "if I change the page size from 15 to 25 in one place, does it change everywhere?" The answer must be yes.

### P6-FOUNDATION — Shared Investigation Table Framework

> **This phase MUST be completed before ANY tab implementation (OM-611+).** It creates the shared infrastructure all investigation tables depend on. See Plan Section 12.2 for full specification.

**Implementation status (2026-02-22):** ✅ Complete in code. Foundation classes/actions/JS/partials are present; remaining work is integration into tab pages (OM-611+).

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-600a | Create `BaseInvestigationTable` (Build layer) | `src/Tables/DataTables/Build/Investigation/BaseInvestigationTable.php` | P4 complete | Extends `Build\Base`. Adds `$subjectType`/`$subjectId` properties and `setSubject()` setter. Overrides `getSearchPanesData()` → returns `[]` (no SearchPanes by default). Adds `getSubjectFilterColumns(): array` method — columns to hide because they're redundant for the subject (e.g., user column when investigating a user). `getColumnsToDisplay()` calls parent then removes subject filter columns. See Plan Section 12.2 for pseudocode. |
| OM-600b | Create `BaseInvestigationData` (LoadData layer) | `src/Tables/DataTables/LoadData/Investigation/BaseInvestigationData.php` | OM-600a | Extends `BaseBuildTableData`. Adds `$subjectType`/`$subjectId` properties. Overrides `buildWheresFromSearchParams()` to always inject subject filter via abstract `getSubjectWheres(): array`. Keeps strict `getSearchPanesDataBuilder()` return contracts and disables SearchPanes in investigation context via empty-array behavior. Child classes implement `getSubjectWheres()` for their specific subject type. **Critical:** inherits `getColumnContent_Date()`, `getColumnContent_LinkedIP()`, `getUserHref()` from parent — these are the shared rendering utilities for timestamps, IPs, and user links. |
| OM-600c | Create investigation-specific Build children | `src/Tables/DataTables/Build/Investigation/ForActivityLog.php`, `ForTraffic.php`, `ForSessions.php`, `ForFileScanResults.php` | OM-600a | Each extends `BaseInvestigationTable`. Column definitions mirror their parent full-page equivalents (`Build\ForActivityLog`, `Build\ForTraffic`, `Build\ForSessions`, `Build\Scans\ForPluginTheme`) but with SearchPanes disabled and subject-redundant columns removed. **Reuse** column definitions from the parent classes — copy the `getColumnDefs()` return value and adjust, or call `parent::getColumnDefs()` and filter. |
| OM-600d | Create investigation-specific LoadData children | `src/Tables/DataTables/LoadData/Investigation/BuildActivityLogData.php`, `BuildTrafficData.php`, `BuildSessionsData.php`, `BuildFileScanResultsData.php` | OM-600b | Each extends `BaseInvestigationData`. Implements `getSubjectWheres()` for its subject type. **Delegates row building** to the same logic as the corresponding full-page loader (e.g., `BuildActivityLogTableData::buildTableRowsFromRawRecords()`). Option: extract row-building into a trait or call a shared static method so both the full-page loader and investigation loader share the same row rendering code. |
| OM-600e | Create `InvestigationTable.js` | `assets/js/components/tables/InvestigationTable.js` | OM-600a | Extends `ShieldTableBase`. Overrides `getDefaultDatatableConfig()`: `dom: 'frtip'` (text search + table + info + pagination — no SearchPanes, no buttons), `pageLength: 15`, `select: false`. Passes `table_type`, `subject_type`, and `subject_id` with every AJAX request. **One JS class for all investigation tables** — a change here applies everywhere. |
| OM-600f | Create `InvestigationTableAction` AJAX handler | `src/ActionRouter/Actions/InvestigationTableAction.php` | OM-600d | Handles `retrieve_table_data` sub-action. Receives `subject_type`, `subject_id`, and `table_type` in action data. Routes to the correct `BuildInvestigation*Data` class. Pattern: mirror existing `ActivityLogTableAction` but dispatches based on `table_type` parameter. |
| OM-600g | Create shared Twig partials | `templates/twig/wpadmin/components/investigate/subject_header.twig`, `templates/twig/wpadmin/components/investigate/table_container.twig` | None | `subject_header.twig`: Reusable subject header bar (avatar, name, meta, status pills, "Change" button). Accepts `subject` data object. `table_container.twig`: Wraps a DataTable `<table>` element with config data attributes + panel heading + optional "Full Log" link. Used inside every tab pane. |

### P6a — Investigate Landing Page

**Implementation status (2026-02-24):** ✅ Complete (`OM-601` through `OM-604`).

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-601 | ~~Refactor existing `PageInvestigateLanding` with subject selector grid~~ | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig` | P4 complete | **Done (2026-02-24)** — landing handler refactored to subject-driven contract (`active_subject`, `input`, `plugin_options`, `theme_options`), active-subject precedence implemented, inline IP analysis rendering removed, by-user query key contract preserved. Plugin/theme options are sourced from existing service wrappers (`Services::WpPlugins()->getPluginsAsVo()`, `Services::WpThemes()->getThemesAsVo()`). |
| OM-602 | ~~Implement lookup panels with autocomplete/dropdown~~ | Same as OM-601 + JS | OM-601 | **Done (2026-02-24)** — template replaced with Bootstrap tab subject cards and required lookup/direct-link panels (users, IPs, plugins, themes, core, requests, activity). No custom JS component and no new AJAX endpoint introduced. |
| OM-603 | ~~Add quick-tools strip below lookup panels~~ | Same as OM-601 | OM-601 | **Done (2026-02-24)** — persistent quick-tools strip implemented with existing routes: Activity Log, HTTP Request Log, Live HTTP Log, IP Rules. |
| OM-604 | ~~Register NAV constants for investigation sub-navs~~ | `src/Controller/Plugin/PluginNavs.php` | OM-601 | **Done (2026-02-24)** — added `by_plugin`, `by_theme`, `by_core` activity sub-nav constants and transitional route mappings to `PageInvestigateLanding`; updated breadcrumb landing-route detection so `activity/by_ip`, `activity/by_plugin`, `activity/by_theme`, `activity/by_core` retain landing-consistent crumb depth. |

P6a validation evidence (2026-02-24):
1. Fragile source-string test removed: `tests/Unit/InvestigateByIpLandingContractTest.php`.
2. New behavior-focused landing test added: `tests/Unit/ActionRouter/Render/PageInvestigateLandingBehaviorTest.php`.
3. Transitional route coverage extended:
   - `tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php`
   - `tests/Unit/Utilities/Navigation/BuildBreadCrumbsOperatorModesTest.php`
4. Targeted unit test runs passed for all three files above.

### P6b — Investigate User

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-611 | **Refactor** existing `PageInvestigateByUser` with rail+panel layout | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByUser.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_user.twig` | OM-604, **OM-600a–f** | **Do NOT create a new file.** Refactor the existing `PageInvestigateByUser.php`. **Keep:** current lookup resolution flow and `buildSessions()`, `buildActivityLogs()`, `buildRequestLogs()`, `buildRelatedIps()` — use these for summary stat counts. **Replace:** template with rail+panel layout using `options_rail_tabs.twig` for the rail (see Plan Section 12.3). **Wire tabs** through the shared DataTable framework — pass `datatables_init` JSON from `Investigation\ForSessions`, `Investigation\ForActivityLog`, `Investigation\ForTraffic` and AJAX action data from `InvestigationTableAction`. Tables are AJAX-loaded by `InvestigationTable.js`, not rendered as static HTML. See Plan Section 12.4.3. |
| OM-612 | Validate and use `FindSessions::byUser(int $userId)` method | `src/Modules/UserManagement/Lib/Session/FindSessions.php` | None | Confirm existing method behavior remains aligned with `byIP()` pattern and use it for Sessions investigation DataTable and summary stat counts. |
| OM-613 | Wire Sessions tab through investigation DataTable | `Investigation\ForSessions`, `Investigation\BuildSessionsData` | **OM-600c, OM-600d**, OM-612 | Sessions tab uses `Investigation\ForSessions` (column config) + `Investigation\BuildSessionsData` (data loading with `getSubjectWheres() → ['user_id' => $uid]`). Row building **reuses** existing `BuildSessionsTableData::buildTableRowsFromRawRecords()` logic. Columns: Login, Last Active, Logged In, IP Address (via `getColumnContent_LinkedIP()`), Sec Admin. No 'user' column (redundant — it's the investigation subject). |
| OM-614 | Wire Activity tab through investigation DataTable | `Investigation\ForActivityLog`, `Investigation\BuildActivityLogData` | **OM-600c, OM-600d** | Activity tab uses `Investigation\ForActivityLog` + `Investigation\BuildActivityLogData` with `getSubjectWheres() → ['user_id' => $uid]`. Row building **reuses** `BuildActivityLogTableData::buildTableRowsFromRawRecords()`. Timestamps via `getColumnContent_Date()`, IPs via `getColumnContent_LinkedIP()`. "Full Log" link → Activity Log page with `?search=user_id:{uid}` pre-filter. |
| OM-615 | Wire Requests tab through investigation DataTable | `Investigation\ForTraffic`, `Investigation\BuildTrafficData` | **OM-600c, OM-600d** | Same pattern. Subject filter: `['uid' => $uid]`. Row building reuses `BuildTrafficTableData` logic. "Full Log" link → Traffic Log with `?search=user_id:{uid}`. |
| OM-616 | Implement IP Addresses tab (card grid, not DataTable) | Same as OM-611 | OM-611 | **Exception:** This tab is NOT a DataTable — it's a card grid rendered server-side. Aggregate unique IPs from `buildSessions()` + `buildRequestLogs()` return arrays. For each IP, render a shield-card using `getColumnContent_LinkedIP()` for the link. Card data: IP, last seen, status badge, counts. Render with a simple Twig loop (no DataTable needed — IP lists are typically <20 items). |

### P6c — Investigate IP Address

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-621 | Create `PageInvestigateByIp` wrapping existing `IpAnalyse\Container` | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByIp.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_ip.twig` | OM-604 | **Reuse `IpAnalyse\Container` as-is.** Do NOT rebuild the 5-tab analysis. This page adds: (1) subject header using shared `subject_header.twig` partial, (2) summary stats row, (3) renders existing `IpAnalyse\Container` below via `self::con()->action_router->render(IpAnalyseContainer::class, ['ip' => $ip])`. The only new code is the subject header/stats wrapper. See Plan Section 12.4.4. |
| OM-622 | Add "Change IP" button linking back to landing | Same as OM-621 | OM-621 | Subject header uses shared `subject_header.twig` partial — "Change IP" button comes from the `change_href` data field linking to investigate landing with IPs subject pre-selected. |

### P6d — Investigate Plugin

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-631 | Create `PageInvestigateByPlugin` with 4-tab rail+panel | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByPlugin.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_plugin.twig` | OM-604, **OM-600a–f** | Page handler. Rail tabs via `options_rail_tabs.twig` (reused). Subject header via shared `subject_header.twig` (reused). Summary stats via `stats_collection.twig` (reused). See Plan Section 12.4.5. |
| OM-632 | Implement Overview tab | Same as OM-631 | OM-631 | **Reuse** `PluginThemesBase::buildPluginData()` for all plugin info (name, slug, version, author, flags). This method already returns `info[]`, `flags[]`, `vars[]` arrays with everything the Overview tab needs. Server-side rendered (no DataTable). Two-column Twig layout. |
| OM-633 | Implement File Status tab via investigation DataTable | Same as OM-631, `Investigation\ForFileScanResults`, `Investigation\BuildFileScanResultsData` | **OM-600c, OM-600d** | Uses investigation DataTable framework with `getSubjectWheres() → ['ptg_slug' => $slug]`. Row building **reuses** existing `LoadFileScanResultsTableData::buildTableRowsFromRawRecords()` logic (which already renders file paths, status badges, and action buttons). Do NOT rebuild the action buttons — inherit them. |
| OM-634 | Implement Vulnerabilities tab (server-side card list) | Same as OM-631 | OM-631 | **NOT a DataTable.** Vulnerability lists are typically 0–3 items. Query `WpVulnDb` by plugin slug, get `VulnVO` objects (title, vuln_type, fixed_in, disclosed_at). Cross-reference current installed version to determine Active vs Resolved. Render as Twig card list. |
| OM-635 | Implement Activity tab via investigation DataTable | Same as OM-631, `Investigation\ForActivityLog`, `Investigation\BuildActivityLogData` | **OM-600c, OM-600d** | Same framework as OM-614 but with plugin subject filter: `getSubjectWheres() → ['plugin_slug' => $slug]`. Filter activity records where event meta contains the plugin slug (activations, deactivations, updates, file changes). Row building reuses `BuildActivityLogTableData` logic. |

### P6e — Investigate Theme + WordPress Core

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-641 | Create `BaseInvestigateAsset` shared by Plugin + Theme | `src/ActionRouter/Actions/Render/PluginAdminPages/BaseInvestigateAsset.php` | OM-631 | **Extract common logic** from `PageInvestigateByPlugin` into a shared parent class. Both Plugin and Theme pages extend this. Shared: subject header, summary stats, rail+panel layout, File Status tab (DataTable with `ptg_slug` filter), Vulnerability tab (card list), Activity tab (DataTable with asset slug filter). Theme-specific: `buildThemeData()` instead of `buildPluginData()`, additional child/parent theme fields. See Plan Section 12.4.6. |
| OM-642 | Create WordPress Core investigation page | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByCore.php` | OM-604, **OM-600c, OM-600d** | Simplified: Overview (version, auto-update config), File Status (investigation DataTable with `getSubjectWheres() → ['is_in_core' => true]` — reuses `Investigation\BuildFileScanResultsData`), Activity (core-related events). |

### P6f — Cross-subject linking

> **These tasks should be inherently satisfied** if the cross-cutting rules in Plan Section 12.5 are followed. Every IP link uses `getColumnContent_LinkedIP()` which already generates linkable IPs. Every user link uses `getUserHref()`. The tasks below are verification tasks.

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-651 | Verify all IP address cells link to investigate-ip | All investigation DataTable `buildTableRowsFromRawRecords()` methods | OM-621 | Confirm `getColumnContent_LinkedIP()` output includes both `offcanvas_ip_analysis` class (for quick offcanvas view) AND a separate link/button to the full `investigate-ip` page. If `getColumnContent_LinkedIP()` doesn't currently do this, modify it ONCE in `BaseBuildTableData` — the change propagates to every table. |
| OM-652 | Verify all username cells link to investigate-user | All investigation DataTable row builders | OM-611 | Confirm `getUserHref()` links to `investigate-user` page (not WordPress user profile). If it currently links to WP profile, modify it ONCE in `BaseBuildTableData`. |
| OM-653 | Wire plugin-related activity events to investigate-plugin | `Investigation\BuildActivityLogData` row builder | OM-631 | Plugin names in activity event descriptions link to `investigate-plugin?slug={slug}`. This may need a custom column renderer in the activity log row builder — add it to the shared `BuildActivityLogData` class so it applies to all activity tables. |

## 11) Hard-Removal Tasks (Completed)

These tasks were deferred behind OM-501 and have now been executed (2026-02-21). This section is retained as a completion record.

| ID | Task | Files | Done When |
|---|---|---|---|
| OM-504 | ~~Remove JS bootstrap wiring for legacy toggle~~ | `assets/js/app/AppMain.js`, `assets/js/components/general/DashboardViewToggle.js` | **Done** — `DashboardViewToggle` bootstrap removed and legacy class deleted. |
| OM-505 | ~~Remove legacy action/preference backend artifacts~~ | `src/ActionRouter/Constants.php`, `src/ActionRouter/Actions/DashboardViewToggle.php`, `src/Modules/Plugin/Lib/Dashboard/DashboardViewPreference.php` | **Done** — no runtime contract remains for `dashboard_view_toggle` / `shield_dashboard_view`. |
| OM-506 | ~~Remove deprecated simple dashboard renderer/templates~~ | `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverviewSimple.php`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple_body.twig` | **Done** — deprecated simple-render path removed. |
| OM-507 | ~~Remove obsolete toggle/panel CSS~~ | `assets/css/plugin-main.scss`, `assets/css/shield/dashboard.scss` | **Done** — obsolete toggle/panel selectors removed. |
| OM-508 | ~~Migrate/remove legacy toggle tests~~ | `tests/Integration/ActionRouter/DashboardViewToggleIntegrationTest.php`, `tests/Unit/Modules/Plugin/Lib/Dashboard/DashboardViewPreferenceTest.php`, `tests/Integration/ActionRouter/DashboardOverviewRoutingIntegrationTest.php` | **Done** — legacy toggle test coverage migrated/removed. |

Deferred execution notes:
1. Do not add PHPCS to these tasks.
2. Integration tests are not required for this cleanup pass.
3. Preserve existing meter severity/traffic logic (`BuildMeter::trafficFromPercentage()` and queue `good|warning|critical`) without introducing new fallback behavior.
