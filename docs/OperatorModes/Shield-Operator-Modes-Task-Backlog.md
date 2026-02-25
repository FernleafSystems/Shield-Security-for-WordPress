# Shield Operator Modes - Delivery Task Backlog (Validated + Runtime Reality Check)

Date: 2026-02-22 (updated 2026-02-25 with P6-STAB closure and P6c-f completion confirmation)
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

### VF-4 (Resolved 2026-02-25): Investigate lookup flow runtime gap

Original finding:
1. Rendered investigate forms had expected full action URLs.
2. Submit flow could still degrade to a bare `admin.php?...` request in some runtime paths.

Resolution:
1. Routing hardening and regression coverage were implemented in P6-STAB (`OM-672`, `OM-673`).
2. Runtime behavior for landing/by-user lookup flows was confirmed working by maintainer validation.
3. `P6a`, `P6b`, `P6-STAB`, and `P6c` through `P6f` are now closed.

## 3) Phase Map (Revised For Low-Risk Start)

| Phase | Goal | Exit Criteria |
|---|---|---|
| P0 | Decision lock + risk controls | Validated findings and scope controls agreed |
| P1 (Phase A) | Meter channel foundation only | Config-only score available; action-channel components correctly classified; no consumer regressions |
| P2 (Starter UI) | Minimal UI adaptation using existing dashboard plumbing | Existing dashboard can render channel-specific metrics without nav/routing rewrite |
| P3 | Mode model + operator preference + selector landing | Operator constants/preference/landing introduced additively |
| P4 | Mode-aware nav and WP submenu | Sidebar and WP submenu switch to mode model |
| P5 | Mode cleanup + legacy removal | Simple/Advanced toggle and deprecated artifacts removed safely |
| P6-STAB | Investigate runtime stabilization | Investigate lookup submits retain `page/nav/nav_sub` in first request across landing/by-user flows |
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
| OM-401 | ~~Make sidebar nav mode-aware~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-301 | **Done**  -  mode selector state + in-mode state sidebar behavior implemented. |
| OM-401a | ~~Fix `resolveCurrentMode()` to return empty string for dashboard nav~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401 | **Done**  -  dashboard nav resolves to mode selector state (`''`). |
| OM-401b | ~~Add `buildModeSelector()` method for State 1 sidebar~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401a | **Done**  -  `build()` returns 4 mode entries in selector state. |
| OM-401c | ~~Add `buildModeNav()` method for State 2 sidebar (back link + mode items)~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401a | **Done**  -  in-mode menu prepends back link and mode-specific items. |
| OM-401d | ~~Remove `NAV_DASHBOARD` from `allowedNavsForMode()`~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401c | **Done**  -  dashboard entry is no longer mode-menu fallback. |
| OM-402 | ~~Replace WP submenu with mode entries~~ | `src/ActionRouter/Actions/PluginAdmin/PluginAdminPageHandler.php` | OM-301 | **Done**  -  `addSubMenuItems()` registers Dashboard, Actions Queue, Investigate, Configure, Reports, Go PRO. |
| OM-403 | ~~Update breadcrumbs for mode pathing~~ | `src/Utilities/Navigation/BuildBreadCrumbs.php` | OM-401 | **Done**  -  `parse()` builds mode-aware breadcrumbs: Shield Security -> Mode Label -> Current Page. |

#### Implementation notes for OM-401a-d (historical)

**File:** `src/Modules/Plugin/Lib/NavMenuBuilder.php`

**Current `build()` method structure (lines 30-104):**
1. Builds full 9-item menu array from private methods
2. Calls `filterMenuForMode($menu, $this->resolveCurrentMode())`
3. Normalizes items (defaults, active state, sub-item processing, security admin checks)
4. Returns processed menu

**Target `build()` method structure:**
1. Call `resolveCurrentMode()` (after OM-401a fix)
2. If mode is `''`: call `buildModeSelector()` -> returns 4 mode items + gopro
3. If mode is non-empty: call `buildModeNav($mode)` -> returns back-link + filtered items + gopro
4. Normalize items (same logic as current lines 44-101, extracted to a shared method)

**Key constraint:** The normalization loop (lines 44-101) must still run on whatever array is returned. Extract it to a `normalizeMenu(array $menu): array` method and call it from `build()` after either path.

