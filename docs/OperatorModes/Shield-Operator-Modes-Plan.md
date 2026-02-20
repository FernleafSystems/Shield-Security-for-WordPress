# Shield Security — Operator Modes Plan

**Date:** 20 February 2026 | **Plugin:** 21.2.2 | **Author:** Paul Goodchild / Fernleaf Systems

---

## 1. The Problem

Shield currently presents a flat dump of everything at once — 9 sidebar nav items, each with sub-items, totalling 25+ pages. When a user opens Shield they see a dashboard crammed with charts, IP tables, activity logs, and scan strips, none of which helps them answer the question they actually came in with.

The earlier Phase 1 "Simple vs Advanced" toggle doesn't solve this. "Simple" implies you're missing something. Nobody wants the lesser version. The real issue isn't complexity — it's that there's no purpose-driven entry point.

### What users actually come to Shield to do

Every time someone opens Shield, they have one of four intents:

1. **"Do I need to fix something?"** — Check for and resolve urgent issues.
2. **"What happened with X?"** — Investigate a specific user, IP, plugin, or event.
3. **"How is my security configured?"** — Review or adjust protection settings.
4. **"What has Shield been doing?"** — View reports, trends, and summaries.

The plugin should be organised around these four intents, not around feature categories.

---

## 2. Operator Modes

Four modes, each with its own sidebar navigation and dedicated landing:

| Mode | Slug | Accent Colour | Intent |
|---|---|---|---|
| **Actions Queue** | `actions` | Critical (`#c62f3e`) | Resolve urgent issues — scan findings, pending repairs, maintenance |
| **Investigate** | `investigate` | Info (`#0ea8c7`) | Dig into a specific user, IP, plugin, or review logs |
| **Configure** | `configure` | Good (`#008000`) | Review/adjust security posture, zones, grades, rules |
| **Reports** | `reports` | Warning (`#edb41d`) | View reports, statistics, trends, alert settings |

Actions Queue and Investigate are distinct modes despite both dealing with security events. Actions is reactive triage ("fix these things"), while Investigate is proactive analysis ("tell me about X"). Users are in different mental states for each, and combining them would clutter the Investigate landing.

When the Actions Queue is empty, its card on the mode selector landing shows a clear green "all clear" state with a summary line (e.g. "No actions pending — last scan 2 hours ago") so the user gets reassurance without entering the mode.

### Mode selector landing page

When a user opens Shield (Dashboard nav), they land on the **mode selector** — a single page showing all four modes with a brief explanation of each. This page also displays a compact security posture score indicator (see Section 3.4) so users get an at-a-glance answer to "am I secure?" without entering a mode.

### Default page preference

The mode selector is the default landing page. Users can optionally set a preferred default via a single control on the mode selector page: "Always start in: [Mode Selector ▾ / Investigate / Configure / Reports / Actions Queue]". This is stored in `wp_usermeta` and is a mutually exclusive single choice — selecting a new default replaces the previous one. Users can also jump directly into any mode from the WordPress admin sidebar without changing their default.

### Returning to mode selection

The existing breadcrumb system provides the "back" path. The first breadcrumb is always "Shield Security" which links to the mode selector. Within a mode, breadcrumbs read: `Shield Security » Investigate » Activity Log`.

---

## 3. Current State Audit

### 3.1 Phase 1 work to deprecate

The following Phase 1 artefacts become obsolete under operator modes:

| Artefact | Status | Action |
|---|---|---|
| `DashboardViewPreference.php` | Exists at `src/Modules/Plugin/Lib/Dashboard/` | Replace with `OperatorModePreference` |
| `DashboardViewToggle.php` | Exists at `src/ActionRouter/Actions/` | Replace with `OperatorModeSwitch` action |
| `PageDashboardOverviewSimple.php` | Exists at `src/ActionRouter/Actions/Render/PluginAdminPages/` | Remove — its role is absorbed by mode landing + Actions Queue mode |
| `dashboard_overview_simple.twig` | Exists at `templates/twig/wpadmin/plugin_pages/inner/` | Remove |
| Simple/Advanced toggle in `base_inner_page.twig` | Exists | Remove toggle; replace with breadcrumb-based mode navigation |
| `NeedsAttentionQueue.php` | Exists at `src/.../Widgets/` | **Keep** — moves into Actions Queue mode |
| `AttentionItemsProvider.php` | Exists at `src/.../Widgets/` | **Keep** — data source for Actions Queue mode |
| `needs_attention_queue.twig` | Exists at `templates/.../widget/` | **Keep** — used by Actions Queue mode |

