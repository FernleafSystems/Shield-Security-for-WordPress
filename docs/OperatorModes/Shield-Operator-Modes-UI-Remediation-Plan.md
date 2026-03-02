# Shield Operator Modes — UI Remediation Plan

**Date:** 2026-03-02 | **Author:** Paul Goodchild / Fernleaf Systems
**Status:** Pending implementation *(REM-002, REM-003, REM-004, REM-005, REM-006, and REM-007 completed 2026-03-02)*
**Source audit:** Full review of implemented templates, PHP handlers, SCSS, and prototypes
**Companion documents:** `Shield-Operator-Modes-Plan.md`, `Shield-Operator-Modes-Task-Backlog.md`
**Prototype references:** `docs/OperatorModes/prototype-b-hero-strip.html`, `docs/OperatorModes/investigate-mode/`

---

## Overview

The engineering delivery of operator modes is structurally complete. The four-mode system (Actions Queue, Investigate, Configure, Reports), the two-state sidebar, breadcrumbs, meter channel separation, investigation subject pages, and the WP dashboard widget are all implemented and unit-tested.

What remains is a UI layer problem: the mode landing pages are not functioning as landing pages. They are functioning as component display surfaces. The original plan called for "purpose-driven entry points" — pages that orient the user, communicate their current context, and provide a clear primary action. The current implementations are closer to the old flat-dump dashboard that the whole operator modes project was created to replace, just reorganised by category.

This document identifies every concrete UI gap, locates the exact code that must change, describes the required change in full, and defines the acceptance criterion for each item. It is intended to be a self-contained work order that can be handed to any implementor without requiring re-discovery of the codebase.

---

## Gap Index

| ID | Title | Severity | Mode |
|---|---|---|---|
| REM-001 | Mode selector: strip cards have asymmetric and incomplete live data | High | Mode Selector |
| REM-002 | Actions Queue landing: action-channel MeterCard is missing | **Completed — Implemented 2026-03-02** | Actions Queue |
| REM-003 | Actions Queue landing: "all clear" state provides no confidence-building context | **Completed — Implemented 2026-03-02** | Actions Queue |
| REM-004 | Configure landing: four data-heavy sections on a landing page is a data dump | **Completed — Implemented 2026-03-02** | Configure |
| REM-005 | Configure landing: zones appear twice; quick links card points to wrong entry | **Completed — Implemented 2026-03-02** | Configure |
| REM-006 | Reports landing: no orientation, no framing, opens with misplaced CTA buttons | **Completed — Implemented 2026-03-02** | Reports |
| REM-007 | Investigate landing: four heading layers before the subject grid | **Completed — Implemented 2026-03-02** | Investigate |
| REM-008 | `PageOperatorModeLanding` does not extend `BasePluginAdminPage` | **Closed — Not a gap** | Mode Selector |
| REM-009 | No persistent visual mode-color identity on any mode landing page | Medium | All modes |
| REM-010 | "Always start in" preference form is visually orphaned and lacks feedback | Low | Mode Selector |

---

## REM-001 — Mode Selector: Strip Cards Have Asymmetric and Incomplete Live Data

### Problem Description

The mode selector landing page renders a hero card for Actions Queue plus three secondary strip cards for Investigate, Configure, and Reports. These four cards carry fundamentally different information densities:

- **Actions Queue hero:** live item count, severity-derived accent colour, subtitle with item count, optional meta line (last scan time). This is genuinely live and informative.
- **Configure strip card:** shows a `badge_text` of `"72%"` (the config-channel meter percentage). Informative, but the badge is tiny and unlabelled — a user has no idea what 72% means without explanation.
- **Investigate strip card:** shows only the static string `"Investigate activity, traffic, and IP behavior."` No live data whatsoever.
- **Reports strip card:** shows only the static string `"Review security reports and trends."` No live data whatsoever.

This asymmetry makes the landing look unfinished. Three cards offer different levels of signal. The user can tell from the Actions card whether they need to act, can guess from the Configure card that 72% is probably a score, and learns nothing actionable from the Investigate or Reports cards.

The prototype (`prototype-b-hero-strip.html`) established a clear visual contract: each card communicates its current state. That contract is only partially honoured.

### Affected Files

| File | Role |
|---|---|
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageOperatorModeLanding.php` | PHP handler — `buildModeStrip()` builds the three secondary card payloads |
| `templates/twig/wpadmin/plugin_pages/inner/operator_mode_landing.twig` | Twig template — renders the strip cards from `vars.mode_strip` |
| `assets/css/shield/dashboard.scss` | SCSS — `.operator-mode-landing__strip-*` classes |

### Exact Location in Code

**`PageOperatorModeLanding.php`, `buildModeStrip()`, lines 84–115:**

```php
private function buildModeStrip( int $configPercentage, string $configTraffic ) :array {
    return [
        [
            'mode'       => PluginNavs::MODE_INVESTIGATE,
            // ...
            'badge_text' => '',          // ← empty: no live signal
            'summary'    => $this->modeSummary( PluginNavs::MODE_INVESTIGATE ),
        ],
        [
            'mode'         => PluginNavs::MODE_CONFIGURE,
            // ...
            'badge_text'   => sprintf( '%s%%', $configPercentage ),   // ← score, but no label
            'badge_status' => $configTraffic,
            'summary'      => $this->configureSummary( $configTraffic ),
        ],
        [
            'mode'       => PluginNavs::MODE_REPORTS,
            // ...
            'badge_text' => '',          // ← empty: no live signal
            'summary'    => $this->modeSummary( PluginNavs::MODE_REPORTS ),
        ],
    ];
}
```

**`operator_mode_landing.twig`, lines 37–39 (badge rendering):**

```twig
{% if mode.badge_text|default('') is not empty %}
    <span class="shield-badge badge-{{ mode.badge_status|default(mode.status) }}">{{ mode.badge_text }}</span>
{% endif %}
```

The badge renders or not based on `badge_text`. Investigate and Reports always render nothing here.

### Required Changes

**`PageOperatorModeLanding.php`:**

1. **Configure card badge label:** Add a `badge_label` key alongside `badge_text` for the Configure card. Set `badge_label` to `__( 'Config Score', 'wp-simple-firewall' )`. Update the template to render it as a small muted line below the badge (see item 5 below). Do not change the existing `badge_text` value — it already contains the percentage string correctly.

2. **Investigate card live data:** The cheapest source of runtime data for the Investigate strip card is an active session count. `FindSessions` is at `src/Modules/UserManagement/Lib/Session/FindSessions.php`. Its `mostRecent(int $limit = 10)` method runs a SELECT with three JOINs (user_meta, ips, wp_users tables) and returns an array keyed by user_id. Calling `count((new FindSessions())->mostRecent(5))` gives the number of active sessions tracked in Shield's user metadata, up to a cap of 5. This value can be passed as a `badge_text` like `"N active sessions"` or worked into the `summary` string as `"N active sessions tracked"`. If the session module is disabled, `mostRecent()` will return an empty array and the badge gracefully reduces to an empty string (no badge rendered).

   Alternatively, since `PageOperatorModeLanding` already calls `AttentionItemsProvider` via `NeedsAttentionQueue::payload()` for the hero card, call `(new AttentionItemsProvider())->buildActionSummary()` directly — this returns `{total: int, severity: string, is_all_clear: bool}` and is already computed as part of the hero build. However, this summarises the Actions Queue, not Investigate-specific data, so it is less appropriate here.

   **Recommended implementation for Investigate badge:**
   ```php
   use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
   $sessionCount = count( ( new FindSessions() )->mostRecent( 5 ) );
   $investigateBadge = $sessionCount > 0
       ? sprintf( _n( '%s active session', '%s active sessions', $sessionCount, 'wp-simple-firewall' ), $sessionCount )
       : '';
   ```

3. **Reports card live data:** The reports database table is accessible via `self::con()->db_con->reports->getQuerySelector()` (class at `src/DBs/Reports/Ops/Select.php`). To get a count of generated reports: `self::con()->db_con->reports->getQuerySelector()->count()`. To get the most recent report's date: call `->setLimit(1)->queryWithResult()` and read `$result[0]->created_at` if non-empty. This timestamp can then be formatted with `Services::WpGeneral()->getTimeStringForDisplay($timestamp)` or similar. A useful badge text: `"N reports"` or `"Last: 3 days ago"`. If the reports table is empty, `count()` returns 0 and the badge should not render.

   **Recommended implementation for Reports badge:**
   ```php
   $reportsCount = (int)self::con()->db_con->reports->getQuerySelector()->count();
   $reportsBadge = $reportsCount > 0
       ? sprintf( _n( '%s report', '%s reports', $reportsCount, 'wp-simple-firewall' ), $reportsCount )
       : '';
   ```

4. **Fallback behaviour:** If the live query returns zero or fails, the card should fall back gracefully to the static summary string only. Do not make the card fail if the data is unavailable.

**`operator_mode_landing.twig`:**

5. Add a `badge_label` field to the strip card contract in `operator_mode_landing.twig`. It defaults to an empty string if not provided. Render it immediately below the badge span:

```twig
{% if mode.badge_text|default('') is not empty %}
    <span class="shield-badge badge-{{ mode.badge_status|default(mode.status) }}">{{ mode.badge_text }}</span>
    {% if mode.badge_label|default('') is not empty %}
        <span class="d-block text-muted" style="font-size: 0.75rem; line-height: 1.2; margin-top: 2px;">{{ mode.badge_label }}</span>
    {% endif %}
{% endif %}
```

This makes the Configure card render `"72%"` with `"Config Score"` directly beneath it. Investigate and Reports have `badge_label` as empty string so neither element renders.

### Acceptance Criteria

- All four cards on the mode selector landing communicate at least one live or status-driven signal.
- The Configure card badge is labelled (not just a raw percentage).
- The Investigate card badge or summary text contains at least one live count derived from runtime data.
- The Reports card badge or summary text contains at least one live count (e.g. last report date or report count).
- All fallbacks to static text are non-breaking when live data is unavailable.

---

## REM-002 — Actions Queue Landing: Action-Channel MeterCard Is Missing *(Completed — Implemented 2026-03-02)*

**Status:** Completed. Action-channel hero meter is rendered above `NeedsAttentionQueue` on the Actions Queue landing page, and unit tests now cover the landing content contract.

### Problem Description

This is the most critical gap. The plan document (`Shield-Operator-Modes-Plan.md`, Section 8, Step 5) states explicitly:

> "Layout: action-channel meter card at top, NeedsAttentionQueue widget body below, quick links to Scan Results and Run Scan at bottom."

The current `PageActionsQueueLanding.php` does not render an action-channel MeterCard. It renders only:

1. `NeedsAttentionQueue` widget (the queue list itself)
2. A CTA card with two buttons ("Open Scan Results", "Run Manual Scan")

Without the action-channel MeterCard, the Actions Queue landing has no aggregate severity score. The `NeedsAttentionQueue` lists individual items grouped by zone, but provides no single "how bad is this overall?" headline figure. A user sees a list of items but has no at-a-glance quantification of the overall action severity.

The action-channel meter infrastructure was built in P1 (`OM-101` through `OM-107`). `Handler::getMeter()` accepts a channel argument. `MeterCard` accepts `meter_channel`. Everything needed to render this card exists. It was simply never added to `PageActionsQueueLanding`.

### Affected Files

| File | Role |
|---|---|
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageActionsQueueLanding.php` | PHP handler — `getLandingContent()` must add the MeterCard render call |
| `templates/twig/wpadmin/plugin_pages/inner/actions_queue_landing.twig` | Twig template — must render `content.action_meter` at the top |
| `src/ActionRouter/Actions/Render/Components/Meters/MeterCard.php` | Existing component to render — review its accepted params |
| `src/Modules/Plugin/Lib/MeterAnalysis/Component/Base.php` | `CHANNEL_ACTION` constant is defined here |
| `src/Modules/Plugin/Lib/MeterAnalysis/Meter/MeterSummary.php` | `SLUG` constant for the summary meter |