**`nav_sidebar.twig` impact:** None expected. The template iterates `vars.navbar_menu` generically. The back-link item and mode-selector items are standard items with the same shape. If you want to style the back link differently, add `'mode-back-link'` to its `classes` array and add a CSS rule.

**Testing approach:** No unit test infrastructure changes needed. Verify manually:
1. Navigate to Dashboard (mode selector) -> sidebar shows 4 mode entries + Go PRO
2. Click "Investigate" -> sidebar shows back link + Activity Logs + Bots & IP Rules + Go PRO
3. Click "<- Shield Security" -> returns to mode selector, sidebar returns to State 1
4. Navigate to any page via WP admin sidebar -> sidebar shows correct mode's items with back link

### P4.5 - Sidebar Follow-Up (Completed)

These were delivered after P4 and before P5 cleanup:

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-410 | ~~Add Security Grades as a direct link in Configure mode sidebar~~ | `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401c | **Done (2026-02-21)**  -  Configure mode inserts `mode-configure-grades` linking to dashboard grades. |
| OM-411 | ~~Optional: Style back link distinctly~~ | `assets/css/components/nav_sidebar_menu.scss`, `src/Modules/Plugin/Lib/NavMenuBuilder.php` | OM-401c | **Done (2026-02-21)**  -  `.mode-back-link` styling added and scoped to sidebar nav link. |

### P5 - Cleanup And Legacy Removal (Trigger-Based)

**Execution status:** Complete (2026-02-21). See archive execution plan for implementation notes.

| ID | Task | Trigger | Files | Done When |
|---|---|---|---|---|
| OM-501 | ~~Unwire legacy dashboard toggle runtime (no deletions)~~ | After P4 ships | `templates/twig/wpadmin/plugin_pages/base_inner_page.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview.twig`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverview.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/BasePluginAdminPage.php`, `src/Modules/Plugin/Lib/AssetsCustomizer.php` | **Done**  -  no active runtime path depends on `dashboard_view_toggle`. |
| OM-502 | ~~Remove deprecated Simple/Advanced classes/templates (hard removal phase)~~ | After OM-501 stabilization | `src/Modules/Plugin/Lib/Dashboard/DashboardViewPreference.php`, `src/ActionRouter/Actions/DashboardViewToggle.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverviewSimple.php`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple_body.twig`, `src/ActionRouter/Constants.php` | **Done**  -  deprecated assets removed and references cleaned. |
| OM-503 | ~~Replace/retire tests tied to old toggle behavior~~ | After OM-502 | `tests/Integration/ActionRouter/DashboardViewToggleIntegrationTest.php`, `tests/Unit/Modules/Plugin/Lib/Dashboard/DashboardViewPreferenceTest.php`, affected assertions in `tests/Integration/ActionRouter/DashboardOverviewRoutingIntegrationTest.php` | **Done**  -  legacy toggle tests removed/replaced. |

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
| P0 | [COMPLETE] Complete | Decisions locked |
| P1 (Phase A) | [COMPLETE] Complete | Meter channel separation done (OM-101-107). See Section 10. |
| P2 | [COMPLETE] Complete | Dashboard renders channel-aware metrics via `PageOperatorModeLanding`. |
| P3 | [COMPLETE] Complete | Mode constants (`PluginNavs`), user preference (`OperatorModePreference`), landing page (`PageOperatorModeLanding` + `operator_mode_landing.twig`) all implemented. |
| P4 | [COMPLETE] Complete | Two-state sidebar implemented (`buildModeSelector`, `buildModeNav`, mode selector fallback) plus WP submenu and breadcrumbs. |
| P4.5 | [COMPLETE] Complete | Configure-grade shortcut and back-link styling delivered (OM-410/OM-411). |
| P5 | [COMPLETE] Complete | Legacy Simple/Advanced toggle artifacts removed (runtime + source + tests). |
| P6-FOUND | [COMPLETE] Complete | **Shared Investigation Table Framework** implemented: `BaseInvestigationTable`, `BaseInvestigationData`, investigation Build/LoadData child classes, `InvestigationTable.js`, `InvestigationTableAction`, and shared Twig partials. |
| P6a | [COMPLETE] Complete | Landing implementation plus lookup-route stabilization confirmed in runtime validation. |
| P6b | [COMPLETE] Complete | By-user implementation plus lookup-route stabilization confirmed in runtime validation. |
| P6-STAB | [COMPLETE] Complete | OM-671..OM-674 closed on 2026-02-25; runtime verification confirmed and submit-path routing is stable. |
| P6c | [COMPLETE] Complete | Dedicated Investigate By IP page delivered with subject header, summary cards, and reused `IpAnalyse\Container`; route and template integration complete. |
| P6d | [COMPLETE] Complete | Dedicated Investigate By Plugin page delivered with shared rail/panel architecture, file status/activity tables, and vulnerabilities panel. |
| P6e | [COMPLETE] Complete | Theme and Core pages delivered; shared `BaseInvestigateAsset` implemented and consumed by plugin/theme pages; core overview + tables integrated. |
| P6f | [COMPLETE] Complete | Cross-subject linking delivered for investigation context while preserving offcanvas IP behavior; canonical investigate URL helpers integrated. |
| P7+ | [NOT STARTED] Not started | Configure/Reports deepening, WP dashboard widget. |

## 7) P6-STAB Closure Record (Runtime Recovery)

### 7.1 Defects Resolved In P6-STAB

| ID | Defect | Severity | Evidence | Resolution |
|---|---|---|---|---|
| OM-DEF-701 | Investigate lookup submit can drop `page/nav/nav_sub` context | Blocker | Live runtime previously showed first request as bare `admin.php?analyse_ip=...` / `admin.php?user_lookup=...` | Resolved 2026-02-25: first-request route context preservation confirmed for landing and by-user flows. |
| OM-DEF-702 | Completion marks were based on slice-level status, not runtime completion | Process blocker | P6a/P6b were previously marked complete while live flow was failing | Resolved 2026-02-25: P6a/P6b completion now reflects runtime-confirmed state. |
| OM-DEF-703 | UI quality/prototype parity gap not tracked as a blocking acceptance condition | High | Current investigate UI diverges materially from plan/prototypes | Open as a post-P6 investigate UI quality follow-up. |

### 7.2 Stabilization Tasks (Completed)

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-671 | ~~Reproduce submit-path loss with deterministic captures~~ | Runtime env + docs | OM-DEF-701 | **Done (2026-02-25)** - runtime validation scenarios were executed for landing IP submit, landing user submit, and by-user submit. |
| OM-672 | ~~Add routing hardening to investigate lookup forms~~ | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`, `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByUser.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_user.twig` | OM-671 | **Done (2026-02-25)** - lookup forms now include immutable fallback route keys (`page`, `nav`, `nav_sub`) while retaining existing action URLs generated by `plugin_urls`. |
| OM-673 | ~~Add regression coverage for lookup route preservation~~ | `tests/Unit/ActionRouter/Render/PageInvestigateLandingBehaviorTest.php`, `tests/Unit/ActionRouter/Render/PageInvestigateByUserBehaviorTest.php`, `tests/Integration/ActionRouter/InvestigateByUserPageIntegrationTest.php`, `tests/Integration/ActionRouter/InvestigateLandingPageIntegrationTest.php`, `tests/Integration/ActionRouter/Support/LookupRouteFormAssertions.php` | OM-672 | **Done (2026-02-25)** - unit + integration assertions validate route-preservation contract in rendered forms without brittle full-markup snapshots. |
| OM-674 | ~~Runtime verification pass and evidence attachment~~ | Runtime env + docs | OM-673 | **Done (2026-02-25)** - runtime verification confirmed first request includes full route params for all 3 lookup flows. Evidence attachment waived by maintainer confirmation. |