### 3.2 Current navigation structure

Source: `NavMenuBuilder.php`, `PluginNavs.php`

```
Current plugin sidebar (NavMenuBuilder::build):
├── Dashboard          → PageDashboardOverview / PageDashboardMeters
├── Security Zones     → 8 dynamic zone pages
├── Bots & IP Rules    → PageIpRulesTable
├── Scans              → PageScansResults, PageScansRun
├── Activity Logs      → PageActivityLogTable, PageTrafficLogTable, PageTrafficLogLive
├── Custom Rules       → PageRulesManage, PageRulesBuild, PageRulesSummary
├── Tools              → Sessions, Lockdown, Import/Export, WhiteLabel, LoginHide, Integrations, Wizard, Docs, Debug
├── Reports            → PageReports
└── Go PRO / License   → PageLicense
```

WordPress admin sidebar (registered in `PluginAdminPageHandler::addSubMenuItems`):

```
Shield Security (top-level)
├── Security Dashboard
├── Security Zones
├── IP Manager
├── Scans
├── Activity
├── Traffic
├── Custom Rules
├── Reports
└── Go PRO / License
```

### 3.3 Current components mapped to modes

| Existing Page/Feature | Operator Mode | Notes |
|---|---|---|
| NeedsAttentionQueue widget | **Actions** | Core of the Actions mode |
| AttentionItemsProvider (scan results, maintenance items) | **Actions** | Data layer for action items |
| Scan Results (PageScansResults) | **Actions** | Review/fix scan findings |
| Scan Run (PageScansRun) | **Actions** | Trigger a scan to check for issues |
| Dashboard Meters / Grades (PageDashboardMeters) | **Configure** | Security posture grades (config channel only — see Section 3.4) |
| Security Zones (8 zones, 48 components) | **Configure** | Zone configuration |
| Custom Rules (Manage, Build, Summary) | **Configure** | Rule configuration |
| Import/Export | **Configure** | Settings management |
| Site Lockdown | **Configure** | Emergency tool |
| Activity Log (PageActivityLogTable) | **Investigate** | Event investigation |
| Traffic Log (PageTrafficLogTable) | **Investigate** | HTTP request investigation |
| Live Traffic (PageTrafficLogLive) | **Investigate** | Real-time monitoring |
| IP Rules (PageIpRulesTable) | **Investigate** | IP investigation/management |
| IP Analysis (IpAnalyse/Container) | **Investigate** | Per-IP deep dive (5 tabs, already built) |
| User Sessions (PageUserSessions) | **Investigate** | Session investigation |
| Reports (PageReports) | **Reports** | Security reports |
| Dashboard Charts (ChartsSummary) | **Reports** | Trend data visualisation |
| Alert/Reporting config | **Reports** | Configure what gets reported |
| License, Docs, Debug, Wizard, WhiteLabel, LoginHide, Integrations | **Cross-cutting** | Accessible from all modes or from a settings/tools sub-section |

### 3.4 Meter Channel Separation — Critical Design Issue

**⚠ This must be resolved before operator modes ship.**

The current meter system conflates two fundamentally different concerns into one score:

| Channel | What it measures | Source | Example |
|---|---|---|---|
| **Configuration Posture** | "Is this feature enabled and properly configured?" | 80+ components via `testIfProtected()` in `MeterAnalysis/Component/` | Is IP blocking enabled? Is 2FA configured? Are scans scheduled? |
| **Action Items** | "Something happened that needs fixing" | `AttentionItemsProvider` → scan result counts (`Counts.php`) + `MeterOverallConfig` maintenance component slugs | 3 vulnerable plugins, 2 modified core files, 5 abandoned plugins, pending WordPress updates |

Today, the `MeterSummary` hero score and the grades page blend both channels. A user can have a perfectly configured site (all features enabled) but see a "C" grade because scan results found 5 vulnerable plugins. Conversely, a user with zero scan findings but half their features disabled also gets a "C." The single score makes it impossible to distinguish "your config needs work" from "something happened that you need to deal with."

