# Shield UI Redesign - Locked Specification

**Date:** 2026-03-03  
**Status:** Decision-locked and implementation-ready  
**Audience:** Implementation agents for parallel delivery

---

## 1. Canonical Document Set

These files are the authoritative set and must be read together before any code changes:

1. `docs/OperatorModes/redesign/REDESIGN-OVERVIEW.md` (this file)
2. `docs/OperatorModes/redesign/user-journeys.md`
3. `docs/OperatorModes/redesign/IMPLEMENTATION-ORCHESTRATION.md`
4. `docs/OperatorModes/redesign/prototype-configure-unified.html`
5. `docs/OperatorModes/redesign/prototype-investigate-expand.html`
6. `docs/OperatorModes/redesign/prototype-reports-alerts.html`

If any implementation idea conflicts with this file or `user-journeys.md`, this file and journeys win.

---

## 2. Locked Product Decisions (No Open Questions)

These decisions are final for this wave:

1. Rollout is **direct replacement** (no feature flag for this wave).
2. Implement with **shared reusable components first**, then mode-specific work.
3. Keep and reuse existing architecture (`PageModeLandingBase`, action router, existing tables/components) rather than rebuilding parallel systems.
4. Investigate landing has **7 tiles** only:
   - User
   - IP Address
   - Plugin
   - Theme
   - Core Files
   - Live Traffic
   - Premium Integrations (disabled placeholder)
5. **Activity Log**, **Sessions**, and historical **Traffic Log** are not Investigate landing tiles. They remain dedicated sidebar pages.
   Activity Log, Sessions, and historical Traffic Log are not Investigate landing tiles.
6. `Live Traffic` means live/request stream behavior. Do not label it with ambiguous "Traffic Log" on the tile.
7. Generic Plugin investigation covers generic plugin lifecycle/activity events. It does not implement WooCommerce-specific event modeling in this wave.
8. WooCommerce-specific UX is deferred. For this wave, use a disabled **Premium Integrations** placeholder tile.
9. Reports scope for this wave is **style/shell consistency only**. Reports tile-panel redesign is not part of this wave.
10. Actions Queue remains structurally unchanged (only alignment with shared shell/accent conventions).
11. Configure keeps the Security Grades nav entry and section mapping.
12. Tile content density is compact: **icon + title + stat/badge**. No per-tile descriptive paragraph.

---

## 3. Existing Architecture That Must Be Reused

Do not create alternate frameworks for these concerns.

### 3.1 Page and Routing Layer

Reuse:

- `src/ActionRouter/Actions/Render/PluginAdminPages/PageModeLandingBase.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageConfigureLanding.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageReportsLanding.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageActionsQueueLanding.php`
- `src/Controller/Plugin/PluginNavs.php`
- `src/Modules/Plugin/Lib/NavMenuBuilder.php`

### 3.2 Investigate Data/Renderer Layer

Reuse existing investigate render/data contracts and table actions:

- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByUser.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByIp.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByPlugin.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByTheme.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateByCore.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/InvestigateRenderContracts.php`
- `src/ActionRouter/Actions/Render/PluginAdminPages/InvestigateOverviewRowsBuilder.php`
- `src/Tables/DataTables/LoadData/Investigation/*`

### 3.3 Frontend and Lookup Layer

Reuse:

- `assets/js/app/AppMain.js`
- `assets/js/components/tables/InvestigationTable.js`
- Existing Select2 pattern in `assets/js/components/search/SuperSearchService.js`

Select2 is mandatory for the redesign lookup UX. Do not add an alternative lookup library.

### 3.4 Styles and Status Tokens

Reuse status classes/tokens and existing mode styles:

- `assets/css/shield/_status-colors.scss`
- `assets/css/shield/dashboard.scss`
- `assets/css/shield/investigate.scss`
- `assets/css/shield/reports.scss`

---

## 3.5 Canonical IDs and Data Contracts (Exact)

These IDs are canonical for this wave and must be used consistently in PHP, Twig, JS, and tests.
Each Investigate tile and panel reference must use the canonical subject key for that subject.

Canonical Investigate subject keys:

1. `user`
2. `ip`
3. `plugin`
4. `theme`
5. `core`
6. `live_traffic`
7. `premium_integrations`

Canonical `panel_target` values (must equal subject keys for this wave):

1. `user`
2. `ip`
3. `plugin`
4. `theme`
5. `core`
6. `live_traffic`
7. `premium_integrations`

### Tile payload contract

Each tile payload entry must include:

1. `key` (string, one of canonical keys)
2. `title` (string)
3. `icon_class` (string, bootstrap icon class)
4. `stat_text` (string, compact metric text)
5. `status` (string enum: `good|warning|critical|info|neutral`)
6. `is_enabled` (bool)
7. `is_disabled` (bool, inverse of `is_enabled`)
8. `panel_target` (string, panel identifier)

For this wave, `key` and `panel_target` must be identical values.
For this wave, `is_disabled` must always be the logical inverse of `is_enabled`.

### Panel payload contract

Each panel response must include:

1. `subject_key` (canonical key)
2. `panel_title` (string)
3. `tabs` (ordered list where applicable)
4. `html` (rendered panel body)
5. `error` (null or structured error payload)

If `subject_key` is `premium_integrations`, `html` is not used because no panel opens in this wave.
For `premium_integrations`, the tile contract is fixed as:

1. `is_enabled = false`
2. `is_disabled = true`
3. `status = neutral`
4. no panel payload request on click/keypress

### Legacy route context mapping contract

Old investigate URLs remain valid and map to panel subjects:

1. `PageInvestigateByUser` -> `user` (lookup key `user_lookup`)
2. `PageInvestigateByIp` -> `ip` (lookup key `analyse_ip`)
3. `PageInvestigateByPlugin` -> `plugin` (lookup key `plugin_slug`)
4. `PageInvestigateByTheme` -> `theme` (lookup key `theme_slug`)
5. `PageInvestigateByCore` -> `core`

These mappings are required for deep-link continuity and test coverage.

Landing preselect context contract:

1. subject key parameter resolves to one canonical key
2. lookup parameter uses existing route keys (`user_lookup`, `analyse_ip`, `plugin_slug`, `theme_slug`) where applicable
3. invalid subject keys fall back to neutral Investigate landing state (no panel open)

---

## 4. UX Contract By Mode

### 4.1 Shared Mode Shell Contract

All mode pages in this wave must use:

1. Existing breadcrumb + title shell
2. Mode accent bar
3. Compact top spacing to reduce vertical real estate
4. Shared tile and panel interaction rules

### 4.2 Configure Mode (Tile + Panel)

1. Keep 8 security zone tiles.
2. Keep compact posture summary strip and zone status visibility.
3. Tile click opens panel in-place below the grid (no page nav).
4. Panel shows flat component health list and CTA to full zone settings.
5. Security Grades remains available via sidebar/nav mapping.

Final Configure zone tiles:

1. Security Admin
2. Login Protection
3. Firewall
4. Bots and IPs
5. HackGuard
6. Comments Filter
7. Audit Trail
8. Traffic Monitor

### 4.3 Investigate Mode (7 Tiles)

#### Tile set (final)

1. User
2. IP Address
3. Plugin
4. Theme
5. Core Files
6. Live Traffic
7. Premium Integrations (disabled)

Investigate tile metadata (fixed for this wave):

1. `user` -> label `User`, icon `bi-person`
2. `ip` -> label `IP Address`, icon `bi-globe`
3. `plugin` -> label `Plugin`, icon `bi-plug`
4. `theme` -> label `Theme`, icon `bi-brush`
5. `core` -> label `Core Files`, icon `bi-wordpress`
6. `live_traffic` -> label `Live Traffic`, icon `bi-lightning`
7. `premium_integrations` -> label `Premium Integrations`, icon `bi-stars`

#### Rules

1. Tile click never navigates.
2. One panel open at a time.
3. Selected tile does not resize.
4. Tile card content is compact: icon + title + stat only.
5. User/IP lookups use Select2 with AJAX search and inline load.
6. Plugin/Theme lookups are Select2 searchable selects with auto-load on change.
7. Generic Plugin panel displays generic plugin events and related data only.
8. Premium Integrations tile is visibly disabled and non-interactive in this wave.

#### Explicitly not in tile grid

- Activity Log
- Sessions
- Historical Traffic Log

These remain as sidebar pages and data-dump workspaces.

### 4.4 Reports Mode (Minimal This Wave)

1. Keep current Reports landing information architecture (charts + recent reports).
2. Apply shared shell/accent/layout consistency only.
3. Do not implement Reports tile-panel redesign in this wave.

`Shell/accent/layout consistency` means:

1. mode accent bar present
2. compact top header spacing aligned with other mode landings
3. no tile IA introduction
4. existing reports content blocks remain as current

### 4.5 Actions Queue Mode

1. Keep existing structure and behavior.
2. Apply shared shell/accent consistency only where needed.
3. Do not refactor to tile-panel pattern.

For Actions Queue this wave:

1. preserve current action meter + queue + quick actions layout
2. do not introduce tile grid or subject panel behavior

---

## 5. Navigation and Legacy Route Contract

1. Existing direct Investigate routes (by user/ip/plugin/theme/core) remain valid URLs.
2. Those routes must render the redesigned Investigate landing + preselected subject panel context (no legacy layout fork).
3. Sidebar retains links for Activity Log, Sessions, Traffic Log (historical), Live Traffic, and other existing entries.
4. Breadcrumb behavior remains unchanged (`Shield Security > Mode > Page`).

Investigate sidebar in this wave remains:

1. Investigate (landing)
2. Activity Log
3. Traffic Log (historical)
4. Live Traffic
5. IP Rules
6. Sessions

---

## 6. Interaction and Accessibility Contract

1. Enabled tile controls are keyboard reachable and activatable.
2. Panel close control is keyboard reachable.
3. Panel state changes are deterministic:
   - click active tile -> close panel
   - click different tile -> switch panel
4. Inline action feedback and inline error rendering are required for AJAX actions.
5. Data tables in panel contexts use flat presentation (no nested card wrappers).
6. Tile activation and panel open/close must maintain deterministic focus behavior for keyboard users.
7. Disabled Premium Integrations tile must render `aria-disabled="true"` and must ignore click/Enter/Space activation.

---

## 7. Detailed Investigate Subject Contracts

This section is implementation-prescriptive. Agents must not infer alternate tab structures.

### 7.1 User Subject Panel Contract

Required panel behavior:

1. Empty state shows Select2 lookup and no data tabs.
2. Loaded state shows these tabs exactly: `Overview`, `Sessions`, `Activity`, `Requests`, `IPs`.
3. Tab switch is client-side.
4. Non-overview tabs must lazy-load on first activation and then use cached data for subsequent switches.
5. User lookup uses AJAX-backed Select2 search by username/email/display name.

Required data in `Overview`:

1. Username
2. Display name
3. Email
4. Role
5. Registered date
6. Last login timestamp
7. Last login IP
8. Active session count
9. Offence score
10. Shield tracking status

Required behaviors:

1. Session revoke action is inline and returns row-level feedback.
2. Clicking an IP from user context switches active subject to IP and preloads that IP.

### 7.2 IP Subject Panel Contract

Required panel behavior:

1. Empty state supports Select2 lookup and direct IP entry.
2. Loaded state shows these tabs exactly: `Overview`, `Offences`, `Requests`, `Sessions`, `Actions`.
3. IP lookup supports both known-IP suggestions and direct typed IP submission.

Required data in `Overview`:

1. IP address
2. Country/geolocation
3. ISP/ASN where available
4. First seen timestamp
5. Last request timestamp
6. CrowdSec status
7. Shield offence score

Required action behavior:

1. Block/bypass/remove actions are inline.
2. Success and failure feedback remain inside the Actions tab.

### 7.3 Plugin Subject Panel Contract (Generic Scope)

Required behavior:

1. Select2 searchable plugin selector.
2. Auto-load on selection change.
3. No separate `Load` button.
4. Data source is local installed-plugin list for selector options (no remote lookup required for selector population).
5. Loaded state tab set is fixed: `Overview`, `Events`, `File Status`, `Security Notes`.

Minimum generic event scope:

1. Install/activate/deactivate/update/delete lifecycle events
2. Related plugin security context available in existing data sources

Explicit exclusions:

1. No WooCommerce-specific event model in this wave.
2. No bespoke premium integration event model in this wave.

### 7.4 Theme Subject Panel Contract

Required behavior:

1. Select2 searchable theme selector.
2. Auto-load on selection change.
3. Flat overview and related investigation data consistent with panel shell.
4. Data source is local installed-theme list for selector options (no remote lookup required for selector population).
5. Loaded state tab set is fixed: `Overview`, `File Status`, `Activity`.

### 7.5 Core Files Subject Panel Contract

Required behavior:

1. No lookup step.
2. Immediate load of core integrity context.
3. Flat data presentation, no nested card stack.
4. Loaded state tab set is fixed: `Overview`, `File Status`, `Scan History`.

### 7.6 Live Traffic Subject Panel Contract

Required behavior:

1. Tile label is `Live Traffic`.
2. Panel provides immediate live/request stream context.
3. Historical Traffic Log remains separate in sidebar navigation.
4. This panel is single-view in this wave (no tab strip).

### 7.7 Premium Integrations Tile Contract

Required behavior:

1. Tile is visible in grid.
2. Tile is disabled and non-interactive.
3. No panel opens from this tile in this wave.

---

## 8. Configure Panel Contract

Configure landing remains zone-based with 8 zone tiles. Each zone panel must provide:

1. Zone status in header.
2. Flat component list with per-component status.
3. CTA to full zone settings page.
4. No nested card wrappers inside panel.

---

## 9. Data, Loading, and Error Contract

1. Tile click opens panel shell immediately.
2. Lookup or selection triggers AJAX load.
3. Tab content must lazy-load once per panel context and then be cached.
4. Failed loads render inline errors with retry.
5. Lookup validation errors render inline in panel context.
6. No full page navigation is triggered by tile click or tab click.
7. Only explicit deep-action CTAs (`Configure Settings`, `Open full log`, sidebar nav) perform navigation.

---

## 10. Prototype Alignment Notes (Current Scope)

Prototypes are demonstrations and must be interpreted with the locked decisions above.

For this wave, agents must treat these as non-negotiable corrections:

1. Investigate tile set is 7 tiles (not previous variants with Activity/Sessions/historical Traffic as tiles).
2. Live traffic naming is explicit (`Live Traffic`).
3. Premium Integrations replaces WooCommerce tile and is disabled.
4. Tile content is compact (no per-tile descriptive paragraph in production UI), even if a prototype still shows descriptive helper text.
5. Reports tile-panel prototype remains future-facing and out of scope for this wave.

---

## 11. Test and Verification Contract

At minimum, implementation must update/add tests for:

1. mode landing rendering contracts
2. investigate landing subject payload and disabled premium tile behavior
3. legacy route to redesigned panel-context rendering
4. nav/breadcrumb continuity
5. no regression in existing investigation table action behavior

Relevant suites already exist in `tests/Unit` and `tests/Integration` for operator modes and investigate routes. Update those suites rather than creating isolated ad-hoc test pathways.

---

## 12. Out of Scope For This Wave

1. WooCommerce-specific event ingestion/modeling and bespoke WooCommerce panel UX
2. Generic premium integration implementations beyond placeholder tile
3. Reports full tile-panel redesign
4. Actions Queue structural redesign

No agent may pull any out-of-scope item into this wave without explicit owner approval.
