# Shield Operator Modes - Delivery Task Backlog (Validated)

Date: 2026-02-20  
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

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-401 | Make sidebar nav mode-aware | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-301 | Internal sidebar filtered by mode |
| OM-402 | Replace WP submenu with mode entries | `src/ActionRouter/Actions/PluginAdmin/PluginAdminPageHandler.php` | OM-301 | Submenu becomes mode-oriented without preference mutation side effects |
| OM-403 | Update breadcrumbs for mode pathing | `src/Utilities/Navigation/BuildBreadCrumbs.php` | OM-401 | Breadcrumb path follows mode model |

### P5 - Cleanup And Legacy Removal (Trigger-Based)

| ID | Task | Trigger | Files | Done When |
|---|---|---|---|---|
| OM-501 | Unwire legacy dashboard toggle runtime (no deletions) | After P4 ships | `templates/twig/wpadmin/plugin_pages/base_inner_page.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview.twig`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverview.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/BasePluginAdminPage.php`, `src/Modules/Plugin/Lib/AssetsCustomizer.php` | Dashboard overview renders a single flow (no Simple/Advanced branch toggle) and no active UI/runtime path depends on `dashboard_view_toggle` |
| OM-502 | Remove deprecated Simple/Advanced classes/templates (hard removal phase) | After OM-501 stabilization | `src/Modules/Plugin/Lib/Dashboard/DashboardViewPreference.php`, `src/ActionRouter/Actions/DashboardViewToggle.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverviewSimple.php`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple_body.twig`, `src/ActionRouter/Constants.php` | Deprecated assets removed and references cleaned |
| OM-503 | Replace/retire tests tied to old toggle behavior | After OM-502 | `tests/Integration/ActionRouter/DashboardViewToggleIntegrationTest.php`, `tests/Unit/Modules/Plugin/Lib/Dashboard/DashboardViewPreferenceTest.php`, affected assertions in `tests/Integration/ActionRouter/DashboardOverviewRoutingIntegrationTest.php` | Test suite reflects operator-mode behavior, not legacy toggle |

## 5) Prototype B Translation Tasks (When Landing Is Introduced)

| ID | Task | Done When |
|---|---|---|
| UI-101 | Actions hero first layout | Actions card is visually primary and first in reading order |
| UI-102 | Dual hero states (`issues`/`all clear`) | Accent/copy/icon responds to queue state |
| UI-103 | 3-mode strip for Investigate/Configure/Reports | Equal-weight secondary entry cards |
| UI-104 | Responsive behavior | Strip collapses cleanly on narrow widths |
| UI-105 | Accessibility pass | Keyboard/focus/semantic heading-link order passes review |

## 6) Phase A Starter Slice (Next Session)

Start strictly with:
1. OM-101
2. OM-102
3. OM-103
4. OM-104
5. OM-105
6. OM-106
7. OM-107

Phase A definition of done:
1. Config-only and action-only meter retrieval are both available.
2. Action-channel includes maintenance + scan-result components.
3. Combined/default meter consumers remain functional with no behavior break.
4. No nav/routing/legacy-toggle cleanup has started yet.

## 7) Verification Checklist For Implementers

Before coding:
1. Confirm no existing channel API already exists.
2. Confirm all `ScanResults*` classes are included in action classification.
3. Confirm cache key strategy in `Handler` cannot collide by channel.

After coding:
1. Run focused unit tests only for Phase A changes.
2. Confirm no integration test run is required for this slice.
3. Confirm no PHPCS run is required for this slice.
4. Confirm no unrelated files are modified.

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
2. Legacy toggle unit coverage still passes: `composer test:unit -- tests/Unit/Modules/Plugin/Lib/Dashboard/DashboardViewPreferenceTest.php`.
3. Integration run attempt for `WpDashboardSummaryIntegrationTest` was skipped because WordPress integration environment is not available in this workspace.
4. No PHPCS run performed (per scope rule).
5. No nav/breadcrumb/operator-landing/toggle-removal/UI-system rewrite changes were introduced in this slice.

## 10) Deferred Hard-Removal Tasks After OM-501

These tasks are intentionally deferred until the runtime unwire (`OM-501`) has settled.

| ID | Task | Files | Done When |
|---|---|---|---|
| OM-504 | Remove JS bootstrap wiring for legacy toggle | `assets/js/app/AppMain.js`, `assets/js/components/general/DashboardViewToggle.js` | `DashboardViewToggle` import/init is removed and legacy class file is deleted |
| OM-505 | Remove legacy action/preference backend artifacts | `src/ActionRouter/Constants.php`, `src/ActionRouter/Actions/DashboardViewToggle.php`, `src/Modules/Plugin/Lib/Dashboard/DashboardViewPreference.php` | No action registration or runtime contract remains for `dashboard_view_toggle` / `shield_dashboard_view` |
| OM-506 | Remove deprecated simple dashboard renderer/templates | `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverviewSimple.php`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple_body.twig` | Deprecated simple-render path is fully removed with no remaining includes/references |
| OM-507 | Remove obsolete toggle/panel CSS | `assets/css/plugin-main.scss`, `assets/css/shield/dashboard.scss` | `.dashboard-view-switch*` and `.dashboard-overview-panels*` selectors are removed after runtime references are gone |
| OM-508 | Migrate/remove legacy toggle tests | `tests/Integration/ActionRouter/DashboardViewToggleIntegrationTest.php`, `tests/Unit/Modules/Plugin/Lib/Dashboard/DashboardViewPreferenceTest.php`, `tests/Integration/ActionRouter/DashboardOverviewRoutingIntegrationTest.php` | Old toggle tests removed/replaced and overview assertions reflect a single non-toggle render path |

Deferred execution notes:
1. Do not add PHPCS to these tasks.
2. Integration tests are not required for this cleanup pass.
3. Preserve existing meter severity/traffic logic (`BuildMeter::trafficFromPercentage()` and queue `good|warning|critical`) without introducing new fallback behavior.
