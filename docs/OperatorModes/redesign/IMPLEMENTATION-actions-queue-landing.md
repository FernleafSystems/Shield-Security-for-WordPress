# Implementation Plan: Actions Queue Landing Page Redesign

**Prototype reference:** `docs/OperatorModes/redesign/prototype-actions-queue-D-inline-results.html`
**Date:** 2026-03-05

---

## 1. Overview

This document specifies the redesign of the Actions Queue operator mode landing page. The page is where users review active security issues — scan findings and maintenance tasks — and take action on them.

**What to implement from the prototype:**
- The severity strip (status summary bar at the top)
- The zone tile grid (Scans tile + Maintenance tile — 2 tiles only, NOT 3)
- The Scans expansion panel with Summary tab + Scan Results tab
- The Maintenance expansion panel with summary list + action links
- The All Clear state when no issues exist

**What to IGNORE in the prototype:**
- The sidebar/nav — this is handled separately by the nav sidebar redesign
- The WP admin chrome (admin bar, WP sidebar) — prototype scaffolding only
- The prototype controls bar and annotation boxes — developer-only UI
- The "Configuration" tile — remove entirely; not based on real data in this context
- The simulated table rows in the Scan Results tab — the real implementation embeds the existing scan results DataTables

---

## 2. Existing Architecture

### 2.1 Mode Landing Base Pattern

All operator mode landing pages extend `PageModeLandingBase` (`src/ActionRouter/Actions/Render/PluginAdminPages/PageModeLandingBase.php`), which extends `BasePluginAdminPage`. This base class provides:

- **Mode shell** (`mode_shell`): accent bar colour, header density, interactivity flag
- **Mode tiles** (`mode_tiles`): normalized array of clickable tile cards
- **Mode panel** (`mode_panel`): expansion panel with open/close state and target tracking
- **Standard hooks**: `getLandingTitle()`, `getLandingSubtitle()`, `getLandingIcon()`, `getLandingMode()`, `getLandingTiles()`, `getLandingPanel()`, `getLandingContent()`, `getLandingFlags()`, `getLandingHrefs()`, `getLandingStrings()`, `getLandingVars()`
- **Template**: each landing page has its own Twig template extending `base_inner_page.twig`

The existing modes (Investigate, Configure, Reports) already use this tile + panel pattern. The Investigate landing (`PageInvestigateLanding`) is the most directly relevant example — it uses `mode_tiles` for a clickable subject grid and `mode_panel_shell.twig` for expansion panels that load content inline.

### 2.2 Current Actions Queue Landing

**PHP:** `PageActionsQueueLanding` (`src/ActionRouter/Actions/Render/PluginAdminPages/PageActionsQueueLanding.php`)
**Template:** `templates/twig/wpadmin/plugin_pages/inner/actions_queue_landing.twig`

The current page renders:
1. A `MeterCard` hero widget (the circular score meter)
2. An all-clear card (when queue is empty)
3. The `NeedsAttentionQueue` widget (rendered as pre-built HTML via `render_output`)
4. A "Quick Actions" card with links to Scan Results and Run Manual Scan

This is simpler than the other mode landing pages — it doesn't use `mode_tiles` or `mode_panel` at all. The redesign will bring it in line with the tile + panel pattern used by Investigate/Configure/Reports.

### 2.3 NeedsAttentionQueue Widget

`NeedsAttentionQueue` (`src/ActionRouter/Actions/Render/Components/Widgets/NeedsAttentionQueue.php`) renders a self-contained widget. Its `getRenderData()` returns:

- `flags.has_items` — whether any issues exist
- `strings.status_strip_text` — e.g. "7 issues need your attention"
- `strings.status_strip_subtext` — e.g. "Last scan: 12 minutes ago"
- `strings.status_strip_icon_class` — Bootstrap icon class for the status
- `vars.summary` — the summary contract (see nav sidebar implementation doc Section 2.6)
- `vars.zone_groups` — items grouped by zone, each with `slug`, `label`, `icon_class`, `severity`, `total_issues`, and `items[]`
- `vars.zone_chips` — all-clear chips for each zone

### 2.4 AttentionItemsProvider — Zone Grouping

