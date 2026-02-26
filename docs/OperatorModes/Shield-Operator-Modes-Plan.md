# Shield Security — Operator Modes Plan

**Date:** 20 February 2026 | **Last Updated:** 25 February 2026 | **Plugin:** 21.2.2 | **Author:** Paul Goodchild / Fernleaf Systems

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

**Status update (2026-02-22):** This deprecation/cleanup was executed in P5. Legacy Simple/Advanced artifacts listed below have been removed from the active runtime/code paths.

| Artefact | Status | Action |
|---|---|---|
| `DashboardViewPreference.php` | Removed (P5 cleanup) | Replaced by `OperatorModePreference` |
| `DashboardViewToggle.php` | Removed (P5 cleanup) | Replaced by `OperatorModeSwitch` action |
| `PageDashboardOverviewSimple.php` | Removed (P5 cleanup) | Role absorbed by mode landing + Actions Queue mode |
| `dashboard_overview_simple.twig` | Removed (P5 cleanup) | Removed |
| Simple/Advanced toggle in `base_inner_page.twig` | Removed (P5 cleanup) | Replaced with operator-mode navigation/breadcrumb flow |
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

### 4.2 Current implementation gap — what needs to change

**Status update (2026-02-22):** The sidebar gap documented in this section has been closed. `NavMenuBuilder` now implements the two-state sidebar (`buildModeSelector()`, `buildModeNav()`, and dashboard-to-selector mode resolution), with follow-up polish (Configure Security Grades shortcut + back-link styling) delivered. This section is retained as historical design/implementation context.

**Current state of `NavMenuBuilder::build()` (as of 2026-02-20):**

The method builds the full 9-item menu (`dashboard`, `zones`, `ips`, `scans`, `activity`, `rules`, `tools`, `reports`, `gopro`) and then calls `filterMenuForMode()` to whitelist nav slugs per mode. The mode is resolved via `resolveCurrentMode()` which calls `PluginNavs::modeForNav($this->inav())`.

**Problems with the current implementation:**

1. **No mode selector state.** When `inav()` returns `dashboard` (the mode selector landing page), `modeForNav('dashboard')` returns `''` (empty string). `resolveCurrentMode()` falls back to `MODE_ACTIONS`. This means the mode selector landing page shows the Actions Queue sidebar items instead of the four mode entries.

2. **No back link.** When inside a mode, the sidebar has no explicit link back to the mode selector. Users must rely on the breadcrumb "Shield Security" link or the WP admin sidebar "Dashboard" entry.

3. **Dashboard item always included.** Every mode's `allowedNavsForMode()` includes `NAV_DASHBOARD`, but the Dashboard sidebar entry shows as "Dashboard — Security At A Glance" with a speedometer icon, which doesn't communicate "back to mode selector."

4. **Mode label not shown.** The sidebar doesn't display the current mode name as a heading. Users inside Investigate mode see "Activity Logs", "Bots & IP Rules", etc. but nothing that says "Investigate" as a group label.

**Required changes in `NavMenuBuilder`:**

```
// Pseudocode for the updated build() logic:

public function build(): array {
    $mode = $this->resolveCurrentMode();

    if ($mode === '') {
        // STATE 1: Mode selector page — return four mode entry links + gopro
        return $this->buildModeSelector();
    }

    // STATE 2: Inside a mode — return back link + mode nav items + gopro
    return $this->buildModeNav($mode);
}

private function buildModeSelector(): array {
    // Return 4 flat items (no sub-items):
    //   Actions Queue  → PluginNavs::defaultEntryForMode(MODE_ACTIONS)
    //   Investigate    → PluginNavs::defaultEntryForMode(MODE_INVESTIGATE)
    //   Configure      → PluginNavs::defaultEntryForMode(MODE_CONFIGURE)
    //   Reports        → PluginNavs::defaultEntryForMode(MODE_REPORTS)
    // Plus gopro() at the end.
    // Each item uses PluginNavs::modeLabel() for its title
    // and the mode's accent colour for its icon/styling.
}

private function buildModeNav(string $mode): array {
    // First item: back link
    //   slug: 'mode-selector-back'
    //   title: '← Shield Security'
    //   href: adminHome() (dashboard overview URL)
    //   No sub-items, no expand
    //
    // Then: the mode-specific items from the existing private methods
    //   (filtered by allowedNavsForMode, same as today but WITHOUT NAV_DASHBOARD)
    //
    // Then: gopro() at the end.
}

private function resolveCurrentMode(): string {
    $nav = $this->inav();
    // Dashboard overview = mode selector page = empty mode
    if ($nav === '' || $nav === PluginNavs::NAV_DASHBOARD) {
        return '';  // <-- THIS IS THE KEY FIX: don't fall back to MODE_ACTIONS
    }
    return PluginNavs::modeForNav($nav);
}
```

**Required changes in `nav_sidebar.twig`:**

The template already handles the `navbar_menu` array generically and supports items with or without `sub_items`. The back link item will render as a flat link (no sub-items). No template changes should be needed unless you want to style the back link differently (e.g. smaller font, left arrow icon). If styling is desired, add a CSS class like `mode-back-link` to the back link item's `classes` array and style it in the sidebar SCSS.

**Required changes in `PageAdminPlugin.php`:**

Currently line 74 creates the builder with no arguments: `(new NavMenuBuilder())->build()`. No change needed here — the builder resolves mode internally from the query string, which is the correct approach since the page handler already passes `NAV_ID` through the URL.

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

Note: The current `allowedNavsForMode(MODE_CONFIGURE)` includes `NAV_ZONES`, `NAV_RULES`, `NAV_TOOLS`. It also includes `NAV_DASHBOARD` which will be removed (replaced by the back link). Security Grades (`NAV_DASHBOARD / SUBNAV_DASHBOARD_GRADES`) needs special handling — it's a dashboard sub-nav that belongs in Configure mode. Options: (a) add it as a direct link item in `buildModeNav()` for Configure, or (b) create a new `NAV_GRADES` constant that maps to the same page handler. Option (a) is simpler.

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
| User Sessions | `PageUserSessions` | Session list. `FindSessions` class supports `byIP()`, `byUser()`, and `mostRecent()`. |

### Investigation selectors delivery status

Dedicated investigation entry points are delivered and wired to subject-specific pages. **All entries reuse existing infrastructure — see Section 12 for the full component inventory and implementation directives.**

**By User**
- UI: Select a user by ID, username, or email (autocomplete dropdown from `wp_users`)
- Result: A page showing for that user: all activity log entries, all sessions, all IP addresses used, all HTTP requests
- Implementation: **Refactor** existing `PageInvestigateByUser.php`. Keep its data-loading methods. Replace its template with rail+panel layout using `options_rail_tabs.twig`. Wire tabs through the shared DataTable investigation framework (Section 12.2) — do NOT render static HTML tables. See Section 12.4.3 for detailed instructions.

**By IP Address**
- UI: Enter or select an IP address
- Result: The existing `IpAnalyse\Container` (5 tabs) wrapped with a subject header bar and summary stats
- Implementation: Create `PageInvestigateByIp.php` that renders `IpAnalyse\Container` (reused as-is) below a new subject header. See Section 12.4.4 — the Container is not rebuilt.

**By Plugin**
- UI: Select an installed plugin from a dropdown
- Result: 4-tab analysis: Overview (from `buildPluginData()`), File Status (from shared investigation file-status table contract), Vulnerabilities (from runtime WPV display results), Activity (from investigation DataTable framework)
- Implementation: `PageInvestigateByPlugin.php` is delivered. It reuses `PluginThemesBase::buildPluginData()` for overview, shared file-status table contracts for file status, and runtime vulnerability results from `WPV()->getResultsForDisplay()->getItemsForSlug()`. See Section 12.4.5.

**By Theme / By Core**
- UI: Select an installed theme, or navigate directly to WordPress Core.
- Result: Dedicated by-theme and by-core pages with shared rail/panel structure and investigation table contracts.
- Implementation: `PageInvestigateByTheme.php` and `PageInvestigateByCore.php` are delivered, with shared behavior consolidated in `BaseInvestigateAsset`.