### Exact Location in Code

**`PageActionsQueueLanding.php`, `getLandingContent()`, lines 25–30:**

```php
protected function getLandingContent() :array {
    $con = self::con();
    return [
        'needs_attention_queue' => $con->action_router->render( NeedsAttentionQueue::class ),
        // ← action-channel MeterCard render call is absent here
    ];
}
```

**`actions_queue_landing.twig`, lines 1–16:**

```twig
{% extends '/wpadmin/plugin_pages/base_inner_page.twig' %}
{% block inner_page_body %}
    {{ content.needs_attention_queue|raw }}
    <!-- ← MeterCard should appear above or alongside the queue -->
    <div class="card shield-card mt-3">
        ...CTA buttons...
    </div>
{% endblock %}
```

### Required Changes

**`PageActionsQueueLanding.php`:**

Add the action-channel MeterCard render to `getLandingContent()`:

```php
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
    Component\Base as MeterComponent,
    Meter\MeterSummary
};

protected function getLandingContent() :array {
    $con = self::con();
    return [
        'action_meter'          => $con->action_router->render( MeterCard::class, [
            'meter_slug'    => MeterSummary::SLUG,
            'meter_channel' => MeterComponent::CHANNEL_ACTION,
            'is_hero'       => true,
        ] ),
        'needs_attention_queue' => $con->action_router->render( NeedsAttentionQueue::class ),
    ];
}
```

**`actions_queue_landing.twig`:**

Add the MeterCard above the queue. The plan specifies the action-channel meter card appears at the top. The template should mirror the Configure landing's hero section structure but with the action channel:

```twig
{% extends '/wpadmin/plugin_pages/base_inner_page.twig' %}
{% block inner_page_body %}

    {% if content.action_meter|default('') is not empty %}
        <div class="progress-metercard progress-metercard-hero progress-metercard-summary mb-3"
             data-meter_slug="summary"
             data-meter_channel="action">
            {{ content.action_meter|raw }}
        </div>
    {% endif %}

    {{ content.needs_attention_queue|raw }}

    <div class="card shield-card mt-3">
        ...CTA buttons...
    </div>

{% endblock %}
```

**Confirmed data attribute names:** The canonical pattern is visible in `configure_landing.twig` lines 7–9:

```twig
<div class="progress-metercard progress-metercard-hero progress-metercard-summary"
     data-meter_slug="summary"
     data-meter_channel="config">
```

For the action channel, use `data-meter_channel="action"` — this is the string value of `MeterComponent::CHANNEL_ACTION` (defined as `'action'` in `src/Modules/Plugin/Lib/MeterAnalysis/Component/Base.php` line 15). The `data-meter_slug="summary"` is the string value of `MeterSummary::SLUG` (defined as `'summary'` in `src/Modules/Plugin/Lib/MeterAnalysis/Meter/MeterSummary.php` line 9). The `MeterCard` component (`src/ActionRouter/Actions/Render/Components/Meters/MeterCard.php`) uses `is_hero: true` to switch its template to `meter_hero.twig`. The `meter_channel` value in `action_data` is passed through to the meter handler which filters components to only those tagged with `CHANNEL_ACTION`.

### Acceptance Criteria

- The Actions Queue landing page shows a hero MeterCard above the `NeedsAttentionQueue` widget.
- The MeterCard uses `meter_channel: action` — it reflects only scan results, vulnerable plugins, pending updates, and maintenance items, not configuration posture.
- The card renders in the same visual style as the Configure landing hero (progress bar, percentage, grade letter, status colour).
- When the action queue is empty, the action-channel meter renders a "good" state (green, 0 action items) at 100% rather than hiding.
- The CTA card remains below the queue.

---

## REM-003 — Actions Queue Landing: "All Clear" State Provides No Confidence-Building Context *(Completed — Implemented 2026-03-02)*

**Status:** Completed. The landing now reuses queue payload context for a page-level all-clear banner, keeps the compact widget de-dup path, and conditionally hides the "Open Scan Results" CTA when the queue is empty.

### Problem Description

When the Actions Queue has no items, the landing page renders:

1. The `NeedsAttentionQueue` widget's "all clear" state (a green tick + "No items require your attention" copy inside the widget)
2. Two CTA buttons: "Open Scan Results" and "Run Manual Scan"

The plan states (Section 2):

> "When the Actions Queue is empty, its card on the mode selector landing shows a clear green 'all clear' state with a summary line (e.g. 'No actions pending — last scan 2 hours ago') so the user gets reassurance without entering the mode."

This reassurance copy with temporal context exists on the mode selector (in `PageOperatorModeLanding::buildActionsHero()`), but **it does not exist on the Actions Queue landing page itself**. When a user actually navigates into the Actions Queue mode and the queue is empty, they see a generic "all clear" widget without knowing:

- When the last scan ran
- What scan types covered
- Whether the all-clear is fresh or stale

This is the page a user will land on most frequently — when everything is fine. It needs to reassure them actively, not just passively show an empty list.

### Affected Files