### 7.3 Pseudocode Direction For OM-672

```php
// For each lookup form contract (landing + by-user):
$form.action = plugin_urls->adminTopNav('activity', $targetSubnav);

// Always include fallback hidden GET keys:
<input type="hidden" name="page" value="icwp-wpsf-plugin" />
<input type="hidden" name="nav" value="activity" />
<input type="hidden" name="nav_sub" value="$targetSubnav" />

// Keep lookup field as-is:
<input name="analyse_ip" ... /> or <input name="user_lookup" ... />
```

Rationale:
1. If action URL is altered/blanked in any runtime path, hidden keys preserve route context.
2. The first request must remain route-resolved and never degrade to bare `admin.php?...`.

### 7.4 Agent Pickup Pack (Historical Reference)

Use this as historical context if runtime route behavior regresses.

**Reproduction checklist (live admin):**
1. Navigate to Investigate landing (`nav=activity&nav_sub=overview`).
2. In DevTools Network, clear existing requests and preserve log.
3. Submit **Investigate IP** with any valid test IP.
4. Record:
   - clicked control
   - first request URL
   - final URL after load/redirect
5. Repeat for:
   - landing **Investigate User**
   - **By User** page **Load User Context**

**Source files to inspect first (no assumptions):**
1. `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`
2. `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByUser.php`
3. `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig`
4. `templates/twig/wpadmin/plugin_pages/inner/investigate_by_user.twig`
5. `src/Controller/Plugin/PluginURLs.php`
6. `src/Controller/Plugin/PluginNavs.php`
7. `src/ActionRouter/CaptureRedirects.php`