`AttentionItemsProvider` (`src/ActionRouter/Actions/Render/Components/Widgets/AttentionItemsProvider.php`) builds the queue items. Currently it groups ALL items — both scan items and maintenance items — under `zone => 'scans'`. There is no separate "Maintenance" zone.

The prototype shows two distinct tiles: **Scans** and **Maintenance**. To support this, the implementation needs to introduce a `'maintenance'` zone for maintenance items. The cleanest approach is to change `buildMaintenanceItems()` to use `zone => 'maintenance'` instead of `zone => 'scans'`. The items that belong in maintenance are already identified by `MAINTENANCE_COMPONENT_SLUGS`:
- `wp_updates`
- `wp_plugins_updates`
- `wp_themes_updates`
- `wp_plugins_inactive`
- `wp_themes_inactive`

The `NeedsAttentionQueue::zoneDataFor()` method will need a fallback label/icon for the new `'maintenance'` zone slug, or a new zone entry should be added to the `ZoneRenderDataBuilder`.

### 2.5 Existing Scan Results Tables

The scan results are rendered by `PageScansResults` (`src/ActionRouter/Actions/Render/PluginAdminPages/PageScansResults.php`), which renders five sub-sections as HTML strings:
- `content.section.plugins` — Plugins table
- `content.section.themes` — Themes table
- `content.section.wordpress` — WordPress core files table
- `content.section.malware` — Malware table
- `content.section.filelocker` — File Locker table

Each is rendered via a component class (e.g. `Plugins::class`, `Malware::class`, etc.) in `src/ActionRouter/Actions/Render/Components/Scans/Results/`.

The existing scan results page template is at `templates/twig/wpadmin/plugin_pages/inner/scan_results.twig`.

For the Actions Queue redesign, the Scan Results tab will embed these same tables. The simplest approach is to render `PageScansResults` as a sub-action (similar to how the dashboard renders `PageOperatorModeLanding`) and inject the output into the panel's results tab. Alternatively, render the individual section components directly. The agent should investigate `PageScansResults` and its template to determine the best integration approach — the goal is to reuse the existing DataTable rendering without duplicating it.

---

## 3. Page Specification

### 3.1 Page Structure (Top to Bottom)

When the user navigates to the Actions Queue mode, the main content area shows:

1. **Mode accent bar** — 4px red bar (`#c62f3e`, status `critical`) rendered by `base_inner_page.twig` via `mode_shell.accent_status`
2. **Page title** — "Actions Queue" (compact header density)
3. **Page subtitle** — "Review active issues and run the next action quickly."
4. **Severity strip** — summary status bar
5. **Zone tile grid** — 2 tiles: Scans, Maintenance
6. **Expansion panel** — shows when a tile is clicked (Scans panel or Maintenance panel)
7. **All-clear state** — replaces tiles + panel when queue is empty

### 3.2 Severity Strip

A horizontal card at the top of the content area showing overall queue status.

**Structure:**
- Left: severity chip (rounded pill with icon + text)
  - When issues exist: red background, "Action Required" with exclamation icon
  - When all clear: green background, "All Clear" with shield-check icon
- Centre: summary info
  - "Queue Status" label (uppercase, small, muted)
  - Summary text, e.g. "2 critical · 3 warnings · 7 items total"
  - "Last scan: 12 minutes ago" subtext
- The chip colour matches the worst severity across all items

**Data source:** Use the `NeedsAttentionQueue` render data. The `vars.summary` provides `severity`, `total_items`, and `has_items`. The `strings.status_strip_subtext` provides the "Last scan" text. For the critical/warning breakdown counts, iterate `vars.zone_groups` → sum items by severity.

**Styling:** See prototype `.severity-strip`, `.severity-chip`, `.severity-info` classes. White card, subtle shadow, rounded corners. Match existing Shield card styling (`shield-card` pattern).

### 3.3 Zone Tile Grid

Two clickable tiles displayed in a responsive grid below the severity strip.

| Tile | Icon | Data Source |
|------|------|-------------|
| **Scans** | `bi-shield-exclamation` | Scan items from `zone_groups` where zone is `'scans'` |
| **Maintenance** | `bi-wrench` | Maintenance items from `zone_groups` where zone is `'maintenance'` |