**Under operator modes, these must be two separate scores:**

| Channel | Displayed in | Score represents |
|---|---|---|
| Configuration Posture Score | **Configure** mode landing + mode selector card | How well your security features are set up. Purely configuration-driven. Does not change unless you change settings. |
| Action Items Count/Status | **Actions Queue** mode landing + mode selector card | Issues requiring attention right now. Changes based on scan results, updates, and site events. |

#### Technical impact of separation

The separation affects several interconnected systems:

**Scoring engine (`BuildMeter.php`, `Handler.php`):**
- `MeterSummary` currently aggregates all components including maintenance items (`wp_updates`, `wp_plugins_updates`, `wp_themes_updates`, `wp_plugins_inactive`, `wp_themes_inactive`) **and scan result components** (`ScanResultsWcf`, `ScanResultsWpv`, `ScanResultsMal`, `ScanResultsPtg`, `ScanResultsApc`).
- Both maintenance components and scan result components must be excluded from the configuration posture score and routed to the action items channel. Scan result components (via `ScanResultsBase`) are count-driven (`countResults()`, dynamic `weight()`) — they measure "something happened" not "something is configured."
- The `MeterOverallConfig` meter already distinguishes maintenance components via the `maintenance_component_slugs` array in its `buildComponents()` method. This is the natural split point for maintenance items. For scan results, `ScanResultsBase` provides a shared parent class where the channel override can be applied once.

**Grades page (`PageDashboardMeters`):**
- Currently shows all meters including components that are really action items.
- Under Configure mode, this page should only show configuration posture meters.
- Maintenance-related grades (pending updates, inactive plugins) should not appear here.

**Hero meter / security score:**
- The single percentage score users have been watching will change when action items are removed from the calculation.
- A site currently showing "B — 75%" might jump to "A — 91%" because vulnerable plugins were dragging the score down.
- **Migration strategy needed:** Either recalibrate grade thresholds so the new config-only score lands in a similar range, or clearly communicate the change ("your score now reflects configuration only; action items are tracked separately").

**WP dashboard widget (`WpDashboardSummary.php`):**
- Currently shows a single progress bar based on `MeterSummary`.
- Needs updating to show two indicators: configuration score + action item count.

**Email reports and ShieldNET:**
- Any reports or external integrations that reference the overall score will need updating.
- ShieldNET reputation scoring may need to factor in both channels.

**`AttentionItemsProvider.php` already partially implements the split:**
- `buildScanItems()` → scan result counts (action channel)
- `buildMaintenanceItems()` → pulls from `MeterOverallConfig` components matching maintenance slugs (action channel)
- `buildSummaryWarningItems()` → meter warnings (cross-channel)

The data sources are already separate in code. The work is in the display layer and score calculation, not in data collection.

#### Approach

1. Tag each meter component with a `channel` property: `config` or `action`. Override to `action` in maintenance components and in `ScanResultsBase` (which covers all `ScanResults*` subclasses).
2. `MeterSummary` accepts a channel filter. Default (no filter) returns the combined score for backward compatibility during migration.
3. Configure mode calls `MeterSummary` with `channel: config`. Actions mode uses `AttentionItemsProvider` (already action-channel only).
4. The mode selector landing shows both: config score on the Configure card, action count on the Actions card.
5. Recalibrate grade thresholds for the config-only score if testing shows significant drift from users' expectations.
6. **Zero-weight safety:** `BuildMeter.php` computes percentages using `$totalWeight` without a zero guard. When channel filtering removes all components, division by zero will occur. Add a hard guard: when `totalWeight <= 0`, return stable zeroed totals/status.
7. **Channel-aware caching:** `Handler.php` caches built meters by slug only (static `BuiltMeters`). Channel-specific retrieval would collide. Cache key must include channel dimension (e.g. `summary|config`, `summary|action`, `summary|combined`).

---

## 4. Sidebar Navigation Per Mode

The Shield plugin sidebar has two distinct states, controlled by whether the user is on the mode selector page or inside a mode.

### 4.1 Sidebar behaviour — design spec

