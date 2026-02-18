# Shield CSS Architecture

## Directory Structure

```
assets/css/
  plugin-main.scss            Main entry point (compiled to shield-main.bundle.css)
  plugin-wpadmin.scss         WordPress admin dashboard widget styles
  plugin-blockpage.scss       Block/lockout page overlay
  plugin-login2fa.scss        Two-factor authentication login form
  plugin-userprofile.scss     User profile MFA section
  plugin-mainwp-server.scss   MainWP server extension
  plugin-main-modern.scss     Future @use/@forward version (not active)

  shield/
    _status-colors.scss       Shared status, neutral surface, and accent color tokens
    progress.scss             Security analysis meter cards and progress bars
    zones.scss                Security zone component cards
    merlin.scss               Merlin setup wizard
    meters.scss               Meter gauge styling
    security-admin.scss       Security admin PIN modal
    options.scss              Module options pages
    ip_analyse.scss           IP analysis off-canvas (options-style rail layout)
    reports.scss              Security report styles (reference only — actual CSS is inline in Twig)
    charts.scss               Chart styling
    dashboard-widget.scss     WP dashboard widget
    datatables.css            DataTables customisations
    dialog.css                Dialog/modal styling
    micromodal.scss           Micro-modal overrides
    third-party-overrides.scss  Third-party library fixes
    toastify.scss             Toast notifications
    video_modal.scss          Video modal

  components/
    bootstrap.scss            Bootstrap overrides (tooltip z-index)
    notices.scss              Admin notice styling
    nav_sidebar_menu.scss     Sidebar navigation
    tables.scss               Table styling
```

## Build Commands

```bash
npm install && npm run build    # Production build
npm run dev                     # Development build with watch
```

Output goes to `assets/dist/`. The main bundle is `shield-main.bundle.css`.

## Import Order (plugin-main.scss)

The import order in `plugin-main.scss` matters. Follow this sequence:

1. **Bootstrap color overrides** (`$primary`, `$secondary`, `$info`, `$warning`, `$danger`)
2. **Bootstrap framework** (`~bootstrap/scss/bootstrap`)
3. **Third-party vendor CSS** (DataTables, Select2, Intro.js, etc.)
4. **Shared status variables** (`shield/_status-colors`) -- must come before any shield component
5. **Shield component styles** (`shield/*.scss`)
6. **General component styles** (`components/*.scss`)
7. **Page-level styles** (inline in plugin-main.scss)

When adding a new shield component file, import it in the shield section (after `_status-colors` and before `components/`).

## Shared Status Variables (_status-colors.scss)

All status-related colours are defined in `shield/_status-colors.scss`. Use these variables instead of hardcoding hex values.

### Status Accent Colours

Use for top accent bars, progress bar fills, and primary status indicators:

| Variable | Resolves to | Use for |
|----------|-------------|---------|
| `$status-color-good` | `$primary` (#008000) | Good/success states |
| `$status-color-warning` | `$warning` (#edb41d) | Warning/okay states |
| `$status-color-critical` | `$danger` (#c62f3e) | Critical/bad states |
| `$status-color-info` | `$info` (#0ea8c7) | Neutral/informational states |

### Status Light Backgrounds

Use for icon badge backgrounds and subtle status indicators:

| Variable | Value | Use for |
|----------|-------|---------|
| `$status-bg-good-light` | #e6f5e6 | Good status icon background |
| `$status-bg-warning-light` | #fef6e6 | Warning status icon background |
| `$status-bg-critical-light` | #fdeaec | Critical status icon background |
| `$status-bg-info-light` | #e7f1ff | Info status icon background |

### Badge Pill Colours

Use for small status pill badges (text on subtle background):

| Variable | Value | Use for |
|----------|-------|---------|
| `$badge-good-bg` / `$badge-good-color` | #d1e7dd / #0a5c36 | Good status badge |
| `$badge-warning-bg` / `$badge-warning-color` | #fff3cd / #856404 | Warning status badge |
| `$badge-critical-bg` / `$badge-critical-color` | #f8d7da / #842029 | Critical status badge |
| `$badge-info-bg` / `$badge-info-color` | #e7f1ff / #0a58ca | Info status badge |

### Card Status Tint Backgrounds

Very subtle status tints for card body fill. These are much lighter than the badge/icon backgrounds — just enough to hint at the status:

| Variable | Value | Use for |
|----------|-------|---------|
| `$card-bg-good` | #f9fdf9 | Good status card body |
| `$card-bg-warning` | #fdfcf8 | Warning status card body |
| `$card-bg-critical` | #fef9f9 | Critical status card body |
| `$card-bg-info` | #f7fbfd | Info status card body |

### Card Properties

Use for card-style components (meter cards, zone cards):

| Variable | Value | Use for |
|----------|-------|---------|
| `$card-border-radius` | 10px | All card containers |
| `$card-box-shadow` | 0 1px 6px rgba(0,0,0,0.07) | Card resting shadow |
| `$card-box-shadow-hover` | 0 4px 16px rgba(0,0,0,0.12) | Card hover shadow |
| `$card-accent-height` | 4px | Top accent bar height |

### Neutral Surface + Salt Green Accent

For modern plugin UI chrome (for example options and off-canvas), use a neutral near-gray base and a very subtle green accent.

| Variable | Value | Use for |
|----------|-------|---------|
| `$surface-color-neutral-base` | #f5f6f5 | Main neutral backgrounds (canvas/body areas) |
| `$surface-color-neutral-raised` | #f8f9f8 | Raised neutral surfaces (headers/rails/panels) |
| `$border-color-neutral-subtle` | #d9dfd9 | Default neutral borders |
| `$border-color-neutral-strong` | #ccd3cc | Stronger borders for controls/inputs |
| `$accent-color-salt-green` | #d2ddd2 | Subtle component accents (panel/rail accent bars) |
| `$accent-color-salt-green-soft` | #d4ddd4 | Softer separators and top strips |

Rule of thumb:
1. Keep the overall surface neutral (almost gray/white)
2. Reserve salt-green accents for component chrome, not full-surface fills
3. Keep accent intensity low so readability stays high

## Card Component Pattern

Both meter cards (`progress.scss`) and zone cards (`zones.scss`) follow the same visual pattern:

```
+--[accent bar: 4px coloured div]--+
|                                   |
|  Card content                     |
|  border: none                     |
|  border-radius: 10px             |
|  box-shadow: resting shadow       |
|                                   |
+-----------------------------------+
```

**Hover state**: `transform: translateY(-3px)` with `$card-box-shadow-hover`.

The accent bar is a `<div>` element (not a CSS border-top) so it sits inside the border-radius without clipping.

When building a new card component that needs a status accent:
1. Use `$card-border-radius`, `$card-box-shadow`, `$card-box-shadow-hover` for the card container
2. Add `status-{{ status }}` class on the card div itself (for background tint and parent-scoped rules)
3. Add an accent `<div>` as the first child with `height: $card-accent-height; width: 100%`
4. Apply status colours via `$status-color-*` variables on the accent div
5. Apply `$card-bg-*` variables for subtle card body background tints per status
6. Set `overflow: hidden` on the card so the accent respects border-radius

## Status Naming Conventions

Different components use different status vocabulary. Map them to the shared colour variables:

| Component | Statuses | Colour mapping |
|-----------|----------|----------------|
| Meter cards | `good`, `warning`, `critical` | Direct match to `$status-color-*` |
| Zone cards | `good`, `okay`, `bad`, `neutral`, `neutral_enabled` | `okay`=warning, `bad`=critical, `neutral`/`neutral_enabled`=info |

## SCSS Conventions

### Indentation

Use **2 spaces** for SCSS indentation. This is distinct from the PHP convention of tabs.

### Selectors

- Use class selectors, avoid IDs where possible for component styles
- Scope component styles to a parent class (e.g., `.zone-component-card .status-badge`)
- Use `&.modifier` for BEM-like status variants (e.g., `&.status-good`)

### Colours

- Never hardcode hex colours for status indicators -- use `$status-color-*` or `$badge-*-color`
- For neutral plugin chrome, use `$surface-color-neutral-*`, `$border-color-neutral-*`, and `$accent-color-salt-green*`
- Hover background colours: use `rgba($status-color-*, 0.06)` for subtle hover tints
- Dark hover text colours: use explicit hex values (no `darken()` / `lighten()` functions)
- Other non-status colours (grays, borders, text) can remain as hex literals

### Variables

- Use simple `$variable` declarations (no SCSS maps or mixins in this codebase)
- Naming: `$category-variant-property` (e.g., `$status-color-good`, `$badge-warning-bg`)
- Shared variables go in `_status-colors.scss`; component-specific values stay in their own file

### Comments

- Section headers: `/* Comment */`
- Inline notes: `// Comment`

## Scope Boundaries

Each SCSS file owns specific components. Do not style another file's components:

| File | Owns |
|------|------|
| `progress.scss` | `.meter-hero-card`, `.meter-card`, `.meter-card-accent`, `.status-badge` (meter context), `.progress-bar` |
| `zones.scss` | `.zone-component-card`, `.zone-card-accent`, `.status-badge` (zone context), `.configure-link`, `.explanations-block` |
| `options.scss` | `.options_form_for--modern`, `.shield-options-layout`, `.shield-options-rail`, `.shield-options-panel`, `.shield-option-row` |
| `ip_analyse.scss` | `#AptoOffcanvas.offcanvas_ipanalysis .shield-ipanalyse*` and IP-analysis-specific content styling inside the shared options-style rail/panel layout |
| `reports.scss` (ref) | `.report-section`, `.scan-card`, `.stat-card`, `.change-card`, `.status-pill`, `.category-header`, `.repair-item`, `.scan-item` |
| `merlin.scss` | `#MerlinOverlay`, `.merlin-modal-dialog`, `.stepper*`, `.wizard-card*`, `.wizard-step-pane`, `.profile-card*`, `.feature-list`, `.section-header`, `.feature-item`, `.merlin-form-footer` |
| `security-admin.scss` | Security admin modal and PIN form |

## Inline CSS Pattern (Reports)

Security reports use inline `<style>` tags rather than external CSS bundles because
the full HTML is stored in the database. `shield/reports.scss` is a reference-only
file that documents the design token usage — it is NOT compiled by webpack. When
updating report styles, edit the `<style>` block in
`templates/twig/pages/report/security.twig` and keep `reports.scss` in sync.

## Wizard (Merlin) — Card Stepper Design

The setup wizard (`merlin.scss`) uses a custom **Card Stepper** pattern with vanilla JS step navigation (no third-party wizard library). It renders as a static Bootstrap modal (`modal show d-block`) with a `$surface-color-neutral-base` background, giving the white wizard card visible breathing room.

### Layout Structure

```
#MerlinOverlay (modal backdrop, z-index: 1055)
  .merlin-modal-dialog (max-width: 960px)
    .modal-content (height: 85vh, background: $surface-color-neutral-base)
      .modal-header (close button)
      .modal-body
        #merlin (flex column, padding: 0 1.25rem 1.25rem)
          .stepper (horizontal step indicators)
          .wizard-card (white card with accent bar, flex: 1)
            .wizard-card-accent (salt-green bar)
            .wizard-card-body (scrollable content, padding: 1rem)
            .wizard-card-footer (prev/next buttons with separator line)
```

### Infrastructure

**Bootstrap Icons font** is imported via `~bootstrap-icons/font/bootstrap-icons.css` in `plugin-main.scss`. This provides `<i class="bi bi-*">` icon classes used in the wizard stepper checkmarks and profile card feature icons. Webpack's `file-loader` rule emits the font files to `assets/dist/img/`.

### Custom Stepper

The stepper is built with real HTML elements (not pseudo-elements), making states easy to manage via CSS class toggles in JS:

- `.stepper` — flex row of `.stepper-step` elements, centered, max-width 120px per step
- `.stepper-circle` — 32px circle containing `.step-number` (text) and `.step-check` (checkmark icon)
- Connecting lines between steps via `::after` pseudo-element on `.stepper-step:not(:last-child)`

**Step states** (toggled by `Merlin.js` via `goToStep()`):

- `.completed` — Solid `$status-color-good` circle, white checkmark, green connecting line
- `.current` — White circle with `$status-color-good` border ring, green number and label
- `.future` — White circle with grey border, muted number

### Wizard Card

`.wizard-card` follows the standard card component pattern with `$card-border-radius`, `$card-box-shadow`, and a `.wizard-card-accent` salt-green bar. The card body scrolls when content overflows. The footer contains prev/next navigation buttons that use `visibility: hidden` (Bootstrap `.invisible` class) instead of `display: none` so buttons stay in fixed positions across all steps.

### Step Content

Step templates extend `templates/twig/components/merlin/steps/base.twig` which provides block structure (`slide_body_title`, `slide_body_video_section_blurb`, `slide_body_video_section`, `slide_body_action_area`). Content renders directly without Bootstrap grid wrapping — the card body padding controls spacing.

### Security Profile Cards

The profile selection step uses a 4-column grid of clickable cards (current config + 3 profiles):

- `.profile-cards-row` — 4-column CSS grid (`repeat(4, 1fr)`), 1rem gap
- `.profile-card` — `$card-border-radius`, `$card-box-shadow`, transparent 2px border, hover lift
- `.profile-card.selected` — 3px green border, `#edf7ed` background tint, double green glow ring
- `.profile-card-current` — Non-selectable, neutral border and `$surface-color-neutral-raised` background
- `.profile-card-accent` — Level-based colours: `.level-light` = `$status-color-good`, `.level-medium` = `$status-color-warning`, `.level-strong` = `$status-color-critical`
- `.feature-list` — Vertical list of `.feature-item` rows with status icons (`.fi-on`, `.fi-off`, `.fi-val`)
- `.merlin-form-footer` — Separator line + centered submit button below profile cards

### Responsive

- `max-width: 992px` — Profile cards switch to 2 columns
- `max-width: 768px` — Profile cards switch to 1 column, stepper wraps with connecting lines hidden

### JS Component

`Merlin.js` extends `BaseComponent` and manages step navigation with `goToStep()`, `next()`, `prev()` methods. No jQuery or third-party wizard dependency — pure vanilla DOM manipulation (class toggles, `querySelectorAll`). Form submissions use `AjaxService` and advance to the next step on success.

### Prototypes (Reference)

Standalone HTML prototypes in `prototypes/wizard/` were used during design exploration. The implemented design follows `card-stepper.html` (Prototype B).

## Related Documentation

- `assets/SASS_MODERNIZATION.md` -- Future plans for migrating from `@import` to `@use`/`@forward`
- `CLAUDE.md` (project root) -- PHP code style and build commands