Each tile shows:
- Icon + severity badge (Critical / Warning / Good)
- Tile name
- Summary stat (e.g. "4 issues · 2 critical" or "3 items" or "All clear")

**Tile behaviour:**
- Clicking a tile opens its expansion panel below the grid
- The active tile gets a coloured border matching its severity (same as prototype: `active-critical`, `active-warning`, `active-good`)
- Only one panel is open at a time — clicking a different tile closes the current one

**This follows the same tile + panel pattern as the Investigate landing page.** Use the `mode_tiles` and `mode_panel` contract from `PageModeLandingBase`. The tiles should be provided via `getLandingTiles()` and the panel state via `getLandingPanel()`. The Twig template should use `data-mode-tile` and `data-mode-panel-target` attributes to connect tiles to panels, exactly as `investigate_landing.twig` does.

**When a zone has no items**, its tile should show a "Good" severity badge with "All clear" stat text. The tile should still be visible but won't open a panel.

### 3.4 Scans Expansion Panel

When the Scans tile is clicked, a panel opens with **two tabs**: Summary and Scan Results.

#### Summary Tab

A compact list of scan finding categories with severity indicators:

Each row shows:
- Severity pip (coloured dot: red for critical, amber for warning)
- Category label (e.g. "Malware", "Vulnerable Assets", "Plugin Files", "WP Core Files")
- Count badge (coloured background matching severity)
- Description text (e.g. "PHP malware detected in theme files")

At the bottom: a "View full scan results table →" link that switches to the Scan Results tab.

**Data source:** The items for this list come from the scan items in the `NeedsAttentionQueue` render data — specifically the items within the `'scans'` zone group. Each item has `label`, `count`, `severity`, and `description`.

#### Scan Results Tab

This tab embeds the **existing scan results tables** — the same DataTables currently rendered by `PageScansResults`. This is NOT a new table implementation; it reuses the existing scan result components.

The tab count badge shows the total number of scan result items.

**Integration approach:** Render the scan results content server-side and inject it into the panel body, OR load it dynamically via AJAX using the same `render_action` pattern that `PageInvestigateLanding` uses for its panels. The agent should evaluate which approach fits best — server-side is simpler but heavier on initial page load; AJAX is lighter but requires more wiring. Given that the scan results are already a full sub-page, AJAX loading (render on demand when the Results tab is selected) is likely the better approach.

### 3.5 Maintenance Expansion Panel

When the Maintenance tile is clicked, a simpler panel opens (no tabs).

**Content:**
- Summary list of maintenance items (same format as the Scans summary tab): severity pip + label + count + description
- Action footer with links to WordPress Updates page and Manage Plugins page

**Data source:** Maintenance items from the `NeedsAttentionQueue` render data — items within the `'maintenance'` zone group.

### 3.6 All-Clear State

When the queue is empty (`flags.has_items` is false):

- The severity strip shows "All Clear" with green styling
- The zone tile grid is **hidden**
- An all-clear card is shown instead, containing:
  - Shield-check icon (large, green)
  - "All security zones are clear" title
  - "Shield is actively protecting your site. Nothing requires your action." subtitle
  - Zone confirmation chips (green pills for each zone: "Scans", "Maintenance")

**Data source:** Use the existing `NeedsAttentionQueue` all-clear rendering data. The `strings.all_clear_title`, `strings.all_clear_subtitle`, and `vars.zone_chips` already provide this.

---

## 4. Implementation Approach

### 4.1 Align with the Mode Landing Pattern

The key architectural change is to make `PageActionsQueueLanding` use the same tile + panel pattern as the other mode landing pages. Currently it renders a monolithic `NeedsAttentionQueue` widget. The redesign should:

1. Keep `PageActionsQueueLanding` extending `PageModeLandingBase` (it already does)
2. Override `isLandingInteractive()` to return `true` — this is **required** for the tile ↔ panel JavaScript (`ModePanelStateController`) to activate. It checks `data-mode-interactive="1"` on the shell element. Currently `PageActionsQueueLanding` does NOT override this, so it defaults to `false`.
3. Implement `getLandingTiles()` to return Scans and Maintenance tiles
4. Implement `getLandingPanel()` to track which tile/panel is active
5. Implement `getLandingVars()` to provide the severity strip data, zone items, scan results content, and all-clear data
6. Replace the Twig template with one that renders the severity strip, tile grid, and expansion panels