| File | Role |
|---|---|
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageActionsQueueLanding.php` | PHP handler — must supply temporal context data |
| `src/ActionRouter/Actions/Render/Components/Widgets/NeedsAttentionQueue.php` | Widget component — review what `status_strip_subtext` provides |
| `src/ActionRouter/Actions/Render/Components/Widgets/AttentionItemsProvider.php` | Data provider — review what last-scan and summary context it exposes |
| `templates/twig/wpadmin/plugin_pages/inner/actions_queue_landing.twig` | Template — must render all-clear context |

### Exact Location in Code

**`NeedsAttentionQueue.php`** — review the `payload()` method. The mode selector landing already calls:

```php
$queuePayload = $con->action_router->action( NeedsAttentionQueue::class )->payload();
// ...uses $queuePayload['strings']['status_strip_subtext']
// ...uses $queuePayload['flags']['has_items']
// ...uses $queuePayload['vars']['total_items']
```

The `status_strip_subtext` key in the widget's payload may already contain temporal context (e.g. "last scan 2 hours ago"). This is displayed on the mode selector hero (via `$this->buildActionsHero()['meta']`) but is not surfaced on the Actions Queue landing.

**`PageActionsQueueLanding.php`** — does not call `NeedsAttentionQueue::payload()` at all; it only calls `render()`.

### Implementation Approach: Hybrid De-dup

The `NeedsAttentionQueue` widget already renders its own all-clear state (shield icon, "All Clear" title, subtitle, zone chips) when the queue is empty. Adding a page-level banner on top of that without any other change would produce two separate "all clear" messages on the same page — the page banner and the widget's own all-clear card. That is confusing and redundant.

**The required approach is hybrid de-duplication:**
- When the queue is **empty**: render the page-level all-clear banner with temporal context; pass a flag to suppress the widget's own all-clear card so only one "all clear" message appears.
- When the queue is **not empty**: no banner; the widget renders normally with its items and severity signal.

### Required Changes

**`PageActionsQueueLanding.php`:**

Compute the queue state once using `AttentionItemsProvider::buildActionSummary()` — this is cheaper than calling `NeedsAttentionQueue::payload()` and returns exactly what is needed: `{total: int, severity: string, is_all_clear: bool}`. Then call `NeedsAttentionQueue::payload()` only if the queue is empty (to get the temporal context string). This avoids double-computing the full widget payload on every page load.

Add a protected method to build the queue state once and cache it on the instance:

```php
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\{
    AttentionItemsProvider,
    NeedsAttentionQueue
};

private ?array $queueSummary = null;