**State 1 — On the mode selector landing page (`NAV_DASHBOARD` / `SUBNAV_DASHBOARD_OVERVIEW`):**

The sidebar mirrors the four mode cards on the landing page. Each entry is a flat top-level link into that mode's default entry point. No sub-items, no expand/collapse — just four clear choices plus Go PRO.

```
Shield Security
├── ⚡ Actions Queue       → links to defaultEntryForMode(MODE_ACTIONS)
├── 🔍 Investigate         → links to defaultEntryForMode(MODE_INVESTIGATE)
├── ⚙  Configure           → links to defaultEntryForMode(MODE_CONFIGURE)
├── 📊 Reports             → links to defaultEntryForMode(MODE_REPORTS)
─────────
└── Go PRO / License
```

**State 2 — Inside any mode (user has navigated to a page belonging to a mode):**

The sidebar shows that mode's dedicated navigation items with full sub-items (zones, rules sub-pages, etc.). A back link at the top returns to the mode selector. This mirrors the breadcrumb path: `Shield Security » [Mode] » [Page]`.

```
← Shield Security              ← back link to mode selector landing
─────────
[Mode Name]                     ← mode heading (not clickable, or links to mode default entry)
├── [Mode-specific nav items]
├── ...
─────────
└── Go PRO / License
```

### 4.2 Sidebar implementation status (2026-02-20)

The two-state sidebar is now implemented in `NavMenuBuilder::build()`.

Completed in `src/Modules/Plugin/Lib/NavMenuBuilder.php`:
1. `resolveCurrentMode()` now returns `''` for `NAV_DASHBOARD`/empty nav (mode selector state) instead of falling back to `MODE_ACTIONS`.
2. `buildModeSelector()` returns the 4 mode-entry links plus Go PRO/License.
3. `buildModeNav()` prepends a `mode-selector-back` link (`mode-back-link`) and then renders mode-filtered nav items plus Go PRO/License.
4. `allowedNavsForMode()` no longer includes `NAV_DASHBOARD` for any mode.
5. Shared menu normalization was extracted and reused for both states to avoid duplication.

No template change was required in `templates/twig/wpadmin/components/page/nav_sidebar.twig` because it already iterates generic `navbar_menu` item structures.

Outstanding sidebar follow-up (non-blocking for P4 completion):
1. Add Security Grades direct link in Configure mode sidebar (`OM-410`).
2. Optional visual polish for `.mode-back-link` (`OM-411`).
### 4.3 Nav items per mode

These define what `allowedNavsForMode()` returns. The current implementation matches the spec for filtering, but the items below also show the desired sidebar labels (which may differ from the current `title` values in the private methods).

#### Actions Queue sidebar

```
← Shield Security                    ← back link
─────────
Actions Queue
├── Scan Results                      → NAV_SCANS / SUBNAV_SCANS_RESULTS
└── Run Scan                          → NAV_SCANS / SUBNAV_SCANS_RUN
```

Note: The existing `scans()` method already returns these as sub-items. The current `allowedNavsForMode(MODE_ACTIONS)` correctly includes `NAV_SCANS`. The "Overview" landing (NeedsAttentionQueue) is rendered as the mode's default entry page, not as a separate nav item.

#### Investigate sidebar

```
← Shield Security                    ← back link
─────────
Investigate
├── Activity Log                      → NAV_ACTIVITY / SUBNAV_LOGS (existing)
├── HTTP Request Log                  → NAV_TRAFFIC / SUBNAV_LOGS (existing)
├── Live HTTP Log                     → NAV_TRAFFIC / SUBNAV_LIVE (existing)
├── Bots & IP Rules                   → NAV_IPS (existing)
├── By User                           → NEW (P6+ — PageInvestigateByUser)
├── By IP Address                     → NEW (P6+ — wraps IpAnalyse\Container)
└── By Plugin                         → NEW (P6+ — deferred)
```

Note: The current `allowedNavsForMode(MODE_INVESTIGATE)` includes `NAV_ACTIVITY`, `NAV_IPS`, `NAV_TRAFFIC`. The `activity()` method already groups Activity Log, HTTP Request Log, and Live HTTP Log as sub-items under `NAV_ACTIVITY`. This is the current structure and works. The investigation selectors (By User, By IP, By Plugin) are P6+ work and will require new NAV constants and page handlers when built.