### 4.2 Zone Separation

Change `AttentionItemsProvider::buildMaintenanceItems()` to use `zone => 'maintenance'` instead of `zone => 'scans'`. This is a one-line change at line 354 of `AttentionItemsProvider.php`.

Then ensure `NeedsAttentionQueue::zoneDataFor()` handles the `'maintenance'` zone slug (provide a label and icon). The simplest approach: add a case for `'maintenance'` in the fallback logic, or add the zone to whatever zone registry `ZoneRenderDataBuilder` uses.

**Impact check:** The existing `NeedsAttentionQueue` widget template (`needs_attention_queue.twig`) iterates `zone_groups` to render zone cards. After this change, it will show two zone groups instead of one. Verify this doesn't break the existing widget rendering elsewhere (e.g. on the dashboard). If it does, the zone separation may need to be handled at the `PageActionsQueueLanding` level instead, splitting the items after retrieval.

### 4.3 Reuse mode_panel_shell.twig

The expansion panels should use the existing `mode_panel_shell.twig` component (`templates/twig/wpadmin/components/page/mode_panel_shell.twig`). This gives you:
- Accent bar coloured by severity status
- Title bar with close button
- Body area for content
- `data-mode-panel` attributes for the tile ↔ panel JavaScript interaction

The Scans panel body will need internal tab navigation (Summary + Results). This is new — the existing `mode_panel_shell.twig` doesn't include tabs. You'll need to add tab markup inside the panel body. The tab styling can follow the prototype's `.panel-tabs` / `.panel-tab` pattern, but should use Bootstrap 5 nav-tabs styling where possible to stay consistent with the rest of the plugin.

### 4.4 Embedding Scan Results

For the Scan Results tab inside the Scans panel, the recommended approach:

1. **Don't render scan results on initial page load.** It's heavy and the user may never open that tab.
2. **Load via AJAX when the Results tab is first clicked.** Use the same `render_action` pattern that Investigate uses — `ActionData::BuildAjaxRender()` to create a render action payload, then the mode-panel JavaScript fetches and injects the HTML.
3. **The render target** is `PageScansResults::class` (or its individual section components). The agent should look at how `PageInvestigateLanding::buildPanelRenderActionData()` constructs the AJAX render payload and follow the same pattern.
4. Once loaded, cache the HTML in the DOM so switching between Summary and Results tabs doesn't re-fetch.

### 4.5 JavaScript Interaction

The tile ↔ panel open/close behaviour should use the **existing mode panel JavaScript** that drives the Investigate and Configure landing pages. This JS listens for clicks on `[data-mode-tile]` elements and shows/hides `[data-mode-panel]` elements based on `data-mode-panel-target` matching.

The internal Scans tab switching (Summary ↔ Results) needs minimal new JS: toggle visibility of the two tab content areas and update the active tab styling. The scan results AJAX load should fire on first tab activation.

The agent should study the existing mode-panel JS (likely in `assets/js/` — search for `mode-tile` or `mode-panel` event handling) to understand the existing wiring before adding new behaviour.

---

## 5. Template Structure

The new `actions_queue_landing.twig` should follow this approximate structure:

```twig
{% extends '/wpadmin/plugin_pages/base_inner_page.twig' %}

{% block inner_page_body %}
    {# Severity Strip #}
    <div class="severity-strip ...">
        {# severity chip + summary info + last scan time #}
    </div>

    {% if not flags.queue_is_empty %}
        {# Zone Tile Grid #}
        <div class="..." data-mode-tiles="1">
            {# Scans tile + Maintenance tile #}
            {# Use data-mode-tile, data-mode-tile-key, data-mode-panel-target attributes #}
        </div>

        {# Scans Panel #}
        {% include '/wpadmin/components/page/mode_panel_shell.twig' with {
            panel: { ... scans panel config ... },
            strings: strings
        } only %}

        {# Maintenance Panel #}
        {% include '/wpadmin/components/page/mode_panel_shell.twig' with {
            panel: { ... maintenance panel config ... },
            strings: strings
        } only %}
    {% else %}
        {# All-Clear Card #}
        <div class="all-clear-card ...">
            {# icon, title, subtitle, zone chips #}
        </div>
    {% endif %}
{% endblock %}
```