private function getQueueSummary() :array {
    if ( $this->queueSummary === null ) {
        $this->queueSummary = ( new AttentionItemsProvider() )->buildActionSummary();
    }
    return $this->queueSummary;
}
```

Add to `getLandingFlags()`:

```php
protected function getLandingFlags() :array {
    return [
        'queue_is_empty' => (bool)( $this->getQueueSummary()['is_all_clear'] ?? false ),
    ];
}
```

Add to `getLandingStrings()`:

```php
protected function getLandingStrings() :array {
    $strings = [
        'cta_title'           => __( 'Quick Actions', 'wp-simple-firewall' ),
        'cta_scan_results'    => __( 'Open Scan Results', 'wp-simple-firewall' ),
        'cta_scan_run'        => __( 'Run Manual Scan', 'wp-simple-firewall' ),
        'all_clear_title'     => __( 'All Clear', 'wp-simple-firewall' ),
        'all_clear_icon_class' => self::con()->svgs->iconClass( 'shield-check' ),
        'all_clear_subtext'   => '',
    ];
    if ( (bool)( $this->getQueueSummary()['is_all_clear'] ?? false ) ) {
        $queuePayload = self::con()->action_router->action( NeedsAttentionQueue::class )->payload();
        $strings['all_clear_subtext'] = (string)( $queuePayload['strings']['status_strip_subtext'] ?? '' );
    }
    return $strings;
}
```

Pass `flags.queue_is_empty` to the `NeedsAttentionQueue` render call so the widget suppresses its own all-clear card:

```php
protected function getLandingContent() :array {
    $con = self::con();
    $queueIsEmpty = (bool)( $this->getQueueSummary()['is_all_clear'] ?? false );
    return [
        'action_meter'          => $con->action_router->render( MeterCard::class, [
            'meter_slug'    => MeterSummary::SLUG,
            'meter_channel' => MeterComponent::CHANNEL_ACTION,
            'is_hero'       => true,
        ] ),
        'needs_attention_queue' => $con->action_router->render( NeedsAttentionQueue::class, [
            'suppress_all_clear' => $queueIsEmpty,
        ] ),
    ];
}
```

**`NeedsAttentionQueue.php`:**

Respect the `suppress_all_clear` flag in `getRenderData()`. When `suppress_all_clear` is true, pass `flags.suppress_all_clear = true` through to the template:

```php
protected function getRenderData() :array {
    // ... existing code ...
    $data['flags']['suppress_all_clear'] = (bool)( $this->action_data['suppress_all_clear'] ?? false );
    return $data;
}
```

**`needs_attention_queue.twig`** (at `templates/twig/wpadmin/components/widget/needs_attention_queue.twig`):

Wrap the all-clear card block in a conditional so it only renders when `suppress_all_clear` is false:

```twig
{% if not flags.suppress_all_clear|default(false) %}
    {# existing all-clear card markup #}
{% endif %}
```

**`actions_queue_landing.twig`:**

Add the page-level all-clear banner above the queue widget. **Use the existing CSS classes from `assets/css/shield/needs-attention.scss`.** Do not write inline styles. The following classes already exist and must be reused:

- `.shield-needs-attention__all-clear-card` — white card with neutral border
- `.shield-needs-attention__all-clear-accent` — green 4px top bar (`background: $status-color-good; height: 4px`)
- `.shield-needs-attention__all-clear-shield` — green-tinted circle container for the shield icon (`background: $status-bg-good-light`)
- `.shield-needs-attention__all-clear-title` — green-coloured title text (`color: $badge-good-color; font-size: 1.02rem`)
- `.shield-needs-attention__all-clear-subtitle` — muted subtitle text (`color: #7a848d; font-size: 0.8rem`)

The preferred template for the all-clear contextual banner on the landing page, using these classes, is:

```twig
{% if flags.queue_is_empty|default(false) %}
    <div class="shield-needs-attention__all-clear-card mb-3">
        <div class="shield-needs-attention__all-clear-accent"></div>
        <div class="card-body d-flex align-items-center gap-3">
            <div class="shield-needs-attention__all-clear-shield">
                <i class="{{ strings.all_clear_icon_class }}"></i>
            </div>
            <div>
                <div class="shield-needs-attention__all-clear-title">{{ strings.all_clear_title }}</div>
                {% if strings.all_clear_subtext|default('') is not empty %}
                    <div class="shield-needs-attention__all-clear-subtitle">{{ strings.all_clear_subtext }}</div>
                {% endif %}
            </div>
        </div>
    </div>
{% endif %}
```

**Full `NeedsAttentionQueue` payload structure** (from `src/ActionRouter/Actions/Render/Components/Widgets/NeedsAttentionQueue.php`, `getRenderData()`):
- `flags.has_items` — bool; true when queue is non-empty
- `strings.status_strip_text` — e.g. "3 issues need your attention" or "Your site is secure"
- `strings.status_strip_subtext` — e.g. "Last scan: 2 hours ago" (formatted with Carbon `diffForHumans()`), or '' if no scan has run
- `strings.all_clear_title` — "All Clear"
- `strings.all_clear_subtitle` — "Shield is actively protecting your site. Nothing requires your action."
- `strings.all_clear_message` — "No security actions currently require your attention."
- `strings.all_clear_icon_class` — icon class for `shield-check` SVG
- `vars.overall_severity` — `'good'|'warning'|'critical'`
- `vars.total_items` — int; count of active items
- `vars.zone_groups` — array of zone groups each with items, total_issues, severity
- `vars.zone_chips` — array of zone chip objects for the all-clear chip display

**Double-computation note:** The naive approach calls `NeedsAttentionQueue::payload()` to get `has_items` and `status_strip_subtext`, then also calls `NeedsAttentionQueue::render()` to display the queue widget. This computes the queue data twice. To avoid this, use `AttentionItemsProvider::buildActionSummary()` directly (at `src/ActionRouter/Actions/Render/Components/Widgets/AttentionItemsProvider.php`) — it returns `{total: int, severity: string, is_all_clear: bool}` and is a lighter call than the full payload. Use `is_all_clear` for the flag, and separately read `status_strip_subtext` from `NeedsAttentionQueue::payload()` only if `is_all_clear` is true (one conditional DB call at most). Alternatively, pass the payload to both the flag-building logic and the render — wrap the widget in a PHP render that pre-builds the payload once.

### Acceptance Criteria

- When the queue has zero items: the landing shows a page-level all-clear banner above the queue widget, with temporal context ("Last scan: X ago" or equivalent). The widget renders without its own all-clear card — exactly one "all clear" message appears on the page.
- When the queue has items: no page-level banner appears; the widget renders normally with its full items and severity signal; the widget's own all-clear card is not suppressed.
- The banner uses the existing `.shield-needs-attention__all-clear-*` CSS classes (green accent, shield icon, title, subtitle).
- The `NeedsAttentionQueue` widget accepts and respects a `suppress_all_clear` boolean in its `action_data`.
- `AttentionItemsProvider::buildActionSummary()` is called once per page load to determine queue state; `NeedsAttentionQueue::payload()` is called at most once and only when the queue is empty.

---

## REM-004 — Configure Landing: Four Data-Heavy Sections Is a Data Dump *(Completed — Implemented 2026-03-02)*

**Status:** Completed. Configure landing now renders only the config-channel hero meter and Security Zones grid, includes a compact posture summary line, and removes the Quick Links/stats/overview sections that duplicated sidebar and per-meter surfaces.

### Problem Description

The Configure landing template (`configure_landing.twig`) renders four major sections:

1. **Section 1 (hero row):** Config-channel `MeterCard` (hero, spans 7 of 12 columns) + Quick Links card (5 of 12 columns)
2. **Section 2 ("Posture Snapshot"):** Four `stat_box.twig` instances: "Good Areas" (count), "Needs Work" (count), "Critical Areas" (count), "Security Zones" (count)
3. **Section 3 ("Configuration Areas"):** Per-meter `MeterCard` components — all meter slugs from `MeterHandler::METERS` excluding `summary` and `overall_config`. This is potentially 6–8 individual meter cards.
4. **Section 4 ("Security Zones"):** 8 zone quick-link buttons

**What is wrong with this:**

Section 2 is a numerical summary of Section 3. You cannot have both on the same landing page because Section 2 says "3 good, 2 needs work" and Section 3 shows exactly which 3 are good and which 2 need work. Seeing both at once is redundant and adds cognitive load without adding information.

Section 1's Quick Links card includes a "Security Zones" link. Section 4 is also a Security Zones grid. Zones appear as a destination twice on the same page (see also REM-005).

The combined effect is a landing page with more data than the old dashboard it replaced. A user coming to Configure mode to adjust a single setting has to scroll past:
- A hero meter
- A quick links card
- Four stat boxes
- Six to eight meter cards
- Eight zone buttons

...before they can act. This is the same problem the operator modes project was designed to solve, just reorganised.

**What Configure mode landing should be:**

The Configure landing should orient the user toward their configuration posture and give them one primary action: go to the zone that needs attention. Everything else is secondary.

The correct model:
- Hero: the single config-channel posture score (keep this — it is the right signal)
- Primary action: get to the zone that needs the most attention, or any zone
- Secondary: a compact summary of which areas are good vs need work (one line, not four stat boxes)

### Affected Files

| File | Role |
|---|---|
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageConfigureLanding.php` | PHP handler — supplies all four sections |
| `templates/twig/wpadmin/plugin_pages/inner/configure_landing.twig` | Template — renders all four sections |

### Exact Location in Code

**`configure_landing.twig`:**

- Lines 4–27: Section 1 — hero meter (left column keep) + Quick Links card (right column **delete**)
- Lines 29–38: Section 2 — Posture Snapshot stat boxes (**delete entirely**)
- Lines 40–49: Section 3 — Configuration Areas meter cards (**delete entirely**)
- Lines 51–69: Section 4 — Security Zones grid (**keep; move to directly below the hero**)

**`PageConfigureLanding.php`:**

- `buildOverviewMeterCards()` (lines 96–122): builds the per-meter cards for Section 3
- `buildConfigureStats()` (lines 154–173): builds the four stat boxes for Section 2
- Note: `buildOverviewMeterCards()` has a side effect — it populates `$this->cachedMeterTrafficCounts`. `buildConfigureStats()` depends on this cache. If Section 3 is removed, `getMeterTrafficCounts()` must still be computed independently for any remaining stats display.

### Required Changes

**`configure_landing.twig`:**

Remove Section 2 (the four stat boxes at `data-configure-section="stats"`, lines 29–38) entirely. Remove Section 3 (the per-meter overview cards at `data-configure-section="overview-meters"`, lines 40–49) entirely. Remove the Quick Links card from Section 1 (the `col-12 col-xl-5` column containing the Quick Links card, lines 13–26) — see REM-005 for the rationale.

What remains after these removals is the hero meter (Section 1, left column only) and the Security Zones grid (Section 4). Move the zones grid to immediately below the hero meter. Add a single posture summary line below the hero meter progress bar.

The resulting template structure must be:

```twig
{% extends '/wpadmin/plugin_pages/base_inner_page.twig' %}
{% block inner_page_body %}

    {# Hero posture meter + single summary line #}
    <div data-configure-section="hero">
        <h5 class="mb-2">{{ strings.posture_title }}</h5>
        <div class="progress-metercard progress-metercard-hero progress-metercard-summary"
             data-meter_slug="summary"
             data-meter_channel="config">
            {{ content.hero_meter|raw }}
        </div>
        {% if vars.posture_summary|default('') is not empty %}
            <p class="text-muted small mt-2 mb-0">{{ vars.posture_summary }}</p>
        {% endif %}
    </div>

    {# Security Zones — primary action surface #}
    <div class="card shield-card mt-4" data-configure-section="zones">
        <div class="shield-card-accent status-good"></div>
        <div class="card-body">
            <h5 class="card-title mb-1">{{ strings.zones_title }}</h5>
            <p class="text-muted small mb-3">{{ strings.zones_subtitle }}</p>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-2">
                {% for zone in vars.zone_links|default([]) %}
                    <div class="col">
                        <a href="{{ zone.href }}"
                           class="btn btn-outline-secondary w-100 text-start d-flex align-items-center gap-2"
                           data-configure-zone="{{ zone.slug }}">
                            <i class="{{ zone.icon_class }}" aria-hidden="true"></i>
                            <span>{{ zone.label }}</span>
                        </a>
                    </div>
                {% endfor %}
            </div>
        </div>
    </div>

{% endblock %}
```

Note the zones card now uses `status-good` (green) — not `status-neutral` and not `status-info`. The Configure mode accent is green. The old `status-neutral` was incorrect (see also CC-002).

**`PageConfigureLanding.php` required change for `posture_summary`:**

Add a `posture_summary` string to `getLandingVars()` or `getLandingStrings()`:

```php
$counts = $this->getMeterTrafficCounts();
$parts = [];
if ( $counts['critical'] > 0 ) {
    $parts[] = sprintf( _n( '%s critical area', '%s critical areas', $counts['critical'], '...' ), $counts['critical'] );
}
if ( $counts['warning'] > 0 ) {
    $parts[] = sprintf( _n( '%s area needs work', '%s areas need work', $counts['warning'], '...' ), $counts['warning'] );
}
if ( empty( $parts ) ) {
    $parts[] = __( 'All configuration areas look good', 'wp-simple-firewall' );
}
$postureSummary = implode( ' · ', $parts );
```

### Acceptance Criteria

- The Configure landing renders exactly two visual sections: the hero meter (with posture summary line) and the Security Zones grid.
- Section 2 (stat boxes `data-configure-section="stats"`) is deleted from the template and from `PageConfigureLanding::buildConfigureStats()` output.
- Section 3 (per-meter overview cards `data-configure-section="overview-meters"`) is deleted from the template and `buildOverviewMeterCards()` need not be called.
- The Quick Links card is deleted (see REM-005).
- A user arriving at Configure mode immediately sees the posture score and can navigate to any security zone with one click.
- The Security Grades page (accessible from the sidebar) is the canonical per-meter detail surface; the Configure landing does not duplicate it.

---

## REM-005 — Configure Landing: Zones Appear Twice and Quick Links Points to Wrong Entry *(Completed — Implemented 2026-03-02)*

**Status:** Completed. The Quick Links card is removed from Configure landing, redundant "Security Zones" duplication is eliminated, and obsolete Quick Links href/string keys were removed from the landing contract.

### Problem Description

**Problem A — Zones appear twice:**

The Configure landing has a "Quick Links" card (Section 1, top right) with the following links:
- Security Grades
- Security Zones ← link 1
- Rules Manager
- Import/Export Tool

It also has Section 4: the Security Zones quick-link grid with all 8 individual zone buttons.

"Security Zones" appears as a navigation destination twice on the same landing page.

**Problem B — "Security Zones" quick link points to Security Admin zone, not a zones overview:**

In `PageConfigureLanding::getLandingHrefs()`:

```php
'zones_home' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ZONES, Secadmin::Slug() ),
```

`Secadmin::Slug()` is the **Security Admin** zone — pin protection, security admin login access. This is a specific zone, not a zones overview or landing. A user clicking "Security Zones" from the Quick Links card lands in the Security Admin settings, which is likely not what they wanted.

The two occurrences of "Zones" also navigate to **different places**:
- Quick Links "Security Zones" → Security Admin zone settings
- Section 4 zone grid → individual zone of choice

This is confusing and inconsistent.

### Affected Files

| File | Role |
|---|---|
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageConfigureLanding.php` | `getLandingHrefs()` — defines `zones_home` incorrectly |
| `templates/twig/wpadmin/plugin_pages/inner/configure_landing.twig` | Template — renders both zones references |
| `src/Zones/Zone/Secadmin.php` | `Secadmin::Slug()` — the incorrect target |
| `src/Controller/Plugin/PluginNavs.php` | Check if there is a zones overview/home nav constant |

### Exact Location in Code

**`PageConfigureLanding.php`, `getLandingHrefs()`, line 53:**

```php
'zones_home' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ZONES, Secadmin::Slug() ),
```

**`configure_landing.twig`, lines 17–25 (Quick Links card):**

```twig
<a href="{{ hrefs.zones_home }}" class="list-group-item list-group-item-action">{{ strings.link_zones }}</a>
```

### Required Changes

**Remove the entire Quick Links card from `configure_landing.twig`.** This is the `col-12 col-xl-5` column (lines 13–26 of the current template) that contains the card with "Security Grades", "Security Zones", "Rules Manager", and "Import/Export Tool" links.

Reasons:
- REM-004 removes Sections 2 and 3, making the Quick Links card the only thing in the right column of the hero row — an entire Bootstrap column with a single card of four links duplicating the sidebar.
- The "Security Zones" link (`hrefs.zones_home`) incorrectly targets `Secadmin::Slug()` (the Security Admin zone specifically) — it is a bug, and fixing it is pointless because `NAV_ZONES/SUBNAV_ZONES_OVERVIEW` maps to `PageConfigureLanding::class` (the current page), so a corrected link would navigate back to itself.
- The sidebar already provides access to Security Grades, all Security Zones, Rules Manager, and Import/Export. The Quick Links card adds zero navigation value.

**`PageConfigureLanding.php`:** Remove `zones_home`, `grades`, `rules_manage`, and `tools_import` from `getLandingHrefs()`. Remove `quick_links_title`, `link_grades`, `link_zones`, `link_rules`, `link_tools` from `getLandingStrings()`. These are no longer needed once the Quick Links card is removed.

The `zones_home` href key (`Secadmin::Slug()` bug) does not need to be fixed — the entire href is being removed.

### Acceptance Criteria

- "Security Zones" appears as a navigation destination at most once on the Configure landing.
- The "Security Zones" quick link from the Quick Links card is **removed** — `NAV_ZONES/SUBNAV_ZONES_OVERVIEW` routes to `PageConfigureLanding` (the current page), making any "Security Zones overview" link a self-link with no value.
- The Quick Links card is deleted entirely (see REM-004 and REM-005 Required Changes).
- There are no two links on the same page that say "Security Zones" but navigate to different destinations.

---

## REM-006 — Reports Landing: No Orientation, No Framing, Opens With Misplaced CTA Buttons

**Status:** Completed. The reports landing CTA toolbar card is removed, section framing headings are present for charts and recent reports, and the contextual action is reduced to an inline reports-list CTA.

### Problem Description

The Reports landing template (`reports_landing.twig`) is:

```twig
{% extends '/wpadmin/plugin_pages/base_inner_page.twig' %}
{% block inner_page_body %}
    <div class="card shield-card mb-3">
        <div class="shield-card-accent status-info"></div>
        <div class="card-body d-flex justify-content-end gap-2 flex-wrap">
            <a href="{{ hrefs.reports_list }}" class="btn btn-outline-primary">...</a>
            <a href="{{ hrefs.reports_charts }}" class="btn btn-outline-primary">...</a>
            <a href="{{ hrefs.reports_settings }}" class="btn btn-outline-primary">...</a>
        </div>
    </div>
    {{ content.summary_charts|raw }}
    {{ content.recent_reports|raw }}
{% endblock %}
```

Problems:

1. **Three CTA buttons right-aligned in a card with no title.** This is a button toolbar with no label. A new user has no idea what "Security Reports", "Charts & Trends", and "Alert Settings" mean in this context or why they are the three options.

2. **The buttons duplicate the sidebar.** The Reports mode sidebar already shows: Security Reports, Charts & Trends, Alert Settings. Putting the same three links in the landing body as buttons adds zero navigation value. They are redundant.

3. **`content.summary_charts|raw` is dumped without a heading or intro.** The user sees charts with no "here's what the last 30 days looked like" framing.

4. **`content.recent_reports|raw` is dumped without a heading.** The user sees a table with no label.

5. **The status-info accent bar on the CTA card is amber/warning in the Reports prototype but renders teal (`status-info`) here.** Reports mode accent colour is `#edb41d` (warning/amber). The accent bar should reflect the Reports mode colour, not default info.

6. **No mode introduction.** The page title comes from `PageReportsLanding::getLandingTitle()` ("Reports") via `base_inner_page.twig`. But there is no subtitle or orientation copy that says "this is your security activity at a glance" before the charts begin.

### Affected Files

| File | Role |
|---|---|
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageReportsLanding.php` | PHP handler — builds content, hrefs, strings |
| `templates/twig/wpadmin/plugin_pages/inner/reports_landing.twig` | Template — all of the above |
| `assets/css/shield/reports.scss` | SCSS — Reports-specific styles |

### Exact Location in Code

**`reports_landing.twig`, line 5:**

```twig
<div class="shield-card-accent status-info"></div>
```

This should be `status-warning` (amber) to match the Reports mode accent colour.

**`reports_landing.twig`, lines 6–10:**

```twig
<div class="card-body d-flex justify-content-end gap-2 flex-wrap">
    <a href="{{ hrefs.reports_list }}" class="btn btn-outline-primary">...</a>
    ...
</div>
```

This is the entire CTA card. No title, no label, no context. Just three right-aligned buttons.

### Required Changes

**`reports_landing.twig`:**

Delete the entire CTA card (the `<div class="card shield-card mb-3">` block, lines 1–9 of the current template body). It duplicates the sidebar and has no title. Do not restructure it — remove it entirely.

Use the following template structure:

```twig
{% extends '/wpadmin/plugin_pages/base_inner_page.twig' %}
{% block inner_page_body %}

    {# Charts section #}
    <h5 class="mb-2">{{ strings.charts_title }}</h5>
    {{ content.summary_charts|raw }}

    {# Recent reports section #}
    <div class="mt-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">{{ strings.recent_reports_title }}</h5>
            <a href="{{ hrefs.reports_list }}" class="btn btn-sm btn-outline-secondary">{{ strings.cta_reports_list }}</a>
        </div>
        {{ content.recent_reports|raw }}
    </div>

{% endblock %}
```

The "Open Reports List" button is placed inline next to the "Recent Reports" heading as a contextual action — not as a standalone toolbar. The "Charts & Trends" and "Alert Settings" links are in the sidebar; they do not need to appear in the page body.

**`PageReportsLanding.php`:**

Add `charts_title` and `recent_reports_title` to `getLandingStrings()`:

```php
protected function getLandingStrings() :array {
    $strings = [
        'charts_title'         => __( 'Activity Overview', 'wp-simple-firewall' ),
        'recent_reports_title' => __( 'Recent Reports', 'wp-simple-firewall' ),
    ];
    foreach ( PluginNavs::reportsWorkspaceDefinitions() as $subNav => $definition ) {
        $strings[ 'cta_reports_'.$subNav ] = (string)( $definition[ 'landing_cta' ] ?? '' );
    }
    return $strings;
}
```

### Acceptance Criteria

- The Reports landing has a clear heading above the charts section.
- The Reports landing has a clear heading above the recent reports section.
- The three-button CTA toolbar card at the top is deleted entirely.
- Accent bars on the Reports landing use the warning (amber) status colour, not info (teal).
- A first-time visitor can understand what they are looking at within 5 seconds of the page loading.

---

## REM-007 � Investigate Landing: Four Heading Layers Before the Subject Grid *(Completed � Implemented 2026-03-02)*

**Status:** Completed. The investigate landing now removes redundant selector heading layers so the subject tile grid renders immediately under the page header/subtitle.

### Problem Description

The Investigate landing page stacks four separate heading or intro elements before the subject tile grid appears:

1. **Page title** from `base_inner_page.twig` — rendered from `strings.inner_page_title` = "Investigate". This is a full page-level `<h2>` or equivalent heading.

2. **`<h5>{{ strings.selector_title }}</h5>`** inside `investigate_landing.twig` — currently set to `"Choose A Subject To Investigate"` in `PageInvestigateLanding::getLandingStrings()`.

3. **`<p class="text-muted small mb-3">{{ strings.selector_intro }}</p>`** — currently set to `"Choose a subject tile to navigate directly to the relevant investigation page."`

4. **`<div class="investigate-landing__section-label">{{ strings.selector_section_label }}</div>`** — currently set to `"What Do You Want To Investigate?"` — uppercase, small, letter-spaced.

The result is a page whose visible content, from top to bottom, begins:

> **Investigate** *(page title, large)*
> Choose A Subject To Investigate *(h5)*
> Choose a subject tile to navigate directly to the relevant investigation page. *(small muted text)*
> WHAT DO YOU WANT TO INVESTIGATE? *(uppercase section label)*
> [grid of tiles starts here]

This is four layers of text all saying variants of the same thing. By the time the user reaches the tiles, they have read the word "investigate" three times and "subject" twice, learning nothing they did not already know from the page title.

The page title ("Investigate") from `base_inner_page.twig` already fully establishes the page context. Everything below it before the grid is redundant.

### Affected Files

| File | Role |
|---|---|
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php` | PHP handler — `getLandingStrings()` defines all three redundant strings |
| `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig` | Template — renders all three before the grid |

### Exact Location in Code

**`investigate_landing.twig`, lines 5–10:**

```twig
<section class="investigate-landing__selector-shell" data-investigate-section="selector">
    <h5 class="mb-1">{{ strings.selector_title }}</h5>
    <p class="text-muted small mb-3">{{ strings.selector_intro }}</p>
    <div class="investigate-landing__section-label">{{ strings.selector_section_label }}</div>
    <div class="investigate-landing__subject-grid">
```

**`PageInvestigateLanding.php`, `getLandingStrings()`, lines 44–48:**

```php
return [
    'selector_title'         => __( 'Choose A Subject To Investigate', 'wp-simple-firewall' ),
    'selector_intro'         => __( 'Choose a subject tile to navigate directly to the relevant investigation page.', 'wp-simple-firewall' ),
    'selector_section_label' => __( 'What Do You Want To Investigate?', 'wp-simple-firewall' ),
    'label_pro'              => __( 'PRO', 'wp-simple-firewall' ),
];
```

### Required Changes

**`investigate_landing.twig`:**

Remove the `<h5>`, `<p>`, and section label. The grid should follow immediately after the `<section>` wrapper:

```twig
{% extends '/wpadmin/plugin_pages/base_inner_page.twig' %}
{% block inner_page_body %}
    <div class="investigate-landing">
        <section class="investigate-landing__selector-shell" data-investigate-section="selector">
            <div class="investigate-landing__subject-grid">
                {# ... tile loop unchanged ... #}
            </div>
        </section>
    </div>
{% endblock %}
```

The page title from `base_inner_page.twig` ("Investigate") combined with the page subtitle from `getLandingSubtitle()` ("Investigate user activity, request logs, and IP behavior.") already provides full orientation. No additional in-body heading is needed.

**`PageInvestigateLanding.php`, `getLandingStrings()`:**

Remove `selector_title`, `selector_intro`, and `selector_section_label`. Keep only `label_pro`:

```php
protected function getLandingStrings() :array {
    return [
        'label_pro' => __( 'PRO', 'wp-simple-firewall' ),
    ];
}
```

The `.investigate-landing__section-label` SCSS class and the `investigate-landing__selector-shell` section wrapper may be retained for structural CSS purposes, but the element itself (`<div class="investigate-landing__section-label">{{ strings.selector_section_label }}</div>`) must be removed from the template. The grid should appear directly inside the section shell.

### Acceptance Criteria

- The Investigate landing renders at most one heading-level element before the subject grid.
- The page title from `base_inner_page.twig` is the only visible "Investigate" heading on the page.
- The subject tiles appear high on the page without requiring the user to scroll past introductory text.
- `selector_title`, `selector_intro`, and `selector_section_label` are all removed from `getLandingStrings()` and from the template. Only `label_pro` remains in `getLandingStrings()`.

---

## REM-008 — `PageOperatorModeLanding` Does Not Extend `BasePluginAdminPage` *(Closed — Not a Gap)*

### Problem Description

Every mode landing page in the system extends `PageModeLandingBase` which extends `BasePluginAdminPage`. This gives them:

- A rendered page header (`strings.inner_page_title`, `strings.inner_page_subtitle`)
- A page icon (`imgs.inner_page_title_icon`)
- A breadcrumb anchor (the first crumb is always "Shield Security" → mode selector)
- Access to the full `BasePluginAdminPage` render hierarchy including nonce handling and contextual href building

**`PageOperatorModeLanding` extends `BaseRender` directly** — not `BasePluginAdminPage`. This means the mode selector page:

- Has no `inner_page_title` or `inner_page_subtitle` rendered by the base template
- Has no page icon
- Has no breadcrumb output (the breadcrumb system in `BuildBreadCrumbs.php` may not receive a meaningful nav context from this page class)
- Sits outside the standard render hierarchy that all other pages use

The mode selector is the most important page in the entire operator modes system — it is the front door. It has weaker structural underpinning than any other page.

Note: the operator mode landing uses a different rendering path specifically because it does not need the standard page header — it is designed to fill the full inner content area with the hero/strip layout. However this means it also misses the breadcrumb infrastructure, which IS needed.

### Affected Files

| File | Role |
|---|---|
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageOperatorModeLanding.php` | The class — extends `BaseRender` |
| `src/ActionRouter/Actions/Render/PluginAdminPages/BasePluginAdminPage.php` | The standard base page — review what it adds vs `BaseRender` |
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageModeLandingBase.php` | The mode landing base — could be extended if appropriate |
| `src/Utilities/Navigation/BuildBreadCrumbs.php` | Breadcrumb logic — verify mode selector breadcrumb is produced |
| `templates/twig/wpadmin/plugin_pages/base_inner_page.twig` | Base template — understand what `inner_page_title` and breadcrumbs it renders |

### Concrete Findings — No Change Required

All three questions have been answered by reading the actual source files. The verdict is that `PageOperatorModeLanding` extending `BaseRender` is correct and intentional, not a bug.

**Finding 1 — Page title header suppression:**

`templates/twig/wpadmin/plugin_pages/base_inner_page.twig` contains:

```twig
{% if strings.inner_page_title|default('') is not empty %}
    {# renders page title header block #}
{% endif %}
```

The entire page title/header block is gated on `strings.inner_page_title` being non-empty. `PageOperatorModeLanding` extends `BaseRender` and never sets `inner_page_title`, so the header block is silently skipped. If `PageOperatorModeLanding` were changed to extend `BasePluginAdminPage` or `PageModeLandingBase`, `inner_page_title` would be required to be set (it is an abstract return from `getLandingTitle()`), and the header WOULD render above the hero card — breaking the hero layout. No suppression flag mechanism is needed because the natural absence of `inner_page_title` already suppresses the header.

**Finding 2 — Breadcrumb output for the mode selector:**

`src/Utilities/Navigation/BuildBreadCrumbs.php`, `parse()`: The first crumb ("Shield Security" → dashboard) is added via `appendCrumbIfNotCurrentRoute()`. This method suppresses the crumb when the current route IS `NAV_DASHBOARD/SUBNAV_DASHBOARD_OVERVIEW`. The mode selector IS that route. Therefore, the breadcrumb system correctly produces **zero breadcrumbs** for the mode selector page. Zero breadcrumbs is correct — the mode selector IS the root. There is no breadcrumb gap.

**Finding 3 — Header suppression mechanism:**

Already confirmed above. The existing mechanism (`inner_page_title` absence → header block skipped) is sufficient. No `flags.suppress_page_header` addition is needed.

**Conclusion — This is not a gap:**

The class hierarchy in `PageOperatorModeLanding` is intentional and correct:
- Zero breadcrumbs → correct (it is the root page)
- No page title header → correct (the hero card fills the content area)
- Extending `BaseRender` instead of `BasePluginAdminPage` → correct (prevents unwanted header injection)

### Required Changes

**None.** The implementation is correct as-is. Remove REM-008 from the implementation queue. Document the class hierarchy rationale in a code comment in `PageOperatorModeLanding.php`:

```php
// Extends BaseRender (not BasePluginAdminPage) intentionally:
// - This page has no inner_page_title, so base_inner_page.twig's header block is suppressed automatically.
// - This page is NAV_DASHBOARD/SUBNAV_DASHBOARD_OVERVIEW — the BuildBreadCrumbs root — so zero breadcrumbs is correct.
// - The hero/strip layout fills the full content area without a page title header above it.
```

### Acceptance Criteria

- A code comment in `PageOperatorModeLanding.php` explains why it extends `BaseRender` rather than `BasePluginAdminPage`.
- No code changes are made to the class hierarchy.
- The hero/strip layout is unchanged.

---

## REM-009 — No Persistent Visual Mode-Color Identity on Mode Landing Pages

### Problem Description

Each operator mode has a defined accent colour:

| Mode | Accent | CSS Status Class |
|---|---|---|
| Actions Queue | `#c62f3e` | `status-critical` |
| Investigate | `#0ea8c7` | `status-info` |
| Configure | `#008000` | `status-good` |
| Reports | `#edb41d` | `status-warning` |

The mode selector landing uses these colours correctly: the Actions hero card has a red accent bar, the Investigate strip card has a teal accent bar, etc. The sidebar back link is styled, and the sidebar mode headings use the mode label.

However, when you navigate to any mode landing page (Actions Queue, Investigate, Configure, Reports), the page itself gives no persistent visual signal that says "you are in [Mode Name]." The only mode-identity signals are:

1. The breadcrumb: `Shield Security » Actions Queue` (text only, no colour)
2. The sidebar: shows mode name as a non-clickable heading

The mode landing page body itself has no coloured mode indicator. A user on the Configure landing and a user on the Investigate landing see pages with nearly identical visual structure and chrome — both have the Shield header, both have grey backgrounds, both have the same breadcrumb style.

The prototype establishes a clear precedent: each mode has a colour. That colour should be visible when you are in that mode, not just on the selector.

### Affected Files

| File | Role |
|---|---|
| `templates/twig/wpadmin/plugin_pages/base_inner_page.twig` | Base template — the correct place to add a mode colour accent |
| `src/ActionRouter/Actions/Render/PluginAdminPages/BasePluginAdminPage.php` | Base page handler — may supply `vars.current_mode` or similar |
| `src/Controller/Plugin/PluginNavs.php` | `modeForNav()`, `modeAccentStatus()` — check if a mode→accent colour method exists |
| `assets/css/shield/dashboard.scss` | SCSS — `.shield-card-accent` and status colour classes already exist |

### Concrete Findings

**Finding 1 — Nav access in the render pipeline:**

All render action classes receive `action_data` as a populated array (via the action router dispatch). The current nav is available in every page class as:

```php
$nav = $this->action_data[ \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants::NAV_ID ] ?? '';
// Constants::NAV_ID = 'nav'
```

`PluginNavs::modeForNav(string $nav)` then maps this to a mode string. Both `BasePluginAdminPage` and `BaseRender` have `$this->action_data` populated — the nav is available in any page class.

**Finding 2 — `base_inner_page.twig` does not currently receive `vars.current_mode`:**

No `vars.current_mode` or `vars.mode_accent_status` variable is passed today. Adding it requires a code change in the PHP handler. The best place is `PageModeLandingBase` (since all four mode landing pages extend it), not `BasePluginAdminPage` (which would apply to every admin page in the plugin, not just mode landing pages).

**Finding 3 — Least-invasive approach:**

Add `vars.mode_accent_status` in `PageModeLandingBase::getAllRenderDataArrays()`. The nav is already available via `$this->action_data[Constants::NAV_ID]`. The computed value flows through to `base_inner_page.twig` via the standard merged render data. Add the accent bar in `base_inner_page.twig` conditional on `vars.mode_accent_status` being non-empty — this is safe because non-mode pages do not extend `PageModeLandingBase` and therefore never set this variable.

`NavMenuBuilder::resolveCurrentMode()` (`src/Modules/Plugin/Lib/NavMenuBuilder.php`, line 273) uses identical logic — `$nav === PluginNavs::NAV_DASHBOARD` → empty mode; otherwise `PluginNavs::modeForNav($nav)`. Copy this logic rather than calling the NavMenuBuilder from a render action.

### Required Changes

**Mode accent bar at the top of the content area:**

In `base_inner_page.twig`, immediately inside `{% block inner_page_header %}` (or just above `{% block inner_page_body %}`), add a thin accent bar whose colour is driven by the current mode:

```twig
{% if vars.mode_accent_status|default('') is not empty %}
    <div class="shield-card-accent status-{{ vars.mode_accent_status }} mb-3"
         style="border-radius: 4px; width: 100%;"></div>
{% endif %}
```

Supply `vars.mode_accent_status` from `PageModeLandingBase::getAllRenderDataArrays()`:

```php
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

protected function getAllRenderDataArrays() :array {
    $data = parent::getAllRenderDataArrays();
    $nav = $this->action_data[ Constants::NAV_ID ] ?? '';
    $currentMode = $nav === PluginNavs::NAV_DASHBOARD ? '' : PluginNavs::modeForNav( $nav );
    $modeAccentMap = [
        PluginNavs::MODE_ACTIONS    => 'critical',
        PluginNavs::MODE_INVESTIGATE => 'info',
        PluginNavs::MODE_CONFIGURE  => 'good',
        PluginNavs::MODE_REPORTS    => 'warning',
    ];
    $data[ 90 ] = [
        'vars' => [
            'mode_accent_status' => $modeAccentMap[ $currentMode ] ?? '',
        ],
    ];
    return $data;
}
```

The priority index `90` places this data array after the base page data (priority `25` in `BasePluginAdminPage::getAllRenderDataArrays()`) so it is merged last and cannot be overridden accidentally.

### Acceptance Criteria

- When viewing any mode landing page (or any page within a mode), there is a visible colour signal indicating which mode is active.
- The colour matches the mode's defined accent colour (critical/red for Actions, info/teal for Investigate, good/green for Configure, warning/amber for Reports).
- The mode selector landing (no mode active) shows no accent colour.
- The signal does not break or visually conflict with any existing page chrome.

---

## REM-010 — "Always Start In" Preference Form Is Visually Orphaned and Lacks Feedback

### Problem Description

The mode selector landing page (`operator_mode_landing.twig`) ends with:

```twig
<div class="card mt-3 border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="{{ hrefs.operator_mode_switch }}" ...>
            <label>{{ strings.set_default_mode }}</label>
            <select name="mode">...</select>
            <div class="form-text">{{ strings.start_mode_help }}</div>
            <button type="submit">{{ strings.save_default_mode }}</button>
        </form>
    </div>
</div>
```

Problems:

1. **The form is separated from the mode cards above it by whitespace alone.** There is no visual connection between "click Investigate to enter that mode" and "set your default starting mode." A user who scrolls only to the strip cards — which is most users — never sees this form.

2. **The form card uses `border-0 shadow-sm`**, which gives it a weaker visual presence than the hero card and strip cards above. It looks like a footer rather than a meaningful control.

3. **No success state.** After submitting the form, the page performs a POST and presumably redirects. The template contains no success flash message or confirmation state. A user who saves a default has no on-page confirmation that it worked.

4. **The form label is "Always start in" which is fine, but the `form-text` below the select says "Choose where Shield opens when you select the plugin menu."** This is repetitive — the label and the help text say the same thing.

5. **The select includes "Mode Selector" as the first option (empty value) but it is labelled with `PluginNavs::modeLabel('')`.** Check what string `modeLabel('')` returns. If it returns "Mode Selector" or "Dashboard", ensure this is clearly communicated as the "reset to default" option, since it is the only non-mode value in the list.

### Affected Files

| File | Role |
|---|---|
| `templates/twig/wpadmin/plugin_pages/inner/operator_mode_landing.twig` | Template — form is at the bottom, lines 46–63 |
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageOperatorModeLanding.php` | PHP handler — `buildModeOptions()`, strings |
| `src/ActionRouter/Actions/OperatorModeSwitch.php` | AJAX action — handles the POST and sets the preference |
| `src/Controller/Plugin/PluginNavs.php` | `modeLabel('')` — check what string is returned for the empty mode value |

### Exact Location in Code

**`operator_mode_landing.twig`, lines 46–63 (full form block):**

```twig
<div class="card mt-3 border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="{{ hrefs.operator_mode_switch }}" class="row g-2 align-items-end">
            <div class="col-md-8 col-xl-9">
                <label for="shield_default_operator_mode" class="form-label mb-1">{{ strings.set_default_mode }}</label>
                <select class="form-select" id="shield_default_operator_mode" name="mode">
                    {% for option in vars.mode_options %}
                        <option value="{{ option.mode }}" {{ option.mode == vars.default_mode ? 'selected' : '' }}>{{ option.label }}</option>
                    {% endfor %}
                </select>
                <div class="form-text">{{ strings.start_mode_help }}</div>
            </div>
            <div class="col-md-4 col-xl-3">
                <button type="submit" class="btn btn-outline-secondary w-100">{{ strings.save_default_mode }}</button>
            </div>
        </form>
    </div>
</div>
```

### Required Changes

**Structural change — move form closer to the mode cards:**

The form must appear immediately below the strip cards with minimal vertical gap, styled as a compact inline settings control — not as a separate card. Remove the `<div class="card mt-3 border-0 shadow-sm">` wrapper. Replace it with a borderless inline row directly below the strip:

```twig
<div class="mt-3 pt-2 border-top" style="max-width: 780px;">
    <form ...>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <label class="text-muted small mb-0 flex-shrink-0">{{ strings.set_default_mode }}:</label>
            <select class="form-select form-select-sm" style="max-width: 220px;" ...>...</select>
            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ strings.save_default_mode }}</button>
        </div>
    </form>
</div>
```

This makes the preference a small inline control at the bottom of the selector, visually connected to the mode cards, rather than a separate card widget.

**Success feedback:**

Check `OperatorModeSwitch.php` — after the preference is saved, the action likely redirects back to the mode selector. The redirect URL could include a `?saved=1` query param, and the template could render a brief success notice when this param is present.

Alternatively, convert the form to a JS AJAX submit using the existing action router AJAX mechanism, with an inline success state (a brief green flash on the select/button area).

**Remove redundant help text:**

Either remove `strings.start_mode_help` or change it to something the label does not already say. For example: "This only affects clicking the top-level Shield menu item. Direct sidebar links always go to their mode."

**`modeLabel('')` string confirmed:**

`PluginNavs::modeLabel('')` returns `"Mode Selector"`. This is a clear, user-facing label that communicates "this is the home/default state." The select option for the empty mode value will render as `"Mode Selector"`, which is unambiguous. No change needed to the label string — it is already correct.

**AJAX response structure confirmed:**

`OperatorModeSwitch` (`src/ActionRouter/Actions/OperatorModeSwitch.php`) on AJAX returns `{page_reload: false, mode: string}`. This means the form CAN be converted to an AJAX submit using the existing Shield action router AJAX mechanism (`shield_dynamic_action_button` or a custom fetch). On success, the JS receives `{mode: 'actions'}` (or whichever mode was selected) and can briefly flash a success state on the submit button or select element without a page reload. This is the preferred approach — it avoids a full page redirect and gives instant feedback.

### Acceptance Criteria

- The preference form is visually adjacent to the mode cards, not separated by large whitespace.
- The form does not use a heavy card container — it is a light, compact control.
- After saving, the user receives visible confirmation (either via redirect + flash message or inline JS feedback).
- The help text and the label do not repeat the same information.
- The "Mode Selector" option in the select is clearly labelled as the default/home option.

---

## Cross-Cutting Issues Not Covered in Individual Gaps

### CC-001 — `actions_queue_landing.twig` has no structural differentiation between "has items" and "all clear" states *(Completed — Implemented 2026-03-02)*

The CTA card no longer renders identically across queue states. `actions_queue_landing.twig` now uses `flags.queue_is_empty` to hide the "Open Scan Results" button when the queue is clear, while always showing "Run Manual Scan". When the queue has active items, both CTA buttons render.

**Files:** `actions_queue_landing.twig`, `PageActionsQueueLanding::getLandingFlags()`

### CC-002 — The `configure_landing.twig` Quick Links card uses `status-info` (teal) accent *(Completed — Resolved 2026-03-02)*

Resolved by removal of the Configure landing Quick Links card during REM-004/REM-005 completion. There is no longer a Configure Quick Links accent mismatch because the duplicated card no longer exists.

**File:** `templates/twig/wpadmin/plugin_pages/inner/configure_landing.twig` (Quick Links block removed)

### CC-003 — `reports_landing.twig` CTA card uses `status-info` (teal) accent *(Completed — Resolved 2026-03-02)*

Resolved by removal of the Reports landing CTA toolbar card during REM-006 completion. The prior `status-info` accent bar no longer renders because the card was deleted.

**File:** `templates/twig/wpadmin/plugin_pages/inner/reports_landing.twig` (CTA card removed)

### CC-004 — No unit test coverage for Actions Queue landing page data contract

All test files in `tests/Unit/ActionRouter/Render/` have been inventoried. Confirmed:

- `PageConfigureLandingBehaviorTest.php` — EXISTS ✓
- `PageReportsLandingBehaviorTest.php` — EXISTS ✓
- `PageInvestigateLandingBehaviorTest.php` — EXISTS ✓
- `PageActionsQueueLandingBehaviorTest.php` — EXISTS ✓ *(created as part of REM-002 completion)*

**`PageActionsQueueLandingBehaviorTest.php` now exists** and asserts:
- `getLandingContent()` returns both `action_meter` and `needs_attention_queue` keys
- `getLandingContent()` calls `MeterCard::class` with `meter_slug: 'summary'`, `meter_channel: 'action'`, `is_hero: true`
- `getLandingContent()` calls `NeedsAttentionQueue::class`
- `getLandingHrefs()` returns `scan_results` and `scan_run` keys
- `getLandingStrings()` preserves Actions Queue CTA labels

**Additional channel coverage delivered:** `MeterCardChannelTest.php` now includes explicit action-channel assertions (`meter_channel: action`) to confirm channel-specific meter retrieval remains correct.

**`PageConfigureLandingBehaviorTest.php` updated after REM-004/REM-005:**
- Landing content assertions now verify hero-meter-only output and explicitly confirm `overview_meter_cards` is removed.
- Landing vars assertions now verify `posture_summary` + `zone_links` and explicitly confirm `configure_stats` is removed.
- Landing href/string assertions now verify Quick Links keys are removed from the render contract.

**`PageReportsLandingBehaviorTest.php` updated after REM-006:**
- String assertions now include `charts_title` and `recent_reports_title`, while preserving `cta_reports_list` and validating non-empty workspace CTA labels.
- `test_landing_content_renders_charts_summary_and_reports_table()` remains valid and continues to confirm `ChartsSummary` and `ReportsTable` rendering on the landing.

**`PageInvestigateLandingBehaviorTest.php` update after REM-007:**
- `test_landing_strings_exclude_workflow_shell_copy()` now asserts only `label_pro` as present.
- The removed heading keys (`selector_title`, `selector_intro`, `selector_section_label`) are now asserted under `assertArrayNotHasKey`.

---

## Implementation Sequencing

Items are ordered by impact and dependency:

| Order | Gap | Reason |
|---|---|---|
| 1 | REM-002 | **Completed — implemented 2026-03-02.** |
| 2 | REM-003 | **Completed — implemented 2026-03-02.** |
| 3 | REM-006 | **Completed — implemented 2026-03-02.** |
| 4 | CC-002, CC-003 | **Completed — resolved via landing template removals in REM-004/REM-005 and REM-006.** |
| 5 | REM-004 | **Completed — implemented 2026-03-02.** |
| 6 | REM-005 | **Completed — implemented 2026-03-02.** |
| 7 | REM-007 | **Completed — implemented 2026-03-02.** |
| 8 | REM-001 | Mode selector strip card live data; requires discovery of data sources; medium effort |
| 9 | REM-009 | Mode colour identity; requires base template changes; cross-cutting impact |
| 10 | REM-008 | **Closed — not a real gap.** Add a clarifying comment to `PageOperatorModeLanding.php` only. |
| 11 | REM-010 | Preference form UX; low impact, low effort |

Items 1–7 are complete. Items 8–11 remain pending and can be delivered independently with normal dependency checks.

---

## File Reference Summary

| File | Related Gaps |
|---|---|
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageOperatorModeLanding.php` | REM-001, REM-008, REM-010 |
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageActionsQueueLanding.php` | REM-002, REM-003, CC-001 |
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageConfigureLanding.php` | REM-004, REM-005 |
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageReportsLanding.php` | REM-006 |
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageInvestigateLanding.php` | REM-007 |
| `src/ActionRouter/Actions/Render/PluginAdminPages/BasePluginAdminPage.php` | REM-008, REM-009 |
| `src/ActionRouter/Actions/Render/PluginAdminPages/PageModeLandingBase.php` | REM-008 |
| `src/ActionRouter/Actions/OperatorModeSwitch.php` | REM-010 |
| `src/ActionRouter/Actions/Render/Components/Meters/MeterCard.php` | REM-002 |
| `src/ActionRouter/Actions/Render/Components/Widgets/NeedsAttentionQueue.php` | REM-002, REM-003 |
| `src/ActionRouter/Actions/Render/Components/Widgets/AttentionItemsProvider.php` | REM-003 |
| `src/Modules/Plugin/Lib/MeterAnalysis/Component/Base.php` | REM-002 (`CHANNEL_ACTION` constant) |
| `src/Modules/Plugin/Lib/MeterAnalysis/Meter/MeterSummary.php` | REM-002 (`SLUG` constant) |
| `src/Modules/Plugin/Lib/MeterAnalysis/Handler.php` | REM-002, REM-004 (`METERS` constant) |
| `src/Modules/Plugin/Lib/NavMenuBuilder.php` | REM-009 (`resolveCurrentMode()` for mode accent) |
| `src/Controller/Plugin/PluginNavs.php` | REM-001, REM-005, REM-009, REM-010 |
| `templates/twig/wpadmin/plugin_pages/inner/operator_mode_landing.twig` | REM-001, REM-010 |
| `templates/twig/wpadmin/plugin_pages/inner/actions_queue_landing.twig` | REM-002, REM-003, CC-001 |
| `templates/twig/wpadmin/plugin_pages/inner/configure_landing.twig` | REM-004, REM-005, CC-002 |
| `templates/twig/wpadmin/plugin_pages/inner/reports_landing.twig` | REM-006, CC-003 |
| `templates/twig/wpadmin/plugin_pages/inner/investigate_landing.twig` | REM-007 |
| `templates/twig/wpadmin/plugin_pages/base_inner_page.twig` | REM-008, REM-009 |
| `assets/css/shield/dashboard.scss` | REM-001, REM-009 (`.operator-mode-landing__*`) |
| `assets/css/shield/investigate.scss` | REM-007 (`.investigate-landing__section-label`) |
| `assets/css/shield/needs-attention.scss` | REM-003 (existing all-clear styles to reuse) |
| `tests/Unit/ActionRouter/Render/PageConfigureLandingBehaviorTest.php` | CC-004 |
| `tests/Unit/ActionRouter/Render/PageActionsQueueLandingBehaviorTest.php` | CC-004 (completed as part of REM-002) |
| `tests/Unit/ActionRouter/Render/PageReportsLandingBehaviorTest.php` | CC-004 (updated after REM-006) |
| `tests/Unit/ActionRouter/Render/PageInvestigateLandingBehaviorTest.php` | CC-004 (update `test_landing_strings_exclude_workflow_shell_copy` after REM-007) |

---

*End of document. Last updated 2026-03-02. All "investigation required" and "pointer for further discovery" sections have been replaced with concrete findings from source code review.*