#### Configure sidebar

```
← Shield Security                    ← back link
─────────
Configure
├── Security Grades                   → NAV_DASHBOARD / SUBNAV_DASHBOARD_GRADES (config channel only)
├── Security Zones                    → NAV_ZONES (existing — 8 dynamic zone sub-items)
│   ├── Security Admin
│   ├── Firewall
│   ├── Bots & IPs
│   ├── Scans
│   ├── Login
│   ├── Users
│   ├── SPAM
│   └── Headers
├── Custom Rules                      → NAV_RULES (existing — 3 sub-items)
│   ├── Manage
│   ├── New
│   └── Summary
└── Tools                             → NAV_TOOLS (existing — Import/Export, Lockdown, Sessions, etc.)
    ├── User Sessions
    ├── Site Lockdown
    ├── Import/Export
    ├── White Label
    ├── Hide Login
    ├── Integrations
    ├── Guided Setup
    ├── Docs
    └── Debug Info
```

Note: `allowedNavsForMode(MODE_CONFIGURE)` now includes `NAV_ZONES`, `NAV_RULES`, and `NAV_TOOLS` only; `NAV_DASHBOARD` has already been removed and replaced by the back link. Security Grades (`NAV_DASHBOARD / SUBNAV_DASHBOARD_GRADES`) still needs explicit handling as a Configure-mode link (`OM-410`). Option (a) remains simplest: add a direct link item in `buildModeNav()` for Configure.

#### Reports sidebar

```
← Shield Security                    ← back link
─────────
Reports
├── Security Reports                  → NAV_REPORTS / SUBNAV_REPORTS_LIST (existing)
└── Alert Settings                    → future: config for InstantAlerts + Reporting components
```

Note: The current `allowedNavsForMode(MODE_REPORTS)` includes `NAV_REPORTS`. The Reports section is currently thin — just one page. Charts & Trends and Alert Settings are future additions.

### 4.4 Cross-cutting items

These appear at the bottom of the sidebar in all states (both mode selector and inside-mode). Currently, items like Docs, Debug, Guided Setup, White Label, Hide Login, and Integrations live as sub-items under `NAV_TOOLS` in Configure mode. They should remain accessible from Configure mode's Tools section. In other modes, they are not shown in the sidebar — users access them via Configure mode or direct URL. Go PRO / License always appears at the bottom.

---

## 5. WordPress Admin Sidebar

Currently Shield registers 9 submenu items. Under operator modes, this simplifies to:

```
Shield Security (top-level menu)
├── Dashboard (mode selector landing — always goes to selector, regardless of default preference)
├── Actions Queue (direct entry to Actions mode)
├── Investigate (direct entry to Investigate mode)
├── Configure (direct entry to Configure mode)
├── Reports (direct entry to Reports mode)
└── Go PRO / License
```

Clicking a mode in the WP sidebar enters that mode directly without changing the user's default preference. The default preference is only changed via the explicit control on the mode selector page.

---

## 6. User Preference Storage

```php
// User meta key
const META_KEY_DEFAULT_MODE = 'shield_default_operator_mode';

// Valid values (empty string = mode selector landing)
'' | 'actions' | 'investigate' | 'configure' | 'reports'

// Behaviour:
// - Default: empty (show mode selector landing when clicking Shield top-level menu)
// - Set via dropdown on mode selector page: "Always start in: [Mode Selector ▾]"
//   Options: Mode Selector, Investigate, Configure, Reports, Actions Queue
// - Mutually exclusive single choice — selecting a new default replaces the previous
// - WP admin sidebar links always go directly to that mode, ignoring this preference
// - Breadcrumb "Shield Security" link always goes to mode selector, ignoring this preference
```

---

## 7. Investigate Mode — Investigation Tools

The current plugin offers raw log views (Activity, Traffic, IP Rules, Sessions). The Investigate mode wraps these with purpose-driven entry points.

### What exists today