### Data layer notes

- `BaseBuildTableData` already provides utility methods for rendering timestamps (`getColumnContent_Date()`), IP links (`getColumnContent_LinkedIP()`), and user links (`getUserHref()`). All investigation tables must use these — see Section 12.5 cross-cutting rules.
- `LoadLogs` already supports `wheres[]` for flexible filtering — adding user/IP/plugin filters is additive SQL via the investigation `BaseInvestigationData` class (Section 12.2).
- `IpAnalyse\Container` is already a complete IP investigation tool — just needs a subject header wrapper.
- `FindSessions::byIP()` and `FindSessions::byUser()` already exist for subject-specific session queries.
- `SearchTextParser` already parses `ip:x.x.x.x`, `user_id:42`, `user_name:admin` syntax — investigation tables reuse this.

### Investigate mode delivered baseline

The delivered investigate baseline includes:

- **Shared investigation DataTable framework** (Section 12.2)
- **By User** dedicated page with rail/panel + table contracts
- **By IP** dedicated page wrapping `IpAnalyse\Container` with subject header/stats
- **By Plugin / By Theme / By Core** dedicated subject pages with shared table pipeline
- Existing pages (Activity Log, Traffic Log, Live Log, IP Rules) reorganised into the Investigate sidebar

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

**Status (2026-02-22):** ✅ Complete. Two-state sidebar behavior is implemented in `NavMenuBuilder` and WP submenu mode entries are in place. Breadcrumb mode-pathing is also in place.

**Modify:**

| File | Change |
|---|---|
| `NavMenuBuilder.php` | Implemented: mode selector state + in-mode sidebar state + back-link behavior + configure-grade shortcut insertion + normalized mode grouping/rendering. |
| `PluginAdminPageHandler.php` | Implemented: WP submenu registers mode-based items. |

**Cleanup:** Remove old flat submenu items (Activity, Traffic, etc.) from WP admin sidebar. (Already done.)

### Step 4: Mode Switching & Breadcrumbs

**Status (2026-02-22):** ✅ Complete. Breadcrumbs are mode-aware (`Shield Security → Mode → Page`), `OperatorModeSwitch` exists, and legacy Simple/Advanced toggle/runtime was removed in P5 cleanup.

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

**Status (2026-02-22):** ✅ Complete. `PageActionsQueueLanding.php` and `actions_queue_landing.twig` exist and are wired via `PluginNavs` (`SUBNAV_SCANS_OVERVIEW`), reusing `NeedsAttentionQueue`.

**How to build it — reuse, don't recreate:**

The Actions Queue landing page is primarily a composition of existing components. The heavy lifting is already done.

| Component to Reuse | File | How |
|---|---|---|
| `NeedsAttentionQueue` widget | `src/ActionRouter/Actions/Render/Components/Widgets/NeedsAttentionQueue.php` | **Render directly** on the landing page via `self::con()->action_router->render(NeedsAttentionQueue::class)`. This widget already groups action items by zone, ranks by severity, and renders both "issues" and "all clear" states. Do NOT rebuild this logic. |
| `AttentionItemsProvider` | `src/ActionRouter/Actions/Render/Components/Widgets/AttentionItemsProvider.php` | **Reuse as data source.** Already provides `buildScanItems()`, `buildMaintenanceItems()`, `buildSummaryWarningItems()`. Use these for the action count on the summary stats row. |
| `MeterCard` (action channel) | `src/ActionRouter/Actions/Render/Components/Meters/MeterCard.php` | **Render** with `meter_channel: 'action'` to show the action-channel meter score alongside the queue. |
| `PageScansResults` | `src/ActionRouter/Actions/Render/PluginAdminPages/PageScansResults.php` | **Link to**, do not embed. The sidebar already has Scan Results as a nav item in Actions mode. |
| `PageScansRun` | `src/ActionRouter/Actions/Render/PluginAdminPages/PageScansRun.php` | **Link to**, do not embed. |

**Create/Refactor:**

| File | Purpose | Implementation Notes |
|---|---|---|
| `PageActionsQueueLanding.php` | Actions mode landing | Extends `BasePluginAdminPage`. `getRenderData()` renders `NeedsAttentionQueue` widget (reused) + action-channel `MeterCard` (reused) + summary stat counts from `AttentionItemsProvider` (reused). Minimal new code — this page is a composition of existing components. |
| `actions_queue_landing.twig` | Template | Layout: action-channel meter card at top, NeedsAttentionQueue widget body below, quick links to Scan Results and Run Scan at bottom. |

### Step 6: Investigate Mode

**Status (2026-02-25):** Complete for the P6 slice. P6 foundation + P6a + P6b + P6-STAB + P6c + P6d + P6e + P6f are implemented, with dedicated pages for IP/Plugin/Theme/Core and investigation-context cross-subject linking in place.

**Prototype reference:** Implementors MUST review the HTML prototypes in `docs/OperatorModes/investigate-mode/` before building. These define the exact visual layout, data columns, tab structure, and cross-linking patterns. See Section 11 for detailed specifications.

**Create/Refactor:**

| File | Purpose | Prototype Reference |
|---|---|---|
| `PageInvestigateLanding.php` | Subject selector grid + lookup panels (refactor existing file) | `investigate-landing.html` (Completed 2026-02-24) |
| `investigate_landing.twig` | Template for landing (refactor existing file) | `investigate-landing.html` (Completed 2026-02-24) |
| `PageInvestigateByUser.php` | User analysis: header + stats + rail/panel (4 tabs) (refactor existing file) | `investigate-user.html` (Completed 2026-02-24) |
| `investigate_by_user.twig` | Template — rail+panel with Sessions, Activity, Requests, IP Addresses tabs (refactor existing file) | `investigate-user.html` (Completed 2026-02-24) |
| `PageInvestigateByIp.php` | IP analysis: wraps IpAnalyse\Container with subject header + stats | `investigate-ip.html` (Completed 2026-02-25) |
| `investigate_by_ip.twig` | Template — subject header + existing IpAnalyse 5-tab container | `investigate-ip.html` (Completed 2026-02-25) |
| `PageInvestigateByPlugin.php` | Plugin analysis: Overview, File Status, Vulnerabilities, Activity | `investigate-plugin.html` (Completed 2026-02-25) |
| `investigate_by_plugin.twig` | Template — rail+panel with 4 tabs | `investigate-plugin.html` (Completed 2026-02-25) |
| `PageInvestigateByTheme.php` | Theme analysis: same pattern as plugin (shared base class) | Completed 2026-02-25 |
| `investigate_by_theme.twig` | Template — rail+panel with 4 tabs for theme subject | Completed 2026-02-25 |
| `PageInvestigateByCore.php` | Core analysis: Overview, File Status, Activity | Completed 2026-02-25 |
| `investigate_by_core.twig` | Template — rail+panel with 3 tabs for core subject | Completed 2026-02-25 |

**Modify:**
- `PluginNavs.php` — route `by_ip/by_plugin/by_theme/by_core` to dedicated page handlers and keep only `activity/overview` as the Investigate landing route.
- `PluginURLs.php` — add canonical investigate URL helpers for user/plugin/theme/core and use them in landing/page contracts.
- Investigation table stack — expand registry/subject-wheres/delegating loaders to support activity subjects `plugin/theme/core`.
- Investigation data sources — scope cross-subject link behavior to investigation-context loaders while preserving global non-investigate table behavior.

**Implementation order:** P6-FOUNDATION (shared table framework) → Landing → By User → By IP → By Plugin → By Theme → WordPress Core. See Section 11.9.

**Critical prerequisite:** The shared investigation DataTable framework (Section 12.2) was used for all delivered subject pages (`by_user`, `by_ip`, `by_plugin`, `by_theme`, `by_core`) without introducing a parallel table pipeline.

### Step 7: Configure & Reports Modes

