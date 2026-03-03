# Shield - User Journeys (Locked)

**Date:** 2026-03-03  
**Status:** Decision-locked  
**Audience:** Implementation agents  
**Companion files:** `REDESIGN-OVERVIEW.md`, `IMPLEMENTATION-ORCHESTRATION.md`, and the three prototype HTML files in this directory

---

## How To Use This File

Each journey is prescriptive. Implement exactly what is stated. Do not improvise interaction patterns.

Global context:

1. Shield runs inside WordPress admin.
2. Breadcrumb starts with `Shield Security` and returns to mode selector.
3. In Configure and Investigate, tile clicks open an inline panel and do not navigate.
4. Exactly one panel is open at a time.

Canonical Investigate subject keys used in implementation and tests:

1. `user`
2. `ip`
3. `plugin`
4. `theme`
5. `core`
6. `live_traffic`
7. `premium_integrations`

---

## Journey 1 - "Do I have urgent security problems now?"

**Mode:** Actions Queue  
**Intent:** quick triage

1. User opens `Shield Security` from WP admin.
2. On mode selector, user sees Actions Queue card state (`items` or `all clear`).
3. If issues exist, user enters Actions Queue mode.
4. Landing shows action meter + NeedsAttentionQueue + quick actions.
5. User opens scan results, resolves items, returns to selector.
6. Selector card shows all-clear state.

Detailed state assertions:

1. Actions Queue card on selector includes clear severity signal without requiring mode entry.
2. Actions Queue landing keeps current IA: action meter, queue list, quick actions.
3. Mode accent bar is visible on Actions Queue pages.

Edge cases:

1. Queue already clear -> user does not need to enter Actions mode.
2. No scan data -> CTA leads user to run scan first.

---

## Journey 2 - "Improve configuration posture"

**Mode:** Configure  
**Intent:** find weak zones and fix them

1. User enters Configure from selector.
2. Configure landing shows compact posture strip and 8 zone tiles.
3. Tile content is compact and status-driven (icon/title/status/stat).
4. User clicks a weak zone tile; panel opens inline below grid.
5. Panel lists zone components and status in flat layout.
6. User clicks `Configure [Zone] Settings` and navigates intentionally to full settings page.
7. After saving settings, returning to landing reflects new zone status.

Edge cases:

1. Clicking active tile closes panel.
2. Clicking another tile switches panel context.
3. If all zones are good, user can still open any tile for detail.

Detailed state assertions:

1. Configure tile content is compact and status-led.
2. Configure tile click never navigates.
3. Configure panel includes status header + component status list + explicit settings CTA.
4. Security Grades remains available from sidebar navigation in this wave.

---

## Journey 3 - "Investigate suspicious IP activity now"

**Mode:** Investigate  
**Intent:** inspect and act on IP behavior

### Investigate landing tile set (final)

1. User
2. IP Address
3. Plugin
4. Theme
5. Core Files
6. Live Traffic
7. Premium Integrations (disabled)

Activity Log, Sessions, and historical Traffic Log are sidebar pages, not tiles.

### Steps

1. User enters Investigate mode.
2. User clicks `IP Address` tile.
3. Panel opens inline; tile grid remains visible and unchanged in size.
4. User enters/selects IP via Select2 lookup.
5. AJAX loads IP context into panel tabs (overview/offences/requests/sessions/actions).
6. User blocks or bypasses IP from inline actions.
7. Inline success or failure feedback appears in the same panel.

Edge cases:

1. IP has no records -> panel shows no-record message and still allows security actions.
2. Invalid IP -> inline validation error.
3. If user switches to another tile while panel is open, panel context switches immediately.

Detailed state assertions:

1. IP panel empty state shows lookup-only shell first.
2. IP panel loaded tabs are exactly: `Overview`, `Offences`, `Requests`, `Sessions`, `Actions`.
3. IP actions are inline and update visible state without full-page notices.
4. IP tile remains full-size while active; grid stays visible.

---

## Journey 4 - "Investigate generic plugin activity"

**Mode:** Investigate  
**Intent:** inspect plugin lifecycle/activity events for a selected plugin

1. User clicks `Plugin` tile.
2. Panel opens with Select2 searchable plugin selector.
3. On selection change, panel auto-loads (no separate load button).
4. Panel displays generic plugin investigation content and event-focused tabs/data.
5. User reviews events such as install/update/deactivate/delete-related activity and associated security context.

Rules:

1. This wave does not implement WooCommerce-specific event model in this panel.
2. This panel is generic plugin scope only.

Detailed state assertions:

1. Plugin selector is searchable and auto-loads on selection.
2. Plugin panel does not require a separate submit button.
3. Plugin panel uses generic event model and excludes WooCommerce-specific presentation.

---

## Journey 5 - "Investigate suspicious user activity from a deep link"