| Tool | Page Class | Capabilities |
|---|---|---|
| Activity Log | `PageActivityLogTable` | DataTable with filters. Events stored in `ActivityLogs` DB. Each record: `event_slug`, `ip`, `rid`, `meta_data`, timestamps. |
| Traffic Log | `PageTrafficLogTable` | DataTable with filters. Request data: path, verb, response code, IP, timestamps. |
| Live Log | `PageTrafficLogLive` | Real-time HTTP request stream. |
| IP Analysis | `IpAnalyse\Container` | 5-tab deep dive on a single IP: General, Bot Signals, Sessions, Activity, Traffic. Already built. |
| User Sessions | `PageUserSessions` | Session list. `FindSessions` class supports `byIP()` and `mostRecent()`. |

### What needs building (investigation selectors)

Three new investigation entry points that wire existing data with a subject filter:

**By User**
- UI: Select a user by ID, username, or email (autocomplete dropdown from `wp_users`)
- Result: A page showing for that user: all activity log entries, all sessions, all IP addresses used, all HTTP requests
- Implementation: New page class that reuses existing DataTable components with pre-set filters. Filter `LoadLogs` by user ID from meta. `FindSessions::byUser()` (new method) queries directly by user ID.

**By IP Address**
- UI: Enter or select an IP address
- Result: Redirect to the existing `IpAnalyse\Container` (already has General, Bot Signals, Sessions, Activity, Traffic tabs)
- Implementation: Mostly a wrapper — the IP Analysis feature already exists. Promote it to a first-class nav item instead of requiring users to click an IP link from another table.

**By Plugin**
- UI: Select an installed plugin from a dropdown
- Result: Activity log entries related to that plugin (activation, updates, file changes), scan results for that plugin's files, vulnerability status
- Implementation: New page class. Filter `LoadLogs` by event slugs related to plugins. Filter scan results by plugin directory path.

### Data layer notes

- `LoadLogs` already supports `wheres[]` for flexible filtering — adding user/IP/plugin filters is additive SQL.
- `IpAnalyse\Container` is already a complete IP investigation tool — just needs a direct nav entry.
- `FindSessions::byIP()` exists. A `byUser()` variant would query directly by user ID.

### Minimum viable Investigate mode for release

The investigation selectors (By User, By IP, By Plugin) are what make operator modes feel like an upgrade rather than a reshuffle. At minimum, the initial release must include:

- **By IP** — essentially free, since `IpAnalyse\Container` already exists
- **By User** — needs building but is achievable with existing data layer
- Existing pages (Activity Log, Traffic Log, Live Log, IP Rules) reorganised into the Investigate sidebar

By Plugin can follow in a subsequent release.

---

## 8. Implementation Steps

### Step 1: Meter Channel Separation

This must happen first because the mode selector landing page needs to display the config score and action count as separate indicators.

**Modify:**

| File | Change |
|---|---|
| `Component/Base.php` | Add `channel()` method returning `config` or `action`. Default: `config`. Add `CHANNEL_CONFIG` and `CHANNEL_ACTION` constants. |
| Maintenance components (`WpUpdates`, `WpPluginsUpdates`, `WpThemesUpdates`, `WpPluginsInactive`, `WpThemesInactive`) | Override `channel()` to return `action`. |
| `ScanResultsBase.php` | Override `channel()` to return `action`. This covers all scan result subclasses (`ScanResultsWcf`, `ScanResultsWpv`, `ScanResultsMal`, `ScanResultsPtg`, `ScanResultsApc`). |
| `MeterSummary` / `MeterOverallConfig` / `MeterBase` | Accept optional channel filter in `buildComponents()`. |
| `BuildMeter.php` | Filter components by channel when building meter data. Add zero-weight guard: return zeroed totals when `$totalWeight <= 0`. |
| `Handler.php` | Add `getMeterByChannel()` convenience method. Make cache channel-aware: cache key includes channel dimension (e.g. `summary|config`). |

**Status (2026-02-20):** ✅ Complete. All changes above have been implemented and tested. See backlog Section 9 for execution details.

**Test:** Verify that the config-only score is within a reasonable range of the old combined score for typical sites. Adjust grade thresholds if needed.

### Step 2: Mode Selector Landing Page

**Create:**

