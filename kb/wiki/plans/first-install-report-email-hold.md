---
title: First-Install Report Email Hold
type: topic
created: 2026-11-11
updated: 2026-11-11
sources: []
tags: [reporting, email, alerts, onboarding, actions-queue, agencies]
category: plans
---

# First-Install Report Email Hold

For the first 24 hours after Shield is installed, hold back scan-driven alert and report emails until someone chooses a report email address, making that choice the first setup guide step and the top Review next item in the Actions Queue.

## Problem

When an agency installs Shield on a client site, `block_send_email_address` is empty, so `OptsLookup::getReportEmail()` falls back to the WordPress site admin email. That is often the site owner, not the agency. The first scans run straight away, and their findings (vulnerable plugins, hidden plugins, the daily scan alert report) can reach the site owner before the agency has had a chance to set the address.

## Goals

- Scan findings from the first 24 hours are not emailed until a report address has been chosen, or until 24 hours have passed.
- Nothing is lost. Held emails go out as soon as the hold ends.
- Choosing the address is the first step of the setup guide and wizard, and one click from the Actions Queue, without digging through settings.
- Default is to send. Only a short, explicit list of emails is held.
- No change for existing installs or for sites that already have a report address.

## Non-Goals

- No blocking modal, pop-up or forced screen. Scans and protection run normally from the first minute.
- No new consolidated "catch-up" email. Each held email type already stores its own pending data (see below).
- No hold for transactional or real-time emails.
- No changes to MainWP or settings import in this slice.

## Hold Rule

The hold is active when all of these are true:

1. `installation_time` (`OptsLookup::getInstalledAt()`) is non-zero and less than 24 hours ago. `ModCon` stores it on load via `storeRealInstallDate()`, so a fresh install has a value. Upgrades and old installs are past 24 hours, so they are never held. A reinstall can inherit an earlier date from the `install_date` WP option and so gets no hold; that is acceptable.
2. `block_send_email_address` is empty or invalid.
3. The `shield/report_email` filter does not supply a valid address.

`Modules\Plugin\Lib\Reporting\ReportEmailHold::isActive()` owns this rule, and `explanation()` owns the user-facing text that describes it. `OptsLookup::getChosenReportEmail()` owns "a report email was chosen" (the setting, or the `shield/report_email` filter on any site, validated; otherwise empty). `getReportEmail()` falls back from it to the site admin email, and the Reporting zone status and the report email setup step (which counts as done once an address is chosen) read it too. Nothing else stores hold state. Choosing an address (including choosing the site admin address on purpose) writes `block_send_email_address`, which ends the hold.

## Which Emails Are Held

Rule: findings produced by Shield's own early scans wait; everything else sends as normal.

Held while the hold is active:

| Email | Send path |
|---|---|
| Scan alert report (daily digest) | `AutoReportCoordinator::attemptAlert()` |
| Info report | `AutoReportCoordinator::attempt( REPORT_TYPE_INFO )` |
| Hidden/cloaked plugins instant alert (on by default, immediate) | `InstantAlertsCon` via `AlertHandlerCloakedPlugins` |
| Vulnerabilities instant alert | `AlertHandlerVulnerabilities::collectScanResultAlerts()` (collection deferred, see below) |
| File Locker instant alert (off by default) | `InstantAlertsCon` via `AlertHandlerFileLocker` |

Sent immediately, unchanged:

- Shield deactivated instant alert (the plugin is going away, so it cannot wait)
- Admin login, admin changes and firewall block instant alerts (off by default; they report live events)
- Email 2FA codes, backup codes, and the 2FA email-delivery verification
- Auto-unblock magic link
- User login notice (goes to the user, not the report address)
- Security admin removal confirmations
- License-check warning and license deactivation emails (`LicenseEmails`)
- WordPress core auto-update emails (WordPress sends them; Shield only redirects the address)

The hold is applied at the alert and report layer, never in `EmailCon::sendVO()`, because several transactional emails also go to `getReportEmail()`.

## How Holding Works

No new storage is needed. Each held type already keeps its pending data and removes duplicates:

- **Scan alert report.** The digest reports scan findings that are still active and not yet notified (`notified_at = 0`), then marks them notified (`ReportGenerator::persistAlertNotifications()`). While held, `AutoReportCoordinator::run()` returns early. The first run after the hold reports every finding that is still active and not yet notified, in one email. Findings fixed, ignored or auto-filtered during the hold are not reported, which is the digest's normal behaviour.
- **Info report.** `CreateReportVO` builds the most recent completed window at the time it runs and refuses to build the same window twice. A window that completes during the hold always contains the install moment, so the only report a hold can skip is the one covering the install window, which holds little or no Shield data. That is acceptable; no catch-up for missed windows.
- **Hidden plugins and File Locker instant alerts.** Their payloads are self-contained (`CloakedPluginFinding::toAlertData()`, File Locker paths) and stored in the `instant_alerts_data` option, merged without duplicates. Each alert handler says whether its pending data must wait through `AlertHandlerBase::isSendHeld()`: these two return the report email hold, and the vulnerabilities handler returns "scans not ready" (replacing the old vulnerability-only special case). `InstantAlertsCon::sendAlerts()` retains held data, and the immediate path (`updateAlertDataFor()` -> `sendAlertFor()`, used by hidden plugins) stores without sending while held.
- **Vulnerabilities instant alert.** Its payload is only plugin/theme identifiers, and the email (`EmailInstantAlertVulnerabilities`) reads each asset's current name and version when it renders. Holding that payload for 24 hours could name an already-upgraded version, or fail to render for a deleted asset after the queue has been cleared. So the hold does not queue it: `collectScanResultAlerts()` returns early while the hold is active, just as it does when scans are not ready. Findings stay active and not yet notified in the scan results, and are collected from the then-current scan state after the hold.