**Evidence template (optional):**
1. Environment: URL + plugin version + date/time.
2. Scenario: landing IP / landing user / by-user.
3. Input value used.
4. First request URL (exact).
5. Final URL (exact).
6. Pass/Fail against `page/nav/nav_sub` preservation.
7. Screenshot of request row + request details panel.

**Stop-ship rule for Investigate:**
Rule satisfied on 2026-02-25. P6a/P6b/P6-STAB/P6c/P6d/P6e/P6f are closed.

### 7.5 Code-Slice Evidence (2026-02-25)
1. Route hardening implemented in investigate landing/by-user render contracts and templates:
   - `PageInvestigateLanding.php`, `PageInvestigateByUser.php`
   - `investigate_landing.twig`, `investigate_by_user.twig`
2. Targeted unit verification passed:
   - `composer test:unit -- tests/Unit/ActionRouter/Render/PageInvestigateLandingBehaviorTest.php tests/Unit/ActionRouter/Render/PageInvestigateByUserBehaviorTest.php`
   - Result: `OK (12 tests, 61 assertions)`.
3. Additional focused unit suite passed:
   - `composer test:unit -- tests/Unit/ActionRouter/Render tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php tests/Unit/Utilities/Navigation/BuildBreadCrumbsOperatorModesTest.php tests/Unit/ActionRouter/InvestigationTableActionTest.php tests/Unit/Tables/Investigation tests/Unit/ActionRouter/TableActionsFailureEnvelopeTest.php`
   - Result: `OK (22 tests, 142 assertions)`.
4. Integration coverage expanded:
   - `InvestigateByUserPageIntegrationTest.php` (route-preservation assertion added)
   - `InvestigateLandingPageIntegrationTest.php` (new landing route-preservation assertions)
   - shared helpers consolidated in `Support/LookupRouteFormAssertions.php`.
5. Integration command in this workspace:
   - `composer test:integration -- tests/Integration/ActionRouter/InvestigateByUserPageIntegrationTest.php tests/Integration/ActionRouter/InvestigateLandingPageIntegrationTest.php`
   - Result: skipped because WordPress integration environment is not available.

## 8) P6c-f Completion Record
P6-STAB and P6 foundation were used as the base for full subject-page delivery.

Completion summary:
1. Dedicated pages are live for `activity/by_ip`, `activity/by_plugin`, `activity/by_theme`, and `activity/by_core`.
2. Transitional landing-route handling for `by_ip/by_plugin/by_theme/by_core` was removed after dedicated handlers were introduced.
3. Activity table subject support now includes `plugin/theme/core` with scoped subject wheres and registry validation.
4. Investigation-context cross-linking is in place (investigate user links, investigate IP deep-links, plugin/theme links in activity rows) without changing non-investigate table contexts.
5. Targeted unit suites pass for routing, contracts, where logic, and new page behavior; integration tests for the new pages/actions are present and skip in environments without the WP integration harness.

## 9) Tracking Format

Use:

`[ ] OM-### - owner - branch - status note - date`

Example:

`[ ] OM-104 - pgoodchild - feat/operator-meters-phase-a - in progress: channel-aware cache keying added, unit tests pending - 2026-02-20`

## 10) Phase A Execution Status (Implemented)

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

## 11) P6  -  Investigate Mode Implementation

**Date added:** 2026-02-22
**Prototype reference:** `docs/OperatorModes/investigate-mode/` (4 interactive HTML files)
**Plan reference:** Section 11 (UI Spec) + Section 12 (Implementation Architecture) of `Shield-Operator-Modes-Plan.md`

**Before starting ANY task in this section, implementors MUST:**
1. Read **Section 12 of the plan document** in full  -  it maps every reusable component in the plugin and gives explicit directives on what to extend vs. create
2. Read **Section 11** for UI specifications, tab definitions, and data sources
3. Open and review ALL prototype HTML files in `docs/OperatorModes/investigate-mode/` in a browser
4. Read the existing classes listed in Section 12.1's component inventory  -  understand the class hierarchy before writing code

