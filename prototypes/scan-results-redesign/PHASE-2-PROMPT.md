# Phase 2: Detail Row Expand/Collapse JavaScript Controller

## Status

- Completed on 2026-03-09.

## Goal

Add the JavaScript controller that makes the Phase 1 detail row expand/collapse and Cancel button work. This phase creates one new JS file, modifies two existing files, and touches one Twig template to remove a temporary workaround from Phase 1.

By the end of this phase, clicking an expandable detail row should toggle its connected expansion body open/closed, with accordion behaviour (only one expansion open at a time per container), keyboard accessibility (Enter/Space), and Bootstrap tooltip initialization for expansion content.

**Prerequisite:** Phase 1 must be complete. The following files from Phase 1 must exist:
- `templates/twig/wpadmin/components/page/shield_detail_row.twig`
- `templates/twig/wpadmin/components/page/shield_detail_expansion.twig`
- `assets/css/shield/_shield-detail-components.scss` (with `.is-expanded` and `.is-open` classes defined)

**Reference prototype:** `prototypes/scan-results-redesign/prototype-h-final-spec.html` — the `toggleExpand()` and `collapseExpand()` functions (near the end of the file) show the target behaviour.

---

## Important Context

### JavaScript Architecture

This plugin uses a centralized event delegation system. All click/keyboard handlers go through a global `ShieldEventsHandler` instance — **never** inline `onclick` attributes (except as a temporary Phase 1 workaround we're now removing).

**Key globals (available at runtime):**
- `shieldEventsHandler_Main` — the global event handler (instance of `ShieldEventsHandler`)
- `shieldAppMain` — the main app instance (instance of `AppMain`)

**Event handler registration pattern** (from `ShieldEventsHandler`):
```javascript
// Signature: add_Click( cssSelector, callback, suppress )
// - cssSelector: any CSS selector — uses evt.target.closest(selector) internally
// - callback: receives (matchedElement, event)
// - suppress: boolean — if true, calls evt.preventDefault() and returns false to stop further handlers
//   For click events, the DEFAULT is true (it suppresses). You must pass `false` explicitly if you
//   do NOT want preventDefault. This is critical.
shieldEventsHandler_Main.add_Click(
    '[data-some-selector="1"]',
    ( element, evt ) => { /* handler */ },
    false  // ← MUST pass false for div/button clicks where we don't want preventDefault
);
```

**Source file for reference:** `assets/js/services/ShieldEventsHandler.js`

### Component Class Pattern

All components extend `BaseAutoExecComponent`:
- Constructor calls `init()` which calls `exec()` which calls `canRun() && run()`
- `canRun()` — return `true` if the component's required DOM elements exist
- `run()` — register event handlers via `shieldEventsHandler_Main`

**Source files for reference:**
- `assets/js/components/BaseComponent.js`
- `assets/js/components/BaseAutoExecComponent.js`

**Existing example to follow:** `assets/js/components/mode/ModePanelStateController.js` — this is the closest analogy to what we're building. Read it before implementing.

### Data Attributes from Phase 1

These data attributes are already in the Phase 1 Twig templates:

| Attribute | Element | Purpose |
|-----------|---------|---------|
| `data-shield-expand-trigger="1"` | `.shield-detail-row--expandable` | Marks rows as clickable triggers |
| `data-shield-expand-target="..."` | `.shield-detail-row--expandable` | ID of the expansion body to toggle |
| `data-shield-expand-body="1"` | `.shield-detail-expansion` | Marks expansion body containers |
| `data-shield-expand-close="1"` | Cancel button inside expansion | Marks close/cancel triggers |

### CSS Classes from Phase 1

These CSS classes are already defined in `_shield-detail-components.scss`:

| Class | Element | Effect |
|-------|---------|--------|
| `.is-expanded` | `.shield-detail-row` | Removes bottom border-radius, hides bottom border, rotates chevron |
| `.is-open` | `.shield-detail-expansion` | Changes `display: none` to `display: block` |

### ARIA Attributes from Phase 1

| Attribute | Element | Purpose |
|-----------|---------|---------|
| `aria-expanded="false"` | `.shield-detail-row--expandable` | Toggled to `"true"` when expanded |
| `aria-hidden="true"` | `.shield-detail-expansion` | Toggled to `"false"` when open |

---

## Deliverable 1: `DetailRowExpandController` JavaScript Component

**File:** `assets/js/components/mode/DetailRowExpandController.js`

This file goes in the `assets/js/components/mode/` directory alongside `ModePanelStateController.js`, `InvestigateLandingController.js`, etc.

### Complete Implementation

```javascript
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";

export class DetailRowExpandController extends BaseAutoExecComponent {

	canRun() {
		return document.querySelector( '[data-shield-expand-trigger="1"]' ) !== null;
	}

	run() {
		shieldEventsHandler_Main.add_Click(
			'[data-shield-expand-trigger="1"]',
			( row, evt ) => this.handleRowClick( row, evt ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'[data-shield-expand-close="1"]',
			( button, evt ) => this.handleCloseClick( button ),
			false
		);
		shieldEventsHandler_Main.add_Keyup(
			'[data-shield-expand-trigger="1"]',
			( row, evt ) => this.handleRowKeyup( row, evt ),
			false
		);
	}

	/**
	 * Handle click on an expandable detail row.
	 *
	 * Guard: if the click originated from an action chip (.shield-action-chip),
	 * do nothing — let the chip's native <a> navigation proceed.
	 */
	handleRowClick( row, evt ) {
		if ( evt.target.closest( '.shield-action-chip' ) ) {
			return;
		}
		this.toggleExpand( row );
	}

	/**
	 * Handle Enter or Space keyup on an expandable row.
	 *
	 * Note: Space triggers page scroll on keydown (before keyup fires). This is
	 * a known minor UX issue. A keydown handler would fix it, but ShieldEventsHandler
	 * does not support keydown events. For now this is acceptable — most users
	 * interact via mouse. If Space-scroll becomes a problem, add a direct keydown
	 * listener in a future phase.
	 */
	handleRowKeyup( row, evt ) {
		if ( evt.key === 'Enter' || evt.key === ' ' ) {
			evt.preventDefault();
			this.toggleExpand( row );
		}
	}

	/**
	 * Handle click on a Cancel / close button inside an expansion body.
	 */
	handleCloseClick( button ) {
		const expansion = button.closest( '[data-shield-expand-body="1"]' );
		if ( expansion === null ) {
			return;
		}
		this.closeExpansion( expansion );
	}

	/**
	 * Toggle the expansion connected to this row.
	 *
	 * Accordion behaviour: if another expansion is open in the same container,
	 * close it first. "Same container" = the parent of the row's .shield-detail-item
	 * wrapper, typically .shield-rail-layout__content or a pane div.
	 */
	toggleExpand( row ) {
		const targetId = ( row.dataset.shieldExpandTarget || '' ).trim();
		if ( targetId.length === 0 ) {
			return;
		}

		const expansion = document.getElementById( targetId );
		if ( expansion === null ) {
			return;
		}

		const isCurrentlyOpen = expansion.classList.contains( 'is-open' );

		// Accordion: close all other open expansions in the same container
		const itemWrapper = row.closest( '.shield-detail-item' );
		const container = itemWrapper !== null ? itemWrapper.parentElement : null;
		if ( container !== null ) {
			container.querySelectorAll( '[data-shield-expand-body="1"].is-open' ).forEach( ( openExp ) => {
				if ( openExp !== expansion ) {
					this.closeExpansion( openExp );
				}
			} );
		}

		if ( isCurrentlyOpen ) {
			this.closeExpansion( expansion );
		}
		else {
			this.openExpansion( row, expansion );
		}
	}

	/**
	 * Open an expansion and mark its trigger row as expanded.
	 */
	openExpansion( row, expansion ) {
		row.classList.add( 'is-expanded' );
		row.setAttribute( 'aria-expanded', 'true' );

		expansion.classList.add( 'is-open' );
		expansion.setAttribute( 'aria-hidden', 'false' );

		// Initialize Bootstrap tooltips within newly-visible expansion content.
		// Tooltips on hidden elements may not be initialized during page load.
		BootstrapTooltips.RegisterNewTooltipsWithin( expansion );
	}

	/**
	 * Close an expansion and un-expand its trigger row.
	 *
	 * Finds the associated row by matching the expansion's ID to a row's
	 * data-shield-expand-target attribute.
	 */
	closeExpansion( expansion ) {
		expansion.classList.remove( 'is-open' );
		expansion.setAttribute( 'aria-hidden', 'true' );

		const row = document.querySelector(
			'[data-shield-expand-target="' + expansion.id + '"]'
		);
		if ( row !== null ) {
			row.classList.remove( 'is-expanded' );
			row.setAttribute( 'aria-expanded', 'false' );
		}
	}
}
```

### Why This Implementation

1. **Extends `BaseAutoExecComponent`** — matches `ModePanelStateController` pattern. Auto-executes on instantiation. No explicit `init()` override needed (inherited `init()` calls `exec()` → `canRun()` → `run()`).

2. **`canRun()` checks DOM** — returns `false` on pages that don't have expandable detail rows (e.g., dashboard, IP rules). The component is a no-op on those pages.

3. **Three event handlers in `run()`** — all via `shieldEventsHandler_Main`:
   - `add_Click` on `[data-shield-expand-trigger="1"]` — row expand toggle
   - `add_Click` on `[data-shield-expand-close="1"]` — Cancel button close
   - `add_Keyup` on `[data-shield-expand-trigger="1"]` — Enter/Space keyboard activation

4. **All three pass `suppress: false`** — critical! `ShieldEventsHandler.add_Click` defaults suppress to `true` (because `'click'` is in `isSuppressEvent`). We must explicitly pass `false` because:
   - Row triggers are `<div>` elements, not `<a>` links — `preventDefault()` is unnecessary
   - Action chip `<a>` elements inside rows need their native navigation to proceed
   - The Cancel `<button>` doesn't need `preventDefault()` either

5. **Action chip guard** (`evt.target.closest('.shield-action-chip')`) — when a user clicks an action chip (e.g., "Update to v8.5.3"), the click event bubbles up. `ShieldEventsHandler` matches `.closest('[data-shield-expand-trigger="1"]')` and fires our handler. The guard returns early so the chip's `<a href>` navigation proceeds normally.

6. **Accordion scoping** — finds the parent of `.shield-detail-item` (the list container). In the Actions Queue, this will be `.shield-rail-layout__content` or a pane div. In Configure mode, it will be similar. This ensures expansions only close within the same visual section.

7. **Row-to-expansion link** — forward: `row.dataset.shieldExpandTarget` → `document.getElementById()`. Reverse: `document.querySelector('[data-shield-expand-target="' + expansion.id + '"]')`. Both directions use the expansion's `id` as the join key.

8. **`BootstrapTooltips.RegisterNewTooltipsWithin()`** — called when expansion opens. This is the existing codebase pattern (used in `OffCanvasService.js`, `Navigation.js`, `Merlin.js`). Expansion content may contain tooltips that weren't initialized during page load because the content was hidden (`display: none`).

---

## Deliverable 2: Register Component in AppMain

**File to modify:** `assets/js/app/AppMain.js`

### 2A. Add Import Statement

Add this import at the TOP of the file, alongside the other `mode/` imports. Place it adjacent to the existing mode imports (around lines 25–27):

```javascript
import { ModePanelStateController } from "../components/mode/ModePanelStateController";
import { InvestigateLandingController } from "../components/mode/InvestigateLandingController";
import { ConfigureLandingController } from "../components/mode/ConfigureLandingController";
import { DetailRowExpandController } from "../components/mode/DetailRowExpandController";
```

The new line goes immediately after the `ConfigureLandingController` import (currently line 27).

### 2B. Add Component Registration

In the `initComponents()` method, add the new component alongside the other mode controllers. Place it immediately after the `configure_landing` line (currently line 87):

```javascript
this.components.mode_panel_state = new ModePanelStateController();
this.components.investigate_landing = new InvestigateLandingController();
this.components.configure_landing = new ConfigureLandingController();
this.components.detail_row_expand = new DetailRowExpandController();
```

**Important:** This component takes NO constructor arguments — same pattern as `ModePanelStateController()`, `InvestigateLandingController()`, and `ConfigureLandingController()`. All three of those are instantiated with no arguments and no `comps` data check.

---

## Deliverable 3: Remove Inline `onclick` from Action Chips

**File to modify:** `templates/twig/wpadmin/components/page/shield_detail_row.twig`

Phase 1 added `onclick="event.stopPropagation()"` to action chip `<a>` elements as a temporary workaround. Now that `DetailRowExpandController` guards against chip clicks (via `evt.target.closest('.shield-action-chip')`), the inline handler is no longer needed.

### Change

Find this line inside the action chips loop:

```twig
            <a href="{{ action.href|default('#') }}"
               class="shield-action-chip shield-action-chip--{{ action.type }}"
               onclick="event.stopPropagation()"
               data-bs-toggle="tooltip"
               data-bs-title="{{ action.tooltip|default('')|e('html_attr') }}">
```

Remove the `onclick="event.stopPropagation()"` line:

```twig
            <a href="{{ action.href|default('#') }}"
               class="shield-action-chip shield-action-chip--{{ action.type }}"
               data-bs-toggle="tooltip"
               data-bs-title="{{ action.tooltip|default('')|e('html_attr') }}">
```

That is the ONLY change to this file. Do not modify anything else.

---

## Files To Create (Summary)

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `assets/js/components/mode/DetailRowExpandController.js` | Expand/collapse controller |

## Files To Modify (Summary)

| # | File Path | Change |
|---|-----------|--------|
| 1 | `assets/js/app/AppMain.js` | Add import + component registration |
| 2 | `templates/twig/wpadmin/components/page/shield_detail_row.twig` | Remove inline `onclick` from action chips |

## Files NOT To Modify

Do NOT touch any of these files:
- `assets/js/services/ShieldEventsHandler.js` — do not add new event types or modify behaviour
- `assets/js/components/BaseComponent.js`
- `assets/js/components/BaseAutoExecComponent.js`
- `assets/js/components/ui/BootstrapTooltips.js`
- `assets/js/components/mode/ModePanelStateController.js`
- `assets/js/components/mode/InvestigateLandingController.js`
- `assets/js/components/mode/ConfigureLandingController.js`
- `assets/css/shield/_shield-detail-components.scss` — CSS is already complete from Phase 1
- Any PHP files
- `templates/twig/wpadmin/components/page/shield_detail_expansion.twig` — no changes needed
- `templates/twig/wpadmin/components/page/shield_rail_sidebar.twig` — no changes needed
- `templates/twig/wpadmin/components/page/shield_rail_layout.twig` — no changes needed

---

## Verification

After implementation:

1. Run `npm run build` to confirm JavaScript compiles without errors.
2. Check that the compiled `shield-main.bundle.js` includes the `DetailRowExpandController` class (search the unminified output or use `npm run dev`).
3. If possible, visually verify using the demo template from Phase 1 (`shield_detail_demo.twig`):
   - Click the "WooCommerce" critical row (item 3 in the demo) — the file table expansion should slide open, the chevron should rotate 90°, and the row's bottom corners should become square.
   - Click it again — the expansion should close, chevron rotates back, row corners restore.
   - Click the "Web Application Firewall" warning row (item 4) — the options expansion with Save/Cancel should open.
   - Click "Cancel" inside the WAF expansion — it should close.
   - With the WAF expansion open, click the "Basic Firewall" row (item 5) — the WAF expansion should close (accordion) and the Basic Firewall expansion should open.
   - Click an action chip ("Update to v8.5.3") — the row should NOT expand; the chip's link should navigate normally.
   - Focus an expandable row with Tab, then press Enter or Space — the expansion should toggle.

---

## What Comes Next (NOT part of this phase)

For context only — do NOT implement any of this:

- **Phase 3:** Wire rail sidebar into Actions Queue scans view — add `RailSidebarController` for pane switching, replace Bootstrap nav-tabs, PHP builder changes for scan results data
- **Phase 4:** Wire detail rows into Configure operator mode — replace tile grid + offcanvas with inline vertical expansion
- **Phase 5:** PHP builder changes to feed component data structures