**Mode:** Investigate  
**Intent:** inspect a user quickly and pivot to related IP context

1. User opens deep link into Investigate with User panel preselected and preloaded.
2. Landing still shows the 7-tile grid and highlights `User` tile.
3. User reviews user overview and tabs (sessions/activity/requests/ips).
4. User revokes suspicious session inline.
5. User clicks suspicious IP from user context.
6. Panel switches to `IP Address` subject with that IP preloaded.

Edge cases:

1. Deep-link user not found -> inline not-found state.
2. User exists but no Shield records -> user identity shown, Shield data tabs show empty-state messaging.

Detailed state assertions:

1. Deep-link opens redesigned Investigate landing, not legacy standalone investigate page layout.
2. User panel loaded tabs are exactly: `Overview`, `Sessions`, `Activity`, `Requests`, `IPs`.
3. Clicking an IP in user context changes active tile to `IP Address` and preloads selected IP.

---

## Journey 6 - "Monthly reporting check"

**Mode:** Reports  
**Intent:** review trends and jump to investigation

1. User enters Reports mode from selector.
2. Reports landing remains charts + recent reports in this wave.
3. Shared shell styling is consistent with other modes.
4. User identifies spike and pivots to Investigate for deeper analysis.

Rule:

1. Reports tile-panel redesign is not part of this wave.

Detailed state assertions:

1. Reports current IA remains intact in this wave.
2. Shared shell/spacing/accent standards still apply.

---

## Journey 7 - "Premium integrations placeholder behavior"

**Mode:** Investigate  
**Intent:** communicate future capability without false affordance

1. User sees `Premium Integrations` tile on Investigate landing.
2. Tile is visibly disabled.
3. Tile is non-interactive and does not open a panel.
4. Tile text indicates upcoming capability, not current functionality.

Detailed state assertions:

1. Premium Integrations tile appears in grid position with disabled affordance.
2. Keyboard and pointer interactions do not open panel for this tile.
3. Tile renders disabled semantics (`aria-disabled="true"`) in UI markup.

---

## Cross-Journey Rules (Non-Negotiable)

### Tile and Panel Behavior

1. Tile click never causes navigation on Configure/Investigate landings.
2. Only one panel can be open.
3. Selected tile remains full size.
4. Panel includes a close control.
5. Tile visuals are compact; no per-tile descriptive paragraph.

### Data and Loading

1. Initial tile interaction opens panel shell immediately.
2. Lookup selection/submit triggers AJAX data load.
3. Tab switches are client-side.
4. Tabs that are not yet loaded must lazy-load once on first activation, then use cached data.
5. Inline actions (block/revoke/etc.) return inline feedback.
6. User panel tab set is fixed: `Overview`, `Sessions`, `Activity`, `Requests`, `IPs`.
7. IP panel tab set is fixed: `Overview`, `Offences`, `Requests`, `Sessions`, `Actions`.
8. Plugin panel is generic plugin events scope only in this wave.
9. Plugin panel tab set is fixed: `Overview`, `Events`, `File Status`, `Security Notes`.
10. Theme panel tab set is fixed: `Overview`, `File Status`, `Activity`.
11. Core panel tab set is fixed: `Overview`, `File Status`, `Scan History`.
12. Live Traffic panel is single-view with no tab strip in this wave.
13. Investigate tile payload always includes `key`, `panel_target`, `is_enabled`, and `is_disabled`.
14. In this wave, `key` and `panel_target` are identical canonical subject keys.
15. `premium_integrations` must remain disabled (`is_enabled=false`, `is_disabled=true`) and no panel request is triggered.

### Accessibility

1. Enabled tiles are keyboard reachable and activatable.
2. Close control and tabs are keyboard reachable.
3. Focus behavior remains stable after panel open/close and context switch.

### Navigation

1. Sidebar pages remain available for legacy data-dump workflows (Activity Log, Sessions, historical Traffic Log).
2. Breadcrumb and sidebar back-link behavior remain unchanged.

---

## No-Interpretation Checklist

Treat these as hard implementation assertions:

1. Investigate landing uses exactly 7 tiles in this wave.
2. `Live Traffic` is a tile; historical `Traffic Log` is sidebar-only.
3. `Premium Integrations` is disabled and opens no panel.
4. Activity Log and Sessions are sidebar pages, not Investigate tiles.
5. Plugin panel does not include WooCommerce-specific modeling in this wave.
6. Reports stays on current IA (charts + recent reports) in this wave.
7. Actions Queue stays on current IA in this wave.

---

## Error States Reference

1. User lookup no result -> inline no-result message.
2. Lookup AJAX failure -> inline retry affordance.
3. IP block action failure -> inline action error and retry.
4. Session revoke failure -> inline row-level error and retry.
5. AJAX timeout -> inline timeout message with retry.
6. Plugin dataset empty -> clear no-plugin state.