**Guiding principles:**
- **DRY (Don't Repeat Yourself).** Every table, every link, every timestamp is rendered by a shared component. If you're writing timestamp formatting logic, stop  -  `BaseBuildTableData::getColumnContent_Date()` already does it. If you're generating an IP link, stop  -  `BaseBuildTableData::getColumnContent_LinkedIP()` already does it.
- **Extend, don't duplicate.** The existing DataTable infrastructure (`Build\Base`, `BaseBuildTableData`, `ShieldTableBase.js`) is battle-tested with 50,000+ installs. Investigation tables are narrower versions of full-page tables  -  create child classes that inherit the heavy lifting and only override what's different (subject filter, column selection, SearchPane removal).
- **One framework, all tables.** A change to `BaseInvestigationTable` or `InvestigationTable.js` must apply to every investigation table across every subject page. This is the test: "if I change the page size from 15 to 25 in one place, does it change everywhere?" The answer must be yes.

### P6-FOUNDATION  -  Shared Investigation Table Framework

> **This phase MUST be completed before ANY tab implementation (OM-611+).** It creates the shared infrastructure all investigation tables depend on. See Plan Section 12.2 for full specification.

**Implementation status (2026-02-25):** [COMPLETE] Complete in code and fully consumed by landing, by-user, by-ip, by-plugin, by-theme, and by-core pages.

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-600a | Create `BaseInvestigationTable` (Build layer) | `src/Tables/DataTables/Build/Investigation/BaseInvestigationTable.php` | P4 complete | Extends `Build\Base`. Adds `$subjectType`/`$subjectId` properties and `setSubject()` setter. Overrides `getSearchPanesData()` -> returns `[]` (no SearchPanes by default). Adds `getSubjectFilterColumns(): array` method  -  columns to hide because they're redundant for the subject (e.g., user column when investigating a user). `getColumnsToDisplay()` calls parent then removes subject filter columns. See Plan Section 12.2 for pseudocode. |
| OM-600b | Create `BaseInvestigationData` (LoadData layer) | `src/Tables/DataTables/LoadData/Investigation/BaseInvestigationData.php` | OM-600a | Extends `BaseBuildTableData`. Adds `$subjectType`/`$subjectId` properties. Overrides `buildWheresFromSearchParams()` to always inject subject filter via abstract `getSubjectWheres(): array`. Keeps strict `getSearchPanesDataBuilder()` return contracts and disables SearchPanes in investigation context via empty-array behavior. Child classes implement `getSubjectWheres()` for their specific subject type. **Critical:** inherits `getColumnContent_Date()`, `getColumnContent_LinkedIP()`, `getUserHref()` from parent  -  these are the shared rendering utilities for timestamps, IPs, and user links. |
| OM-600c | Create investigation-specific Build children | `src/Tables/DataTables/Build/Investigation/ForActivityLog.php`, `ForTraffic.php`, `ForSessions.php`, `ForFileScanResults.php` | OM-600a | Each extends `BaseInvestigationTable`. Column definitions mirror their parent full-page equivalents (`Build\ForActivityLog`, `Build\ForTraffic`, `Build\ForSessions`, `Build\Scans\ForPluginTheme`) but with SearchPanes disabled and subject-redundant columns removed. **Reuse** column definitions from the parent classes  -  copy the `getColumnDefs()` return value and adjust, or call `parent::getColumnDefs()` and filter. |
| OM-600d | Create investigation-specific LoadData children | `src/Tables/DataTables/LoadData/Investigation/BuildActivityLogData.php`, `BuildTrafficData.php`, `BuildSessionsData.php`, `BuildFileScanResultsData.php` | OM-600b | Each extends `BaseInvestigationData`. Implements `getSubjectWheres()` for its subject type. **Delegates row building** to the same logic as the corresponding full-page loader (e.g., `BuildActivityLogTableData::buildTableRowsFromRawRecords()`). Option: extract row-building into a trait or call a shared static method so both the full-page loader and investigation loader share the same row rendering code. |
| OM-600e | Create `InvestigationTable.js` | `assets/js/components/tables/InvestigationTable.js` | OM-600a | Extends `ShieldTableBase`. Overrides `getDefaultDatatableConfig()`: `dom: 'frtip'` (text search + table + info + pagination  -  no SearchPanes, no buttons), `pageLength: 15`, `select: false`. Passes `table_type`, `subject_type`, and `subject_id` with every AJAX request. **One JS class for all investigation tables**  -  a change here applies everywhere. |
| OM-600f | Create `InvestigationTableAction` AJAX handler | `src/ActionRouter/Actions/InvestigationTableAction.php` | OM-600d | Handles `retrieve_table_data` sub-action. Receives `subject_type`, `subject_id`, and `table_type` in action data. Routes to the correct `BuildInvestigation*Data` class. Pattern: mirror existing `ActivityLogTableAction` but dispatches based on `table_type` parameter. |
| OM-600g | Create shared Twig partials | `templates/twig/wpadmin/components/investigate/subject_header.twig`, `templates/twig/wpadmin/components/investigate/table_container.twig` | None | `subject_header.twig`: Reusable subject header bar (avatar, name, meta, status pills, "Change" button). Accepts `subject` data object. `table_container.twig`: Wraps a DataTable `<table>` element with config data attributes + panel heading + optional "Full Log" link. Used inside every tab pane. |

### P6a  -  Investigate Landing Page

**Implementation status (2026-02-25):** [COMPLETE] Runtime-confirmed (stabilization closed).

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-601 | ~~Refactor existing `PageInvestigateLanding` with subject selector grid~~ | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig` | P4 complete | **Done (2026-02-24)**  -  landing handler refactored to subject-driven contract (`active_subject`, `input`, `plugin_options`, `theme_options`), active-subject precedence implemented, inline IP analysis rendering removed, by-user query key contract preserved. Plugin/theme options are sourced from existing service wrappers (`Services::WpPlugins()->getPluginsAsVo()`, `Services::WpThemes()->getThemesAsVo()`). |
| OM-602 | ~~Implement lookup panels with autocomplete/dropdown~~ | Same as OM-601 + JS | OM-601 | **Done (2026-02-24)**  -  template replaced with Bootstrap tab subject cards and required lookup/direct-link panels (users, IPs, plugins, themes, core, requests, activity). No custom JS component and no new AJAX endpoint introduced. |
| OM-603 | ~~Add quick-tools strip below lookup panels~~ | Same as OM-601 | OM-601 | **Done (2026-02-24)**  -  persistent quick-tools strip implemented with existing routes: Activity Log, HTTP Request Log, Live HTTP Log, IP Rules. |
| OM-604 | ~~Register NAV constants for investigation sub-navs~~ | `src/Controller/Plugin/PluginNavs.php` | OM-601 | **Done (2026-02-24)**  -  added `by_plugin`, `by_theme`, `by_core` activity sub-nav constants. **Updated (2026-02-25):** dedicated page handlers now back `by_ip/by_plugin/by_theme/by_core`, and only `activity/overview` remains landing-routed for breadcrumbs. |

P6a validation evidence (2026-02-24):
1. Fragile source-string test removed: `tests/Unit/InvestigateByIpLandingContractTest.php`.
2. New behavior-focused landing test added: `tests/Unit/ActionRouter/Render/PageInvestigateLandingBehaviorTest.php`.
3. Route coverage extended:
   - `tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php`
   - `tests/Unit/Utilities/Navigation/BuildBreadCrumbsOperatorModesTest.php`
4. Targeted unit test runs passed for all three files above.
5. Runtime confirmation (2026-02-25): landing submit-path route preservation is confirmed working for first request (`page/nav/nav_sub` retained).
6. Stabilization code/test slice (2026-02-25): lookup route-preservation hardening landed in `PageInvestigateLanding.php` + `investigate_landing.twig`; behavior tests assert `lookup_route` contract and lookup-panel `subnav_hint` enforcement.

### P6b  -  Investigate User

**Implementation status (2026-02-25):** [COMPLETE] Runtime-confirmed (stabilization closed).

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-611 | ~~Refactor existing `PageInvestigateByUser` with rail+panel layout~~ | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByUser.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_user.twig` | OM-604, **OM-600a-f** | **Done (2026-02-24)**  -  existing page/template refactored in place to rail+panel layout using shared tabs and investigation table container partials. |
| OM-612 | ~~Validate and use `FindSessions::byUser(int $userId)` method~~ | `src/Modules/UserManagement/Lib/Session/FindSessions.php` | None | **Done (2026-02-24)**  -  existing method validated and reused in by-user aggregation path (`buildSessions()`) without introducing alternate session lookup logic. |
| OM-613 | ~~Wire Sessions tab through investigation DataTable~~ | `Investigation\ForSessions`, `Investigation\BuildSessionsData` | **OM-600c, OM-600d**, OM-612 | **Done (2026-02-24)**  -  Sessions tab now renders through shared investigation DataTable contract and AJAX action. |
| OM-614 | ~~Wire Activity tab through investigation DataTable~~ | `Investigation\ForActivityLog`, `Investigation\BuildActivityLogData` | **OM-600c, OM-600d** | **Done (2026-02-24)**  -  Activity tab now renders through shared investigation DataTable contract; full-log href includes `search=user_id:{uid}` prefilter token. |
| OM-615 | ~~Wire Requests tab through investigation DataTable~~ | `Investigation\ForTraffic`, `Investigation\BuildTrafficData` | **OM-600c, OM-600d** | **Done (2026-02-24)**  -  Requests tab now renders through shared investigation DataTable contract; full-log href includes `search=user_id:{uid}` prefilter token. |
| OM-616 | ~~Implement IP Addresses tab (card grid, not DataTable)~~ | Same as OM-611 | OM-611 | **Done (2026-02-24)**  -  IP Addresses tab is server-rendered card grid with status/count/last-seen payload from existing by-user arrays; no parallel table system added. |

P6b validation evidence (2026-02-24):
1. New by-user behavior unit test: `tests/Unit/ActionRouter/Render/PageInvestigateByUserBehaviorTest.php`.
2. New by-user integration coverage: `tests/Integration/ActionRouter/InvestigateByUserPageIntegrationTest.php`.
3. Targeted unit run passed:
   `composer test:unit -- tests/Unit/ActionRouter/Render/PageInvestigateByUserBehaviorTest.php tests/Unit/Tables/Investigation tests/Unit/ActionRouter/InvestigationTableActionTest.php tests/Unit/ActionRouter/TableActionsFailureEnvelopeTest.php`
   => `OK (4 tests, 59 assertions)`.
4. Targeted integration command in this workspace:
   `composer test:integration -- tests/Integration/ActionRouter/InvestigateByUserPageIntegrationTest.php`
   => skipped because WordPress integration environment is not available.
5. Runtime confirmation (2026-02-25): by-user submit-path route preservation is confirmed working for first request (`page/nav/nav_sub` retained).
6. Stabilization code/test slice (2026-02-25): by-user lookup route-preservation hardening landed in `PageInvestigateByUser.php` + `investigate_by_user.twig`; integration route-contract assertions were added and shared via `tests/Integration/ActionRouter/Support/LookupRouteFormAssertions.php`.

### P6c  -  Investigate IP Address

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-621 | ~~Create `PageInvestigateByIp` wrapping existing `IpAnalyse\Container`~~ | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByIp.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_ip.twig` | OM-604 | **Done (2026-02-25)**  -  dedicated by-ip page renders subject header + summary cards and reuses `IpAnalyse\Container` via action-router render. |
| OM-622 | ~~Add "Change IP" button linking back to landing~~ | Same as OM-621 | OM-621 | **Done (2026-02-25)**  -  by-ip subject header includes `change_href` to canonical investigate-by-ip route and landing back-link remains available. |

### P6d  -  Investigate Plugin

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-631 | ~~Create `PageInvestigateByPlugin` with 4-tab rail+panel~~ | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByPlugin.php`, `templates/twig/wpadmin/plugin_pages/inner/investigate_by_plugin.twig` | OM-604, **OM-600a-f** | **Done (2026-02-25)**  -  dedicated by-plugin page delivered with shared rail/panel structure and table contracts. |
| OM-632 | ~~Implement Overview tab~~ | Same as OM-631 | OM-631 | **Done (2026-02-25)**  -  overview reuses `PluginThemesBase::buildPluginData()` output through shared asset base helper. |
| OM-633 | ~~Implement File Status tab via investigation DataTable~~ | Same as OM-631, `Investigation\ForFileScanResults`, `Investigation\BuildFileScanResultsData` | **OM-600c, OM-600d** | **Done (2026-02-25)**  -  file status tab uses shared investigation table container and action contracts for plugin subject scope. |
| OM-634 | ~~Implement Vulnerabilities tab (server-side card list)~~ | Same as OM-631 | OM-631 | **Done (2026-02-25)**  -  vulnerabilities panel is server-rendered from runtime WPV display results with external lookup link reuse. |
| OM-635 | ~~Implement Activity tab via investigation DataTable~~ | Same as OM-631, `Investigation\ForActivityLog`, `Investigation\BuildActivityLogData` | **OM-600c, OM-600d** | **Done (2026-02-25)**  -  activity tab works via shared investigation action pipeline with plugin subject support in registry/resolver/wheres. |

### P6e  -  Investigate Theme + WordPress Core

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-641 | ~~Create `BaseInvestigateAsset` shared by Plugin + Theme~~ | `src/ActionRouter/Actions/Render/PluginAdminPages/BaseInvestigateAsset.php` | OM-631 | **Done (2026-02-25)**  -  shared base added and consumed by plugin/theme pages for lookup resolution, tabs, tables, summary cards, and vulnerabilities panel data. |
| OM-642 | ~~Create WordPress Core investigation page~~ | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByCore.php` | OM-604, **OM-600c, OM-600d** | **Done (2026-02-25)**  -  dedicated by-core page delivered with overview, file-status table, and activity table. |

### P6f  -  Cross-subject linking

> **These tasks should be inherently satisfied** if the cross-cutting rules in Plan Section 12.5 are followed. Every IP link uses `getColumnContent_LinkedIP()` which already generates linkable IPs. Every user link uses `getUserHref()`. The tasks below are verification tasks.

| ID | Task | Files | Depends On | Done When |
|---|---|---|---|---|
| OM-651 | ~~Verify all IP address cells link to investigate-ip~~ | All investigation DataTable `buildTableRowsFromRawRecords()` methods | OM-621 | **Done (2026-02-25)**  -  investigation-context IP links retain offcanvas behavior and include investigate deep-link icon without changing non-investigate contexts. |
| OM-652 | ~~Verify all username cells link to investigate-user~~ | All investigation DataTable row builders | OM-611 | **Done (2026-02-25)**  -  investigation-context user links route to canonical investigate-by-user URL. |
| OM-653 | ~~Wire plugin-related activity events to investigate-plugin~~ | `Investigation\BuildActivityLogData` row builder | OM-631 | **Done (2026-02-25)**  -  plugin/theme activity rows include investigate links in investigation-context activity table source. |

## 12) Hard-Removal Tasks (Completed)

These tasks were deferred behind OM-501 and have now been executed (2026-02-21). This section is retained as a completion record.

| ID | Task | Files | Done When |
|---|---|---|---|
| OM-504 | ~~Remove JS bootstrap wiring for legacy toggle~~ | `assets/js/app/AppMain.js`, `assets/js/components/general/DashboardViewToggle.js` | **Done**  -  `DashboardViewToggle` bootstrap removed and legacy class deleted. |
| OM-505 | ~~Remove legacy action/preference backend artifacts~~ | `src/ActionRouter/Constants.php`, `src/ActionRouter/Actions/DashboardViewToggle.php`, `src/Modules/Plugin/Lib/Dashboard/DashboardViewPreference.php` | **Done**  -  no runtime contract remains for `dashboard_view_toggle` / `shield_dashboard_view`. |
| OM-506 | ~~Remove deprecated simple dashboard renderer/templates~~ | `src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverviewSimple.php`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple.twig`, `templates/twig/wpadmin/plugin_pages/inner/dashboard_overview_simple_body.twig` | **Done**  -  deprecated simple-render path removed. |
| OM-507 | ~~Remove obsolete toggle/panel CSS~~ | `assets/css/plugin-main.scss`, `assets/css/shield/dashboard.scss` | **Done**  -  obsolete toggle/panel selectors removed. |
| OM-508 | ~~Migrate/remove legacy toggle tests~~ | `tests/Integration/ActionRouter/DashboardViewToggleIntegrationTest.php`, `tests/Unit/Modules/Plugin/Lib/Dashboard/DashboardViewPreferenceTest.php`, `tests/Integration/ActionRouter/DashboardOverviewRoutingIntegrationTest.php` | **Done**  -  legacy toggle test coverage migrated/removed. |

Deferred execution notes:
1. Do not add PHPCS to these tasks.
2. Integration tests are not required for this cleanup pass.
3. Preserve existing meter severity/traffic logic (`BuildMeter::trafficFromPercentage()` and queue `good|warning|critical`) without introducing new fallback behavior.