**Status (2026-02-22):** ✅ Complete for landing slice. Dedicated landing pages exist and are wired: `PageConfigureLanding.php`/`configure_landing.twig` and `PageReportsLanding.php`/`reports_landing.twig`.

Mostly sidebar reorganisation of existing pages. The actual configuration and reporting pages already exist — only the landing pages are new.

**How to build — reuse existing components:**

**Configure landing:**

| Component to Reuse | File | How |
|---|---|---|
| `MeterCard` (config channel) | `src/ActionRouter/Actions/Render/Components/Meters/MeterCard.php` | **Render** with `meter_channel: 'config'` and `is_hero: true` for the config posture score hero card. This is the existing meter card — just filtered to config channel. |
| Zone overview data | `SecurityZones` module | Query zone-level meter data to show a summary card per zone (8 zones). Each zone already has its own `MeterCard` — render a compact grid of zone meter cards. |
| `stat_box.twig` | `templates/twig/components/events/stats/stat_box.twig` | **Reuse** for summary stats (total zones configured, grade distribution, etc.). |

**Reports landing:**

| Component to Reuse | File | How |
|---|---|---|
| `ChartsSummary` | `src/ActionRouter/Actions/Render/Components/Charts/ChartsSummary.php` | **Render directly** on the reports landing. These charts already exist on the current dashboard — relocate their rendering here. |
| `PageReports` | `src/ActionRouter/Actions/Render/PluginAdminPages/PageReports.php` | **Link to** from the landing page. The full reports page already exists. |

**Create landing pages:**

| File | Purpose | Implementation Notes |
|---|---|---|
| `PageConfigureLanding.php` | Configure mode landing — config posture score + zone overview | Extends `BasePluginAdminPage`. Renders `MeterCard` with config channel (reused), zone summary grid (each zone as a compact `MeterCard` — reused), and links to Security Grades page. |
| `PageReportsLanding.php` | Reports mode landing — recent reports + chart summary | Extends `BasePluginAdminPage`. Renders `ChartsSummary` component (reused — relocated from dashboard), recent reports list, and link to full reports page. |

### Step 8: WP Dashboard Widget Update

**Status (2026-02-26):** ✅ Complete.

Implemented in two bounded passes:
1. Dashboard/widget refactor pass completed:
   - Shared channel normalization reuse across meter/widget callsites.
   - Action summary derivation moved to `AttentionItemsProvider`.
   - Legacy v2 widget transient cleanup added in v3 regeneration path.
   - Legacy widget template removed and legacy-only widget style blocks cleaned.
2. Follow-up optimization/simplification pass completed:
   - `ProgressMeters::normalizeMeterChannel()` readability simplification (no behavior change).
   - `WpDashboardSummary::getVars()` split to separate cache orchestration from payload construction.
   - `AttentionItemsProvider::buildWidgetRows()` preserved for compatibility with docblock deprecation only.
   - Shared built-meter cache helper consolidation across unit/integration support tests.

Implemented changes:

| File | Change |
|---|---|
| `WpDashboardSummary.php` | Two-indicator widget behavior delivered (configuration posture + action summary), with legacy v2 transient cleanup during v3 regeneration. |
| `AttentionItemsProvider.php` | Action summary derivation is provider-owned and reused by widget rendering; legacy `buildWidgetRows()` compatibility path remains. |
| `templates/twig/admin/admin_dashboard_widget.twig` | Legacy template removed; v2 template path remains authoritative (`admin_dashboard_widget_v2.twig`). |
| `assets/css/shield/dashboard-widget.scss` | Legacy-only widget style blocks removed while v2 widget styling remains intact. |

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

## 11. UI Specification — Investigate Mode

> **Prototypes:** `docs/OperatorModes/investigate-mode/` — open these HTML files in a browser for interactive reference.

This section defines the visual patterns, data layout, and component structure for the Investigate operator mode. Once the Investigate mode is implemented and validated, the same structural patterns (subject header, summary stats, rail+panel layout) will be adapted for Configure and Reports landing pages.

### 11.1 Design tokens (reference)

All prototypes and implementations use these CSS custom properties. They match the existing Shield SCSS variables documented in `assets/css/README.md`.

```
--status-good:      #008000    (green — safe, active, clean)
--status-warning:   #edb41d    (amber — needs attention, pending)
--status-critical:  #c62f3e    (red — blocked, vulnerable, offense)
--status-info:      #0ea8c7    (teal — neutral information, links)
--card-radius:      10px
--card-shadow:      0 1px 6px rgba(0,0,0,0.07)
--card-accent-height: 4px      (coloured accent bar on top of cards)
--surface-neutral:  #f5f6f5    (light grey background)
--surface-raised:   #f8f9f8    (rail background)
--border-subtle:    #d9dfd9    (dividers)
--accent-salt-green: #d2ddd2   (rail accent bar)
```

### 11.2 Investigate landing page

**Prototype file:** `investigate-landing.html`

**Layout structure:**

```
┌─ Breadcrumbs: Shield Security » Investigate ──────────────────────┐
│                                                                    │
│  Page title: "Investigate"                                         │
│  Subtitle: "Choose a subject to investigate..."                    │
│                                                                    │
│  ┌─ Subject Selector Grid (auto-fill, minmax 200px) ─────────┐   │
│  │  [Users]  [IPs]  [Plugins]  [Themes]                       │   │
│  │  [WP Core]  [HTTP Requests]  [Activity Log]  [WooCommerce] │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                    │
│  ┌─ Lookup Panel (toggles per selected subject) ──────────────┐   │
│  │  [Search input / dropdown / direct link depending on type]  │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                    │
│  Quick access: [Activity Log] [HTTP Requests] [Live Traffic] [IPs] │
└────────────────────────────────────────────────────────────────────┘
```