| File | Purpose |
|---|---|
| `src/.../PluginAdminPages/PageOperatorModeLanding.php` | Mode selector page handler — renders 4-mode cards with config score and action count |
| `templates/.../inner/operator_mode_landing.twig` | Mode selector template |
| `src/Modules/Plugin/Lib/OperatorModePreference.php` | User meta storage for default mode preference |

**Modify:**

| File | Change |
|---|---|
| `PageDashboardOverview.php` | Check `OperatorModePreference`. If default set → redirect to that mode. If empty → render mode selector landing. |
| `PluginNavs.php` | Add operator mode constants (`MODE_ACTIONS`, `MODE_INVESTIGATE`, `MODE_CONFIGURE`, `MODE_REPORTS`) |

**Status (2026-02-20):** ✅ Complete. `PageOperatorModeLanding.php`, `operator_mode_landing.twig`, and `OperatorModePreference.php` exist and are functional. Mode constants added to `PluginNavs.php`.

**Cleanup:** None. Additive only.

### Step 3: Mode-Aware Sidebar Navigation

**Status (2026-02-20):** Complete. Two-state sidebar behavior is implemented in `NavMenuBuilder` and WP submenu mode entries are already in place.

**Modify:**

| File | Change |
|---|---|
| `NavMenuBuilder.php` | Completed: two-state sidebar implemented (`resolveCurrentMode()` dashboard handling, `buildModeSelector()`, `buildModeNav()`, and `allowedNavsForMode()` dashboard removal). |
| `PluginAdminPageHandler.php` | Already done — WP submenu registers mode-based items. No further changes needed unless entry point URLs change. |

**Cleanup:** Remove old flat submenu items (Activity, Traffic, etc.) from WP admin sidebar. (Already done.)

### Step 4: Mode Switching & Breadcrumbs

**Status (2026-02-20):** Breadcrumbs (`BuildBreadCrumbs.php`) are ✅ done — mode-aware with Shield Security → Mode → Page structure. `OperatorModeSwitch` action exists. Simple/Advanced toggle has not yet been removed from `base_inner_page.twig` (deferred to P5). The toggle UI is no longer visible but the code path still exists.

**Create:**

| File | Purpose |
|---|---|
| `src/ActionRouter/Actions/OperatorModeSwitch.php` | AJAX action to set default mode preference |

**Modify:**

| File | Change |
|---|---|
| `base_inner_page.twig` | Remove Simple/Advanced toggle. |
| `BuildBreadCrumbs.php` | First breadcrumb = "Shield Security" → mode selector URL. Second = current mode name. |

**Cleanup:** Remove `DashboardViewPreference.php`, `DashboardViewToggle.php`, `PageDashboardOverviewSimple.php`, `dashboard_overview_simple.twig`, Simple/Advanced toggle UI.

### Step 5: Actions Queue Mode

**Status (2026-02-20):** ❌ Not started. The Actions Queue hero exists on the mode selector landing page, but no dedicated Actions Queue mode landing page or sidebar has been built.

**Reuse existing:** `NeedsAttentionQueue.php`, `AttentionItemsProvider.php`, `PageScansResults.php`, `PageScansRun.php`

**Create:**

| File | Purpose |
|---|---|
| `PageActionsQueueLanding.php` | Actions mode landing — action items count/list using action-channel data |
| `actions_queue_landing.twig` | Template |

### Step 6: Investigate Mode

**Status (2026-02-20):** ❌ Not started. Existing pages (Activity Log, Traffic Log, IP Rules) are accessible via the sidebar when in Investigate mode, but no dedicated landing page or investigation selectors (By User, By IP) have been built.

**Create:**

| File | Purpose |
|---|---|
| `PageInvestigateLanding.php` | Investigate mode landing — subject selector UI |
| `investigate_landing.twig` | Template |
| `PageInvestigateByUser.php` | User investigation — aggregates activity, sessions, IPs |
| `investigate_by_user.twig` | Template |

Promote `IpAnalyse\Container` to a first-class Investigate nav item ("By IP Address").

**Modify:**
- `LoadLogs` — add convenience method for filtering by user ID
- `FindSessions` — add `byUser(int $userId)` method

### Step 7: Configure & Reports Modes

**Status (2026-02-20):** ❌ Not started. Existing pages are accessible via sidebar mode filtering, but no dedicated landing pages have been built.