Release needs no extra trigger. Once the hold rule is false:

- Retained instant alerts: `InstantAlertsCon::run()` schedules a WP-Cron send (`instant_alerts_send`, 30 seconds out) on any request while alert data is pending, so they go out on the next cron run after the hold.
- Reports: the next `ReportingController::runHourlyCron()`.
- Vulnerability findings: whichever comes first, the next scan completion (instant alert) or the next alert digest (which includes vulnerability results). Either way they are reported once.

Result: at most one email per held type for findings that are still current.

## Actions Queue Item

- A new item, "Choose where Shield sends reports", shown only while the hold rule is true. It disappears when an address is saved or when 24 hours pass.
- Short description: Shield is holding scan alerts until you choose where they should go.
- It is the first item in the Actions Queue's "Review next" lane. The landing page lists lanes by bucket (critical first, then review) and orders the kinds inside each lane by `ActionsQueueLandingSectionsBuilder::KIND_ORDER`, with unlisted kinds after the listed ones. Group `sort_order` in `ActionsQueueGroupDefinitions` does not decide landing order, so no new group definition is needed.
- It cannot be ignored. Ignoring would leave the hold running with no way out except waiting.
- Its CTA is an ordinary navigation link (the kind maintenance CTAs already use) that opens the setup guide on the report email step. No new dialog and no new save action.

Narrow changes needed for this, all in existing code:

1. Add the item (key `report_email`) as a `maintenance` zone entry with `drill_bucket` `review` in `PluginNavs::actionsLandingAssessmentDefinitions()`, with a `MeterAnalysis` component whose applicability is the hold rule. As a singleton it becomes its own single-item seed through the existing `ActionsQueueMaintenanceGroupSeedBuilder::buildSingleItemSeed()`.
2. Put `report_email` first in `ActionsQueueLandingSectionsBuilder::KIND_ORDER`, so it leads the Review next lane. (That lane's boxes start folded to a heading and count, like every other review item; that is acceptable.)
3. Register its CTA in `MaintenanceQueueItemDisplayNormalizer` (unknown keys get no CTA).
4. Exclude it from the ignore toggle in `MaintenanceQueueItemDisplayNormalizer::buildToggleAction()` (active singleton items get one by default).

## Setup Guide And Wizard

The report email choice lives in the existing setup flow, not in a new pop-up. Both surfaces reuse `Merlin\Steps\ReportEmail`, so saving works exactly as it does today.

- **Step order.** Report email becomes the first real step, followed by Security Admin:
  - `SetupGuideDataBuilder::STEP_HANDLERS`: `ReportEmail`, `SecurityAdmin`, then `License`, `ApplySecurityProfile`, `Integrations`, `SecurityBadge`.
  - `Wizards::WIZARD_STEPS_WELCOME`: `GuidedSetupWelcome` stays first as the greeting slide, then `ReportEmail`, `SecurityAdmin`, then the rest in their current order.
- **Opening on the step.** The guide opens on the next step not done, and `?setup_guide=open` opens it straight away. A skipped step counts as done, so the queue link also carries `setup_step=report_email` (`PluginURLs::setupGuide( $stepSlug )`); `SetupGuideDataBuilder` opens that step when the guide is asked for and the step is listed, and `SetupGuideBar.js` tidies both parameters off the address.
- **Keeping the admin address.** The field shows the site admin email only as a placeholder, so the step has a second small form with a "Use the site admin email (`admin@...`)" button. It posts `use_site_admin_email=Y`, and `processStepFormSubmit()` resolves the site admin email on the server and saves it. Choosing it on purpose writes `block_send_email_address` and ends the hold.
- **Skipping.** "Skip this step" in the guide leaves the address empty, so the hold continues and the queue item stays until the address is set or 24 hours pass. That is intended.
- **Copy.** The step's text should say that Shield is holding scan alerts until an address is chosen, while the hold is active. The guide hint "Weekly summary and alerts" can stay.

## Acceptance Checks

- New install, no address: hidden-plugin, File Locker and vulnerability instant alerts and scan alert/info reports are not emailed during the first 24 hours; hidden-plugin and File Locker data stays in `instant_alerts_data`, and vulnerability findings stay not yet notified in the scan results.
- Address saved (either button) inside 24 hours: retained instant alerts send on the next WP-Cron run, held reports on the next hourly cron, and vulnerability findings at the next scan completion or alert digest, all to the chosen address. Each held type sends at most once.
- No address after 24 hours: held emails send to the site admin fallback, as today.
- Not-held emails (list above) send immediately during the hold.
- Existing installs (installation time over 24 hours) and installs with an address set: no behaviour change.
- Actions Queue shows the item first in the Review next lane during the hold, without an ignore control, and not at all otherwise. Its link opens the setup guide on the report email step.
- Setup guide and wizard show report email first, then Security Admin. "Use the site admin address" saves the admin email and ends the hold.

## Testing

- `ReportEmailHoldIntegrationTest`: the hold window and chosen-email rule, both step saves, the queue item's state, CTA and missing ignore toggle, and the guide's step order and `setup_step`.
- `CloakedPluginsConIntegrationTest`: a held hidden-plugin alert is released once to the chosen address, and on expiry once to the site admin email.
- `VulnerabilityInstantAlertIntegrationTest`: collection waits during the hold and runs once an address is chosen.
- Unit: `AutoReportCoordinator` builds nothing while held; the ignore action rejects `report_email`.
- Browser (`report-email-hold.spec.js`): the item leads Review next with no ignore control, its link opens the report email step even after a skip, and both saves end the hold.

## Related Pages

- [[index]]
- [[overview]]