**Subject cards:** Each card is a clickable tile with an icon (48×48px, rounded 12px, `status-bg-info-light` background), a label (bold 0.9rem), and a one-line description (0.78rem, #888). The active card gets a 2px `status-info` border and `status-bg-info-light` background. Cards with `disabled` class are greyed out with a "PRO" badge.

**Subjects and their lookup types:**

| Subject | Icon | Lookup Type | Input | Action |
|---|---|---|---|---|
| Users | `bi-people-fill` | Text search | Username, email, or user ID | Navigate to `investigate-user` page |
| IP Addresses | `bi-globe2` | Text input | IPv4 or IPv6 address | Navigate to `investigate-ip` page |
| Plugins | `bi-puzzle-fill` | Dropdown select | Installed plugins list | Navigate to `investigate-plugin` page |
| Themes | `bi-palette-fill` | Dropdown select | Installed themes list | Navigate to `investigate-theme` page |
| WordPress Core | `bi-wordpress` | Direct link | — | Navigate to core file status page |
| HTTP Requests | `bi-arrow-left-right` | Direct link | — | Navigate to Traffic Log (existing) |
| Activity Log | `bi-journal-text` | Direct link | — | Navigate to Activity Log (existing) |
| WooCommerce | `bi-cart3` | Disabled (PRO) | — | — |

**Quick tools strip:** Row of small pill-buttons linking to existing full pages (Activity Log, HTTP Requests, Live Traffic, IP Rules). Always visible below the lookup panel. These are shortcuts — the same pages are accessible from the sidebar.

**JavaScript:** Clicking a subject card toggles `.active` on that card and shows/hides the corresponding `#lookup-{subject}` panel. The panel for the initially active subject (Users) is visible on load.

### 11.3 Investigation analysis page pattern

All investigation subjects that load a specific entity (User, IP, Plugin, Theme) share a consistent three-part layout:

```
┌─ Breadcrumbs: Shield Security » Investigate » [Subject Type] ─────┐
│                                                                    │
│  ┌─ Subject Header Bar ──────────────────────────────────────┐    │
│  │  [Avatar]  Name / identifier                    [Change ←] │    │
│  │            Meta line (email, version, etc.)                │    │
│  │            Status pills (active, vulnerable, etc.)         │    │
│  └────────────────────────────────────────────────────────────┘    │
│                                                                    │
│  ┌─ Summary Stats Row (4 equal columns) ─────────────────────┐    │
│  │  [Stat 1]  [Stat 2]  [Stat 3]  [Stat 4]                  │    │
│  └────────────────────────────────────────────────────────────┘    │
│                                                                    │
│  ┌─ Rail + Panel Layout ─────────────────────────────────────┐    │
│  │ ┌──── Rail ────┐ ┌──── Tab Content ────────────────────┐  │    │
│  │ │ Tab 1 [badge] │ │                                     │  │    │
│  │ │ Tab 2 [badge] │ │  (data table, cards, signals, etc.) │  │    │
│  │ │ Tab 3 [badge] │ │                                     │  │    │
│  │ │ Tab 4 [badge] │ │                                     │  │    │
│  │ └───────────────┘ └─────────────────────────────────────┘  │    │
│  └────────────────────────────────────────────────────────────┘    │
└────────────────────────────────────────────────────────────────────┘
```

#### Subject Header Bar

A horizontal bar (`display:flex`, `align-items:center`) with:

- **Avatar** (52×52px): Circular for users/IPs, rounded-square (12px radius) for plugins/themes. Background colour reflects entity status (info=default, good=trusted, warning=attention, critical=blocked/vulnerable).
- **Info block** (flex:1): Entity name (bold 1.1rem), meta line (0.82rem, #888 — email, version, author, directory, etc.), and status pills (inline badges — "Active", "Update Available", "1 Vulnerability", "3 Offenses").
- **"Change" button** (flex-shrink:0): `btn-sm btn-outline-secondary` with back arrow, links to landing page.

#### Summary Stats Row

Four equal-width shield-cards in a `row g-2`. Each contains a coloured accent bar (status-info for neutral counts, status-warning/critical for concerning values) and a centred stat: large value (1.5rem bold) + small label (0.75rem uppercase).

The stats displayed are subject-specific:

| Subject | Stat 1 | Stat 2 | Stat 3 | Stat 4 |
|---|---|---|---|---|
| User | Sessions | Activity Events | HTTP Requests | Unique IPs |
| IP Address | Offenses | Sessions | Activity Events | HTTP Requests |
| Plugin | Vulnerabilities | Modified Files | Activity Events | Total Files |
| Theme | Vulnerabilities | Modified Files | Activity Events | Total Files |

#### Rail + Panel Layout

The core analysis area uses the same `shield-analyse-layout` pattern as the existing `IpAnalyse\Container`:

- **Rail** (200px fixed, `surface-raised` background): Vertical `nav flex-column` with `data-bs-toggle="tab"` buttons. Each button has an icon, label, and optional count badge. Active tab has a 3px left border in `status-info` and white background.
- **Content** (flex:1): Bootstrap `tab-content` with `tab-pane` panels. Each panel has consistent 1.25rem padding.
- **Responsive:** Below 768px, the rail collapses to a horizontal scrollable tab row.

This matches the existing `options_rail_tabs.twig` template. The Twig template can be reused directly for the rail; panel content is page-specific.

### 11.4 Investigate User — tab definitions

**Prototype file:** `investigate-user.html`

**Subject header meta:** Username (bold), email, User ID, WordPress role.

**Tabs:**

| Tab | Icon | Badge | Content |
|---|---|---|---|
| Sessions | `bi-key` | Session count | Table: Login (username), Last Active (relative+absolute), Logged In (relative+absolute), IP Address (link to IP analysis), Sec Admin (Yes/No badge) |
| Activity | `bi-journal-text` | Event count | Table: Event (description), When (relative+absolute), IP Address (link). "Full Log" button links to Activity Log with user filter pre-applied. |
| Requests | `bi-arrow-left-right` | Request count | Table: Request (verb badge + path code), When (relative+absolute), Response (code + Clean/Offense badge), IP Address (link). "Full Log" button links to Traffic Log with user filter. |
| IP Addresses | `bi-globe2` | Unique IP count | Card grid (2-col): Each card shows IP address (monospace link), last seen, status badge (Current/Known/Blocked), and counts (sessions, events, requests). Card accent colour reflects IP status. |

**Data sources (implementation):**

- Sessions: `FindSessions::byUser($userId)` — query `wp_shield_sessions` by user_id. Return last 50.
- Activity: `LoadLogs` with `wheres[user_id] = $userId`. Return last 75.
- Requests: `LoadRequestLogs` with `wheres[uid] = $userId`. Return last 75.
- IP Addresses: Aggregate unique IPs from sessions + request logs. For each IP, sub-query counts.

### 11.5 Investigate IP Address — tab definitions

**Prototype file:** `investigate-ip.html`

**Subject header meta:** IP address (monospace), geolocation (city, country), ISP/ASN, first seen date. Avatar background adapts to IP status.

This page wraps the existing `IpAnalyse\Container` with the standardised subject header and summary stats row. The five existing tabs are preserved.

**Tabs:**

| Tab | Icon | Badge | Content | Existing Component |
|---|---|---|---|---|
| General | `bi-info-circle` | — | IP details table (address, hostname, location, ISP, first/last seen, status) + IP Rule Status card (block status, offense count, linked users, block/bypass actions) | `IpAnalyse\General` |
| Bot Signals | `bi-robot` | Bot score | Signal bar chart: each signal type as a labelled horizontal bar (0–100). Signals: Login Failures, 404 Probing, XML-RPC, Comment SPAM, Fake Crawler, User-Agent Invalid, etc. | `IpAnalyse\BotSignals` |
| Sessions | `bi-key` | Session count | Table: User (link to user investigation), Login time, Last Active, Sec Admin status. | `IpAnalyse\Sessions` |
| Activity | `bi-journal-text` | Event count | Table: Event description, When, User. "Full Log" link pre-filtered by IP. | `IpAnalyse\Activity` |
| Traffic | `bi-arrow-left-right` | Request count | Table: Request (verb+path), When, Response (code+offense). "Full Log" link pre-filtered by IP. | `IpAnalyse\Traffic` |

**Implementation note:** The existing `IpAnalyse\Container` already renders these five tabs using sub-component classes. The main change is adding the subject-header bar and summary-stats row above the container. This can be done by wrapping `Container::getRenderData()` output with the header data, or by creating a new `PageInvestigateByIp` page class that renders the header + container.

### 11.6 Investigate Plugin — tab definitions

**Prototype file:** `investigate-plugin.html`

**Subject header meta:** Plugin name (bold), version, author, install directory. Status pills: Active/Inactive, Update Available, Vulnerability count, Abandoned.

**Tabs:**

| Tab | Icon | Badge | Content |
|---|---|---|---|
| Overview | `bi-info-circle` | — | Two-column layout. Left: info table (Name, Slug, Version with update pill, Author, Status, Source, Installed, Last Updated). Right: Security Summary card with vulnerability count, modified files count, update status, abandoned status. |
| File Status | `bi-file-earmark-code` | Issue count | Table: File (monospace path + size/type), Status (Modified/Unrecognised/Missing/Malware with icon), Detected (relative+absolute), Actions (View/Repair/Delete/Ignore button group). Footer note about SVN checksum comparison. |
| Vulnerabilities | `bi-shield-exclamation` | Active vuln count | Card list: Each vulnerability is a bordered card with title, type, disclosure date, fixed-in version, severity pill (Active/Resolved). Active vulns have `status-critical` left border; resolved vulns are dimmed. Link to vulnerability DB. |
| Activity | `bi-journal-text` | Event count | Table: Event (icon + description), When (relative+absolute), User (link to user investigation), IP Address (link). Events filtered to plugin slug: activations, deactivations, updates, file changes, settings changes. |

**Data sources (implementation):**

- Overview: `buildPluginData()` from `PluginThemesBase` — provides all info/flags/vars fields.
- File Status: `LoadFileScanResultsTableData` filtered by `ptg_slug` meta matching plugin slug. Returns `rid`, `file` (path_fragment), `status`, `detected_since`, `actions`.
- Vulnerabilities: runtime WPV display results (`WPV()->getResultsForDisplay()->getItemsForSlug($slug)`), rendered as concise status cards with lookup links.
- Activity: `LoadLogs` filtered by event slugs containing plugin identifier (activation/deactivation/update events store plugin slug in meta).

### 11.7 Investigate Theme — tab definitions

**No separate prototype** — uses identical layout to Investigate Plugin with these differences:

- Avatar: `bi-palette-fill` icon with rounded-square shape.
- Subject header meta: Theme name, version, author, active/parent/child status.
- Overview: Additional fields for child/parent theme relationships (`child_theme`, `parent_theme` from `buildThemeData()`).
- File Status: Same table, filtered by theme's `ptg_slug`.
- Vulnerabilities: Same card list, queried by theme slug.
- Activity: Filtered to theme-related events.

### 11.8 WordPress Core — analysis page

**No separate prototype** — this is a simplified version of the plugin/theme analysis pattern.

**Subject header:** WordPress logo icon, "WordPress Core" name, current version, auto-update status.

**Tabs:**

| Tab | Content |
|---|---|
| Overview | Core version, update status, auto-update config, last scan time |
| File Status | Table of modified/missing/unrecognised core files. Data from `LoadFileScanResultsTableData` with `is_in_core` filter. Same table format as plugin File Status tab. |
| Activity | Core-related events: WordPress updates, core file modifications. Filtered from `LoadLogs`. |

### 11.9 Implementation order for Investigate mode

**Completion note (2026-02-25):** This execution order has been completed through WordPress Core and cross-subject linking.

1. **Investigate Landing Page** — subject selector grid + lookup panels. Create `PageInvestigateLanding.php` rendering the subject grid. Each subject card links to its analysis page or existing log page.
2. **Investigate User** — highest value, most complex. Create `PageInvestigateByUser.php` with rail+panel layout. Uses `FindSessions::byUser()`, `LoadLogs` filtered by user_id, `LoadRequestLogs` filtered by uid.
3. **Investigate IP** — wraps existing `IpAnalyse\Container`. Create `PageInvestigateByIp.php` that adds subject-header + summary stats above the existing container.
4. **Investigate Plugin** — Delivered `PageInvestigateByPlugin.php`. Uses `buildPluginData()`, shared file-status table contracts, runtime WPV display results, and investigation activity tables filtered by plugin subject context.
5. **Investigate Theme** — Near-identical to Plugin. Can share a base class.
6. **WordPress Core** — Simplified version of Plugin page with `is_in_core` filter.

### 11.10 Cross-subject linking

Investigation pages link to each other. This is critical for the investigative flow:

- **User → IP:** IP addresses in user tables link to `investigate-ip?ip={address}`.
- **IP → User:** User names in IP sessions tab link to `investigate-user?uid={id}`.
- **Plugin Activity → User:** User column links to `investigate-user?uid={id}`.
- **Plugin Activity → IP:** IP column links to `investigate-ip?ip={address}`.
- **User Activity → Plugin:** Plugin/theme-related activity rows include investigate links where metadata identifies the asset.

This creates a web of investigation paths. The "Change [Subject]" button in the header always returns to the landing page, while entity links within tables enable lateral investigation.

---

## 12. Implementation Architecture — Reusable Components & Patterns

> **CRITICAL: Read this section before writing any code.** Every investigation page must be built by extending or composing existing plugin infrastructure. Do not create bespoke rendering logic when an existing component already handles the pattern. The guiding principle is DRY (Don't Repeat Yourself) — if you modify the framework for one table, it should apply across all tables.

### 12.1 Component inventory — what already exists

The plugin has a mature, battle-tested component architecture. The table below is the authoritative inventory. Before building anything, check whether it already exists here.

#### Page handlers

| Component | File | Purpose | Extend or Reuse? |
|---|---|---|---|
| `BasePluginAdminPage` | `src/ActionRouter/Actions/Render/PluginAdminPages/BasePluginAdminPage.php` | Base class for all admin pages. Provides breadcrumbs, contextual hrefs, page title/subtitle, nonce field. | **Extend.** All investigation pages extend this. Override `getRenderData()`, `getInnerPageTitle()`, `getInnerPageSubTitle()`. |
| `BaseRender` | `src/ActionRouter/Actions/Render/BaseRender.php` | Base class for all renderable components (pages, widgets, offcanvas). Provides `getAllRenderDataArrays()` merge at priority levels, Twig rendering. | **Extend indirectly** (via BasePluginAdminPage). Sub-components (summary stats, subject header) extend this directly. |
| `PageInvestigateByUser` | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByUser.php` | **Implemented.** Uses rail+panel layout with shared investigation DataTable contracts for Sessions/Activity/Requests and server-rendered IP card grid. | **Reuse as reference.** Keep existing loader methods and shared table framework pattern for remaining subject pages. |
| `PageInvestigateLanding` | `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php` | **Implemented.** Subject selector landing with lookup panels and quick-tools strip. | **Reuse as reference.** Keep active-subject and lookup contract pattern for other subject landing flows. |

#### DataTable build infrastructure (column definitions + configuration)

| Component | File | Purpose | Extend or Reuse? |
|---|---|---|---|
| `Build\Base` | `src/Tables/DataTables/Build/Base.php` | Abstract base for DataTable configuration. Defines `getColumnDefs()`, `getColumnsToDisplay()`, `getOrderColumnSlug()`, `getSearchPanesData()`. Returns JSON config consumed by JS. | **Extend.** Create child classes for investigation tables. Override column defs and display columns. SearchPanes can be disabled by returning empty array from `getSearchPanesData()`. |
| `Build\ForActivityLog` | `src/Tables/DataTables/Build/ForActivityLog.php` | Activity log column definitions: event, user, ip, day, timestamp. Full SearchPanes for day/event/user. | **Extend.** Create `ForInvestigationActivityLog` that narrows columns (drop SearchPanes, pre-apply subject filter) for embedded use within investigation tabs. |
| `Build\ForTraffic` | `src/Tables/DataTables/Build/ForTraffic.php` | Traffic log column definitions: path, verb, code, offense, ip, day. | **Extend.** Create `ForInvestigationTraffic` with narrowed columns for investigation context. |
| `Build\ForSessions` | `src/Tables/DataTables/Build/ForSessions.php` | Session column definitions: user, logged_in, last_activity, ip, sec_admin. | **Extend.** Create `ForInvestigationSessions` — may remove user column when investigating a specific user (since it's redundant). |
| `Build\Scans\ForPluginTheme` | `src/Tables/DataTables/Build/Scans/ForPluginTheme.php` | Plugin/theme file scan result columns: file, status, detected, actions. | **Extend.** Create `ForInvestigationPluginFiles` with pre-applied `ptg_slug` filter. |

#### DataTable data loading (server-side AJAX)

| Component | File | Purpose | Extend or Reuse? |
|---|---|---|---|
| `BaseBuildTableData` | `src/Tables/DataTables/LoadData/BaseBuildTableData.php` | Abstract base for server-side DataTable data loading. Handles pagination, search text parsing, SearchPane validation, record counting, row building. Provides utility methods: `getColumnContent_Date()`, `getColumnContent_LinkedIP()`, `getUserHref()`. | **Extend.** All investigation data loaders extend this. The utility methods (`getColumnContent_LinkedIP()`, `getUserHref()`) are essential for cross-subject linking — reuse them, do not rewrite IP/user link generation. |
| `ActivityLog\BuildActivityLogTableData` | `src/Tables/DataTables/LoadData/ActivityLog/BuildActivityLogTableData.php` | Loads activity log records with full search, pagination, SearchPanes. Builds rows from `ActivityLogs` DB. | **Extend.** Create `BuildInvestigationActivityLogData` that receives subject context via the locked investigation contract (`table_type`, `subject_type`, `subject_id`) and injects subject WHERE constraints in `buildWheresFromSearchParams()`. Disable or simplify SearchPanes. |
| `Traffic\BuildTrafficTableData` | `src/Tables/DataTables/LoadData/Traffic/BuildTrafficTableData.php` | Loads traffic log records. | **Extend.** Same pattern as activity log — create `BuildInvestigationTrafficData` with pre-set subject filter. |
| `Sessions\BuildSessionsTableData` | `src/Tables/DataTables/LoadData/Sessions/BuildSessionsTableData.php` | Loads session records. | **Extend.** Create `BuildInvestigationSessionsData` with user-only subject filtering (`user_id`). |
| `Scans\LoadFileScanResultsTableData` | `src/Tables/DataTables/LoadData/Scans/LoadFileScanResultsTableData.php` | Loads file scan results. Filters by `ptg_slug` meta, `is_in_core` meta, etc. | **Extend.** Create `BuildInvestigationFileScanData` with pre-set `ptg_slug` filter for plugin/theme investigation. |
| `SearchTextParser` | `src/Tables/DataTables/LoadData/SearchTextParser.php` | Parses structured search syntax (`ip:x.x.x.x`, `user_id:42`, `user_name:admin`). | **Reuse as-is.** Investigation tables should support the same search syntax within their context. |

#### JavaScript

| Component | File | Purpose | Extend or Reuse? |
|---|---|---|---|
| `ShieldTableBase` | `assets/js/components/tables/ShieldTableBase.js` | Initialises DataTables with AJAX, handles server-side requests, configures SearchPanes/buttons/select. Default config: `dom: 'PrBpftip'`, `serverSide: true`, `searchDelay: 600`, `pageLength: 25`. | **Extend.** Create `InvestigationTable extends ShieldTableBase` that overrides `getDefaultDatatableConfig()` to use a simplified `dom` string (remove SearchPanes `P`), disable multi-select, and set a smaller `pageLength`. This single JS class serves ALL investigation tabs — a change to `InvestigationTable` applies everywhere. |

#### Tab/Rail templates

| Component | File | Purpose | Extend or Reuse? |
|---|---|---|---|
| `options_rail_tabs.twig` | `templates/twig/components/config/options_rail_tabs.twig` | Renders a vertical nav rail with Bootstrap `data-bs-toggle="tab"` tabs. Expects `nav_items[]` with `target`, `id`, `controls`, `label`, `is_focus`. | **Reuse directly.** All investigation analysis pages use this template for the left rail. The nav items are built in the PHP page handler and passed as data. |
| `container.twig` (IpAnalyse) | `templates/twig/wpadmin/components/ip_analyse/container.twig` | Renders the IpAnalyse 5-tab layout using `options_rail_tabs.twig` for the rail and `tab-content` panels for the body. | **Reuse pattern.** Investigation pages follow the exact same layout structure. The IpAnalyse Container template is the reference implementation — study it, then apply the same pattern to new investigation templates. |

#### Card and widget components

| Component | File | Purpose | Extend or Reuse? |
|---|---|---|---|
| `stat_box.twig` | `templates/twig/components/events/stats/stat_box.twig` | Renders a single stat card (count + label). | **Reuse.** Use for summary stats row on investigation pages. Adapt the data shape to pass investigation counts. |
| `stats_collection.twig` | `templates/twig/components/events/stats/stats_collection.twig` | Renders a row of stat boxes. | **Reuse.** Wrap investigation summary stats in this template. |
| `NeedsAttentionQueue` | `src/ActionRouter/Actions/Render/Components/Widgets/NeedsAttentionQueue.php` | Queue widget with severity-ranked items grouped by zone. | **Reuse for Actions Queue mode.** The Actions Queue landing page should render this component directly — it IS the actions queue. |
| `MeterCard` | `src/ActionRouter/Actions/Render/Components/Meters/MeterCard.php` | Renders a security meter with progress, grade, and status. | **Reuse for Configure mode landing.** The config posture score card should render `MeterCard` with `meter_channel: 'config'`. |

#### OffCanvas components

| Component | File | Purpose | Extend or Reuse? |
|---|---|---|---|
| `OffCanvasBase` | `src/ActionRouter/Actions/Render/Components/OffCanvas/OffCanvasBase.php` | Base for slide-out panels. Override `buildCanvasTitle()` and `buildCanvasBody()`. | **Reuse.** IP links in investigation tables should trigger the existing `IpAnalysis` offcanvas (class `offcanvas_ip_analysis`) for quick inspection without page navigation. |
| `IpAnalysis` offcanvas | `src/ActionRouter/Actions/Render/Components/OffCanvas/IpAnalysis.php` | Slide-out IP analysis panel that renders `IpAnalyse\Container`. | **Reuse as-is.** Every IP address link in investigation tables should have class `offcanvas_ip_analysis` to enable this. Cross-subject links (to full investigation pages) are separate buttons. |

#### AJAX actions

| Component | File | Purpose | Extend or Reuse? |
|---|---|---|---|
| `ActivityLogTableAction` | `src/ActionRouter/Actions/ActivityLogTableAction.php` | Handles `retrieve_table_data` sub-action for activity log DataTable. Instantiates `BuildActivityLogTableData`. | **Extend pattern.** Create `InvestigationTableAction` that routes to the correct `BuildInvestigation*Data` class based on the table type parameter. Or create per-subject actions (simpler). |
| `DynamicPageLoad` | `src/ActionRouter/Actions/DynamicPageLoad.php` | Lazy loads page content via AJAX. Request contains `dynamic_load_slug` + `dynamic_load_data`. | **Reuse.** Consider using dynamic loading for investigation tab panels so the initial page load is fast and tabs are loaded on demand. |

### 12.2 The shared table framework — foundational task

**This must be built BEFORE any investigation tab.** Every table in every investigation tab uses this shared framework. Modifying it once applies everywhere.

#### What to build

Create an `Investigation` sub-namespace within the existing DataTable infrastructure:

```
src/Tables/DataTables/Build/Investigation/
    BaseInvestigationTable.php          ← extends Build\Base
    ForActivityLog.php                  ← extends BaseInvestigationTable
    ForTraffic.php                      ← extends BaseInvestigationTable
    ForSessions.php                     ← extends BaseInvestigationTable
    ForFileScanResults.php              ← extends BaseInvestigationTable

src/Tables/DataTables/LoadData/Investigation/
    BaseInvestigationData.php           ← extends BaseBuildTableData
    BuildActivityLogData.php            ← extends BaseInvestigationData
    BuildTrafficData.php                ← extends BaseInvestigationData
    BuildSessionsData.php               ← extends BaseInvestigationData
    BuildFileScanResultsData.php        ← extends BaseInvestigationData

assets/js/components/tables/
    InvestigationTable.js               ← extends ShieldTableBase
```

#### `BaseInvestigationTable` (Build layer)

```php
// Extends Build\Base
// Adds:
//   - $subjectType (string: 'user', 'ip', 'plugin', 'theme', 'core')
//   - $subjectId (mixed: user ID, IP string, plugin slug, etc.)
//   - Overrides getSearchPanesData() to return [] (no SearchPanes by default)
//   - Adds getSubjectFilterColumns(): array — columns hidden because they're
//     redundant (e.g., 'user' column when investigating a specific user)
//   - getColumnsToDisplay() calls parent then removes subject filter columns
```

#### `BaseInvestigationData` (LoadData layer)

```php
// Extends BaseBuildTableData
// Adds:
//   - $subjectType and $subjectId properties
//   - Overrides buildWheresFromSearchParams() to always inject subject filter
//   - Provides getSubjectWheres(): array — returns the SQL WHERE clauses
//     that filter all records to the current investigation subject
//   - Keeps strict getSearchPanesDataBuilder() return types and disables SearchPanes using empty-array behavior
//   - Child classes override getSubjectWheres() for their specific subject:
//       user → ['user_id' => $uid]
//       ip → ['ip' => $ip]
//       plugin → ['meta.ptg_slug' => $slug]
```

#### `InvestigationTable.js` (JavaScript layer)

```javascript
// Extends ShieldTableBase
// Overrides getDefaultDatatableConfig():
//   - dom: 'frtip'  (text search + table + info + pagination; no SearchPanes/buttons)
//   - serverSide: true (same as parent)
//   - pageLength: 15 (smaller for embedded tabs)
//   - select: false (no multi-select)
//   - searching: true (text search still works, just no SearchPanes)
//   - Passes table_type, subject_type, and subject_id with every AJAX request
//     so the server always filters by the investigation subject
```

#### Why this approach works

- **One change, all tables:** If you adjust pagination, column styling, or AJAX behaviour in `BaseInvestigationTable` or `InvestigationTable.js`, it applies to every investigation tab across every subject.
- **DRY data loading:** `BaseInvestigationData` injects the subject filter once. Child classes only define the subject-specific WHERE clause and row-building logic (which they inherit mostly from their parent full-table loaders).
- **Consistent cross-linking:** `BaseBuildTableData` already provides `getColumnContent_LinkedIP()` and `getUserHref()` for rendering clickable IP/user links. All investigation tables inherit these, so IP and user links look and behave identically everywhere.
- **Progressive enhancement:** Start with text search only. SearchPanes can be added later to specific investigation tables by overriding `getSearchPanesData()` in the child class — the infrastructure already supports it.

### 12.3 Rail+Panel layout — how to build it

Every investigation analysis page (User, IP, Plugin, Theme, Core) uses the same layout. Here is exactly how to build it using existing components.

#### PHP page handler pattern

```php
// Every investigation page follows this structure:

class PageInvestigateByUser extends BasePluginAdminPage {

    const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_user.twig';

    protected function getRenderData(): array {
        $subject = $this->resolveLookupSubject();  // Resolve from query params
        if (empty($subject)) {
            return $this->buildLookupOnlyView();  // Show lookup form if no subject
        }

        return [
            'strings' => [
                'page_title' => 'Investigate User: '.$subject->user_login,
                // ... tab labels, headings
            ],
            'vars' => [
                'subject' => $this->buildSubjectHeaderData($subject),
                'summary_stats' => $this->buildSummaryStats($subject),
                'datatables_init' => [
                    'sessions' => (new Investigation\ForSessions())
                        ->setSubject('user', $subject->ID)->build(),
                    'activity' => (new Investigation\ForActivityLog())
                        ->setSubject('user', $subject->ID)->build(),
                    'requests' => (new Investigation\ForTraffic())
                        ->setSubject('user', $subject->ID)->build(),
                ],
            ],
            'ajax' => [
                'table_action' => ActionData::BuildJson(
                    InvestigationTableAction::class,
                    ['subject_type' => 'user', 'subject_id' => $subject->ID]
                ),
            ],
            'content' => [
                'nav_rail' => $this->buildNavRailItems(),
                // Tab panel content is loaded by DataTables AJAX, not pre-rendered
            ],
        ];
    }

    private function buildNavRailItems(): array {
        // Returns array matching options_rail_tabs.twig expected shape
        return [
            ['target' => '#tab-sessions', 'id' => 'nav-sessions',
             'controls' => 'tab-sessions', 'label' => 'Sessions', 'is_focus' => true],
            ['target' => '#tab-activity', 'id' => 'nav-activity',
             'controls' => 'tab-activity', 'label' => 'Activity', 'is_focus' => false],
            // ...
        ];
    }
}
```

#### Twig template pattern

```twig
{# investigate_by_user.twig — follows IpAnalyse container.twig pattern #}

{# ── Subject Header (new reusable partial) ── #}
{% include 'components/investigate/subject_header.twig' with {subject: vars.subject} %}

{# ── Summary Stats (reuse stat_box.twig) ── #}
<div class="row g-2 mb-3">
    {% for stat in vars.summary_stats %}
        <div class="col">
            {% include 'components/events/stats/stat_box.twig' with {stat: stat} %}
        </div>
    {% endfor %}
</div>

{# ── Rail + Panel (reuse options_rail_tabs.twig for rail) ── #}
<div class="shield-options-layout has-rail">
    <div class="shield-options-rail">
        {% include 'components/config/options_rail_tabs.twig' with {
            nav_items: content.nav_rail
        } %}
    </div>
    <div class="tab-content">
        {# Each panel contains a DataTable container — AJAX-loaded #}
        <div class="tab-pane active show" id="tab-sessions">
            <table class="shield-investigation-table"
                   data-table-config='{{ vars.datatables_init.sessions }}'
                   data-ajax-action='{{ ajax.table_action }}'>
            </table>
        </div>
        <div class="tab-pane" id="tab-activity">
            <table class="shield-investigation-table"
                   data-table-config='{{ vars.datatables_init.activity }}'
                   data-ajax-action='{{ ajax.table_action }}'>
            </table>
        </div>
        {# ... more tab panes #}
    </div>
</div>
```

#### Reusable Twig partials to create

Create these shared templates that ALL investigation pages include:

| Partial | File | Purpose |
|---|---|---|
| Subject header | `templates/twig/wpadmin/components/investigate/subject_header.twig` | Avatar + name + meta + status pills + "Change" button. Accepts `subject` data with `type`, `name`, `meta[]`, `status_pills[]`, `avatar_icon`, `change_href`. |
| Summary stats row | Reuse existing `stats_collection.twig` | Row of 4 stat cards. Already exists. |
| Investigation table container | `templates/twig/wpadmin/components/investigate/table_container.twig` | Wraps a `<table>` element with DataTable data attributes + panel heading + "Full Log" link. Used inside each tab pane. |

### 12.4 Per-section implementation directives

This section gives the implementing agent specific instructions for each part of the investigation pages. Each directive says exactly which existing component to extend or reuse and what to change.

#### 12.4.1 Investigate Landing Page

**Page handler:** Refactor existing `PageInvestigateLanding.php` (do NOT create a new file).

**Status update (2026-02-25):** Implemented and integrated with dedicated subject pages.
1. Landing refactored to subject-selector + panel architecture with Bootstrap tab behavior.
2. Inline `IpAnalyse\Container` embed removed from the landing page.
3. Landing data contract now provides `active_subject`, persisted `input`, and plugin/theme option lists.
4. Dedicated page routing is active for `by_ip`, `by_plugin`, `by_theme`, and `by_core`; transitional landing routing for those sub-navs is removed.
5. Breadcrumb handling now treats only `activity/overview` as landing-routed in Investigate mode.
6. Source-string fragile tests were replaced with behavior-level unit/integration coverage for route contracts and landing payload behavior.

**What to keep:**
- The current user lookup resolution logic (for example `ResolveUserLookup::resolve()` flow)
- The IP validation logic
- The page handler registration in `PluginNavs.php`

**What to replace:**
- The template. Replace `investigate_landing.twig` contents with the subject selector grid from the prototype.
- The landing render structure. Provide plugin/theme options and subject hrefs via existing service wrappers and URL helpers (`Services::WpPlugins()->getPluginsAsVo()`, `Services::WpThemes()->getThemesAsVo()`, `plugin_urls`).

**No new PHP infrastructure needed.** Keep logic in the existing landing page class with small private helpers and existing wrappers/helpers; no new service layer is required.

#### 12.4.2 Investigation tables (all subjects)

**DO NOT build static HTML tables.** Every data table in every investigation tab must be a DataTable instance using the shared framework from Section 12.2. This is non-negotiable.

**Why DataTables, not static tables:**
- Sorting: Users expect to click column headers to sort.
- Pagination: Investigation data sets can be large (hundreds of activity events).
- Consistent rendering: `BaseBuildTableData` already formats timestamps (`getColumnContent_Date()`), IP links (`getColumnContent_LinkedIP()`), and user links (`getUserHref()`). Using these ensures every timestamp, IP, and user link looks the same and behaves the same across all tables.
- Text search: Even without SearchPanes, the text search box with `SearchTextParser` syntax (`ip:x.x.x.x`, `user_id:42`) works.
- AJAX loading: Tables load data on demand, not on initial page render. This keeps the page fast.

**For the Activity tab specifically:**
- The existing `BuildActivityLogTableData` already loads activity records, formats rows, and handles pagination.
- Create `Investigation\BuildActivityLogData extends BaseInvestigationData` that:
  1. Delegates row building to the same logic as `BuildActivityLogTableData.buildTableRowsFromRawRecords()`
  2. Injects subject filter via `getSubjectWheres()` (e.g., `['user_id' => 42]`)
  3. Keeps strict `getSearchPanesDataBuilder()` contracts and disables SearchPanes in investigation context via empty-array behavior
- The column definitions come from `Investigation\ForActivityLog extends BaseInvestigationTable` which mirrors `ForActivityLog` but removes SearchPane config and optionally hides the subject column.

**For the Requests/Traffic tab:**
- Same pattern. Extend `BuildTrafficTableData`'s row-building logic via `Investigation\BuildTrafficData`.

**For the Sessions tab:**
- Same pattern. Extend `BuildSessionsTableData`'s row-building logic.

**For the File Status tab (Plugin/Theme investigation):**
- Extend `LoadFileScanResultsTableData`. Pre-set `ptg_slug` filter in `getSubjectWheres()`.
- The action buttons (View, Repair, Delete, Ignore) are already rendered by the existing scan results table row builder — reuse that rendering logic.

#### 12.4.3 Investigate User page

**Page handler:** Refactor existing `PageInvestigateByUser.php`.

**What to keep:**
- The current subject lookup resolution flow for user input (returns `?WP_User` when valid)
- `buildSessions()`, `buildActivityLogs()`, `buildRequestLogs()`, `buildRelatedIps()` — keep these methods as data providers for summary stats counts, but DO NOT use their return arrays to render static tables.

**What to change:**
- Instead of passing full data arrays to the template for static rendering, pass DataTable configuration JSON (`datatables_init`) and AJAX action data. The tables are populated by the `InvestigationTable.js` JavaScript class making AJAX calls to `InvestigationTableAction`.
- The summary stats row uses counts from the data providers (e.g., `count($this->buildSessions($user))`) but the actual table data is loaded on demand by DataTables.
- The IP Addresses tab is the one exception — it shows cards, not a DataTable. This tab's data is built server-side in the page handler (aggregate unique IPs from sessions + requests, sub-query counts per IP) and rendered as a card grid in Twig. Use `getColumnContent_LinkedIP()` from `BaseBuildTableData` to generate consistent IP links.

**Template:** Replace the current flat vertical layout with the rail+panel layout using `options_rail_tabs.twig` for the rail (see Section 12.3).

#### 12.4.4 Investigate IP page

**Page handler:** Create `PageInvestigateByIp.php`.

**Status update (2026-02-25):** Completed.
1. Dedicated by-ip page class/template implemented and registered in action routing.
2. Input validation and empty/invalid lookup state are implemented with a landing back-link.
3. Valid lookup renders shared subject header + summary cards and reuses `IpAnalyse\Container` unchanged.

**Key directive:** This page wraps the EXISTING `IpAnalyse\Container` component. Do NOT rebuild the 5-tab analysis — it already exists and works.

**What to build:**
1. Subject header bar (using the shared `subject_header.twig` partial)
2. Summary stats row (query counts: offenses, sessions, activity, requests for this IP)
3. Render the existing `IpAnalyse\Container` below the header/stats:
   ```php
   'content' => [
       'ip_analysis' => self::con()->action_router->render(
           IpAnalyseContainer::class,
           ['ip' => $ip]
       ),
   ],
   ```

**The entire Container component is reused as-is.** The only new code is the subject header and summary stats wrapper.

#### 12.4.5 Investigate Plugin page

**Page handler:** Create `PageInvestigateByPlugin.php`.

**Status update (2026-02-25):** Completed.
1. Dedicated by-plugin page class/template implemented and routed.
2. Shared rail+panel/tables contracts are provided through `BaseInvestigateAsset`.
3. Overview data reuses existing plugin scan data builder logic; vulnerabilities panel uses runtime WPV display results.
4. File status/activity tabs use the shared investigation DataTable pipeline.

**Data sources — all existing:**
- Plugin info: `buildPluginData()` from `Scans\Results\PluginThemesBase` — provides name, slug, version, author, flags (has_update, is_vulnerable, is_abandoned, has_guard_files). Reuse this method.
- File scan results: `LoadFileScanResultsTableData` filtered by `ptg_slug` matching plugin slug. Extend via `Investigation\BuildFileScanResultsData`.
- Vulnerabilities: runtime WPV display results for the selected plugin slug. This is NOT a DataTable — it is rendered as a compact server-side status panel/card.
- Activity: `Investigation\BuildActivityLogData` with subject filter `['plugin_slug' => $slug]`. Filter logic: match event slugs that contain the plugin identifier in their meta data.

**Tabs that use DataTables:** File Status, Activity.
**Tabs that use server-side rendering:** Overview (info table + security summary card), Vulnerabilities (card list — typically 0–3 items, not worth a DataTable).

#### 12.4.6 Investigate Theme page

**Share a base class with Plugin.** Create `BaseInvestigateAsset` that both `PageInvestigateByPlugin` and `PageInvestigateByTheme` extend. The base class provides:
- Subject header rendering (accepts any asset type)
- Overview tab structure
- File Status tab (DataTable with `ptg_slug` filter)
- Vulnerability tab (card list)
- Activity tab (DataTable with asset slug filter)

Theme-specific overrides:
- `buildThemeData()` instead of `buildPluginData()`
- Additional fields: `child_theme`, `parent_theme`
- Theme-specific event slugs for activity filtering

**Status update (2026-02-25):** Completed.
1. `BaseInvestigateAsset` is implemented and shared by plugin/theme pages.
2. Dedicated by-theme and by-core pages/templates are implemented and routed.
3. Core page provides overview + file status + activity tabs using the shared table contracts.

### 12.5 Cross-cutting implementation rules

These rules apply to ALL investigation pages. Violating them creates inconsistency.

**Status update (2026-02-25):** Implemented in investigation context.
1. Investigation table sources now apply canonical investigate links for user context.
2. IP links keep offcanvas analysis behavior and add investigate deep-link navigation.
3. Plugin/theme activity rows include investigate links where metadata identifies the asset.

1. **IP links.** Every IP address displayed anywhere in an investigation table must:
   - Be rendered using `BaseBuildTableData::getColumnContent_LinkedIP()` (which produces a monospace-styled `<a>` link)
   - Have CSS class `offcanvas_ip_analysis` (triggers the existing IP analysis slide-out panel)
   - Additionally link to the full `investigate-ip` page for deep investigation

2. **User links.** Every username displayed in an investigation table must:
   - Be rendered using `BaseBuildTableData::getUserHref()` (which generates the user profile link)
   - Link to the `investigate-user` page, not the WordPress user profile

3. **Timestamps.** Every timestamp must be rendered using `BaseBuildTableData::getColumnContent_Date()` which produces both relative ("3 hours ago") and absolute ("2026-02-22 14:48:22") displays. Do not write custom timestamp formatting.

4. **Status colours.** Use the existing status colour variables: `--status-good`, `--status-warning`, `--status-critical`, `--status-info`. Do not introduce new colour values.

5. **Tab badges.** Count badges on rail tabs show the total record count for that subject. These counts come from the `countTotalRecords()` method in the respective `BuildInvestigation*Data` class, called during page render (not AJAX). Cache with short TTL if performance is a concern.

6. **"Full Log" links.** Each investigation table tab should include a "Full Log" link that opens the corresponding full-page table (Activity Log, Traffic Log, etc.) with the subject pre-filtered. Use `SearchTextParser` syntax in the URL: e.g., `?search=user_id:42` for the activity log filtered to user 42. The full-page tables already parse this syntax.