Mostly sidebar reorganisation of existing pages.

**Configure:** Security Grades (config channel only), Security Zones (8), Custom Rules (3), Import/Export, Site Lockdown.

**Reports:** Security Reports, Charts & Trends (relocated from current dashboard), Alert Settings.

**Create landing pages:**

| File | Purpose |
|---|---|
| `PageConfigureLanding.php` | Configure mode landing — config posture score + zone overview |
| `PageReportsLanding.php` | Reports mode landing — recent reports + chart summary |

### Step 8: WP Dashboard Widget Update

**Status (2026-02-20):** ❌ Not started.

**Modify:**

| File | Change |
|---|---|
| `WpDashboardSummary.php` | Show two indicators: config posture score + action items count. Remove IP tables, blog posts, session tables, activity tables. |
| `admin_dashboard_widget.twig` | Simplified template matching the new two-indicator design. |

---

## 9. What Gets Cleaned Up and When

| Step | Removed / Deprecated | Reason |
|---|---|---|
| Step 1 | Nothing | Internal scoring change, backward-compatible API |
| Step 2 | Nothing | Additive — landing page sits alongside existing dashboard |
| Step 3 | Old flat WP admin submenu items | Replaced by mode-based submenu |
| Step 4 | `DashboardViewPreference`, `DashboardViewToggle`, `PageDashboardOverviewSimple`, `dashboard_overview_simple.twig`, Simple/Advanced toggle UI | Superseded by operator mode system |
| Step 5 | Nothing | Reuses existing components |
| Step 6 | Nothing | New investigation pages |
| Step 7 | Old `PageDashboardOverview` chart rendering | Charts move to Reports mode |
| Step 8 | Old WP dashboard widget tables/blog section | Replaced by two-indicator widget |

---

## 10. Open Questions

1. **Cross-cutting tools placement.** Where do Docs, Debug, Wizard, WhiteLabel, LoginHide, and Integrations live? Options: always-visible footer section in sidebar, or nested under Configure as a "Tools" sub-group.

2. **Scans — Actions vs Configure?** `PageScansResults` is naturally Actions (fix findings), but `PageScansRun` could be Configure (set up scan schedules) or Actions (run a scan to check). Current plan: both in Actions.

3. **IP Rules — Investigate vs Configure?** Viewing IP rules is investigation (Investigate), but adding manual bypass/block rules is configuration (Configure). Current plan: Investigate, since the primary use is investigation. Configuration of IP blocking thresholds lives in Security Zones (Configure mode).

4. **Investigate: additional investigation subjects.** Beyond User, IP, Plugin — what about: by WordPress post/page, by WooCommerce order, by time period ("what happened last Tuesday")? Future consideration.

5. **Reports mode depth.** Currently thin — just existing Reports page and relocated charts. Early bulk can come from moving `ChartsSummary` data into a dedicated Charts & Trends page. Future phases: scheduled PDF reports, email digest configuration, comparison reports.

6. **Grade threshold recalibration.** After meter channel separation, test the config-only score across a range of real sites. If the typical score shifts significantly upward (because action items are removed), the grade boundaries (A+: >85, A: >80, B: >70, etc.) may need adjustment so users don't see an unexplained score jump.

7. **ShieldNET and external reporting.** Any external systems consuming the overall score need to be updated to handle two-channel scoring, or continue receiving a combined score for backward compatibility.

---

## 11. Phase A Implementation Notes (2026-02-20)

This note records what was implemented for the first meter-channel foundation slice.

Completed in scope:
1. Added component channel contract and payload channel output.
2. Classified maintenance + scan-result meter components to action channel.
3. Threaded optional channel through meter build/retrieval flow.
4. Added channel-aware cache partitioning while preserving combined/default retrieval behavior.
5. Added divide-by-zero safety for zero-weight filtered sets.
6. Added focused unit tests for classification, channel cache behavior, channel propagation, and zero-weight safety.

Important compatibility detail:
1. Existing combined/default consumer callsites were preserved unchanged.
2. Legacy dashboard toggle flow remains untouched in Phase A.

Validation detail:
1. Unit tests for the new Phase A meter slice pass.
2. WordPress integration tests were not required for this phase and could not be executed in this workspace due missing WP integration environment.