The internal tab content for the Scans panel (Summary list + Scan Results embed) goes in the `panel.body` passed to `mode_panel_shell.twig`.

---

## 6. Styling

### 6.1 Reuse Existing Styles

Most of the UI components in this redesign already have established styling in the codebase:

- **Mode accent bar**: handled by `base_inner_page.twig` + existing SCSS
- **Shield card / mode panel**: `mode_panel_shell.twig` uses `shield-card` and `shield-card-accent` — already styled
- **Tile grid**: the Investigate landing uses a subject grid — reuse that grid pattern or adapt it for 2 tiles
- **Status badges**: `.shield-badge` with `.badge-critical`, `.badge-warning`, `.badge-good` — already exist

### 6.2 New Styles Needed

Minimal new CSS is required:

- **Severity strip**: the top summary bar. Style it as a `shield-card` variant with horizontal flex layout. See prototype `.severity-strip` for dimensions and spacing.
- **Severity chip**: the coloured pill inside the strip. See prototype `.severity-chip` with `.is-good`, `.is-warning`, `.is-critical` variants.
- **Summary list rows**: the item rows inside the panels. See prototype `.summary-list` and `.summary-row`. This is a simple flex row with pip + label + count + description.
- **Tab navigation inside panels**: for the Scans panel's Summary/Results tabs. Use Bootstrap 5 `nav nav-tabs` if possible, otherwise add minimal custom tab styling. See prototype `.panel-tabs` and `.panel-tab` for reference.

Add these styles to the existing SCSS — either in `assets/css/components/` as a new `actions_queue.scss` partial, or within the existing mode landing SCSS files if there's a shared one. The agent should check what SCSS files the other mode landing pages use and follow that pattern.

### 6.3 Zone Tile Styling

The zone tiles in the prototype have a 3px severity accent bar at the top (via `::before` pseudo-element) and hover lift. These match the existing investigate subject card pattern. The main differences:

- Tiles show a severity badge (not a stat text)
- Active tiles get a coloured border matching severity
- Tiles are wider and fewer (2 vs the 5+ subjects in Investigate)

Use `grid-template-columns: repeat(auto-fill, minmax(185px, 1fr))` as in the prototype, or a simpler `1fr 1fr` for the 2-tile layout.

---

## 7. Data Contract

The PHP renderer (`PageActionsQueueLanding`) should provide these template variables:

```php
// From getLandingVars():
'vars' => [
    'severity_strip' => [
        'severity'      => string,  // 'good', 'warning', 'critical'
        'has_items'     => bool,
        'total_items'   => int,
        'critical_count' => int,
        'warning_count' => int,
        'icon_class'    => string,
        'strip_text'    => string,  // "X issues need your attention"
        'subtext'       => string,  // "Last scan: 12 minutes ago"
    ],
    'zones' => [
        'scans' => [
            'slug'        => 'scans',
            'label'       => 'Scans',
            'icon_class'  => string,
            'severity'    => string,
            'total_issues' => int,
            'items'       => [ ... ],  // individual scan items
        ],
        'maintenance' => [
            'slug'        => 'maintenance',
            'label'       => 'Maintenance',
            'icon_class'  => string,
            'severity'    => string,
            'total_issues' => int,
            'items'       => [ ... ],  // individual maintenance items
        ],
    ],
    'all_clear' => [
        'title'      => string,
        'subtitle'   => string,
        'icon_class' => string,
        'zone_chips' => [ ... ],
    ],
],
```

This is a suggested shape. The agent should adapt it based on what the existing `NeedsAttentionQueue` render data already provides — much of this data is already computed. The goal is to restructure the existing data for the new template layout, not to compute new data.

---

## 8. Key Decisions for the Agent

The implementation document intentionally leaves some decisions to the implementing agent. The agent should investigate the codebase and make informed choices on:

1. **Zone separation strategy**: Change `AttentionItemsProvider` to use `zone => 'maintenance'`, or split items at the `PageActionsQueueLanding` level. Check whether changing the zone affects other consumers of `NeedsAttentionQueue`.

2. **Scan results embedding**: Server-side render on page load vs AJAX load on tab click. Consider page weight and the existing AJAX panel loading infrastructure.

3. **Tab implementation**: Bootstrap 5 native tabs vs custom tab markup. Check what tab patterns exist elsewhere in the plugin.

4. **Tile grid styling**: Reuse investigate subject grid CSS, create new actions-specific grid CSS, or share a common mode-tile grid. Check the existing SCSS structure.

5. **Whether to keep the MeterCard hero widget**: The current page has a circular meter card at the top. The prototype replaces it with the severity strip. The agent should remove the MeterCard and replace it with the severity strip.

---

## 9. Summary Checklist

### Severity Strip
- [ ] Horizontal card with severity chip + summary + last scan time
- [ ] Severity chip colour matches worst severity (critical/warning/good)
- [ ] Shows "All Clear" state when queue is empty
- [ ] Data from NeedsAttentionQueue summary

### Zone Tiles
- [ ] 2 tiles only: Scans and Maintenance (NOT 3 — no Configuration tile)
- [ ] Each tile shows icon, name, severity badge, summary stat
- [ ] Tiles use mode_tiles contract from PageModeLandingBase
- [ ] Clicking a tile opens its expansion panel
- [ ] Active tile highlighted with severity-coloured border

### Scans Panel
- [ ] Opens when Scans tile is clicked
- [ ] Summary tab: severity-grouped list of scan items (malware, vulnerabilities, file changes, etc.)
- [ ] Scan Results tab: embeds existing scan results DataTables
- [ ] Tab count badge on Scan Results tab
- [ ] "View full scan results →" link in Summary tab switches to Results tab
- [ ] Uses mode_panel_shell.twig

### Maintenance Panel
- [ ] Opens when Maintenance tile is clicked
- [ ] Summary list of maintenance items (WP updates, plugin updates, inactive plugins, etc.)
- [ ] Action footer with links to WordPress Updates + Plugin Management
- [ ] Uses mode_panel_shell.twig

### All-Clear State
- [ ] Shown when queue is empty
- [ ] Hides tile grid and panels
- [ ] Shows all-clear card with icon, title, subtitle, zone chips
- [ ] Zone chips: "Scans" and "Maintenance"

### Architecture
- [ ] PageActionsQueueLanding uses getLandingTiles() for tile data
- [ ] Reuses mode_panel_shell.twig for expansion panels
- [ ] Follows same tile + panel pattern as Investigate/Configure/Reports
- [ ] Zone separation: scan items vs maintenance items grouped into distinct zones

### Files to Modify
- [ ] `src/ActionRouter/Actions/Render/PluginAdminPages/PageActionsQueueLanding.php` — restructure to provide tile/panel/strip data
- [ ] `templates/twig/wpadmin/plugin_pages/inner/actions_queue_landing.twig` — rewrite template
- [ ] `src/ActionRouter/Actions/Render/Components/Widgets/AttentionItemsProvider.php` — zone separation (maintenance items get `zone => 'maintenance'`)
- [ ] `src/ActionRouter/Actions/Render/Components/Widgets/NeedsAttentionQueue.php` — handle `'maintenance'` zone in `zoneDataFor()`
- [ ] SCSS — severity strip, zone tiles, summary list, panel tab styling
- [ ] Possibly JS — tab switching within Scans panel, AJAX scan results loading

---

## 10. Post-Redesign Simplification Follow-Up (2026-03-05)

- [x] Extract Actions Queue payload-to-view mapping into a dedicated builder class
- [x] Centralize Actions zone metadata (scans/maintenance labels + icons) in `PluginNavs`
- [x] Split `ScansResults.js` table visibility/reflow handling into a focused helper
- [x] Deduplicate Actions Queue summary-row Twig markup into a shared partial
- [x] Add a deferred TODO note for optional scan-results lazy loading (no runtime behavior change)
