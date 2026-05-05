# Shield Security Accessibility Audit

Date: 2026-04-26

## Linear Migration Note

As of 2026-05-05, Linear is the active tracker for this accessibility backlog. This document is retained as the historical audit baseline and should not be treated as current status without checking the linked Linear issues, current code, and tests.

Linear project: [Accessibility Remediation 2026](https://linear.app/fernleafsystems/project/accessibility-remediation-2026-e2ee11de8ecf)

Completed historical slices verified during migration:

- [SHI-9](https://linear.app/fernleafsystems/issue/SHI-9/investigate-lookup-accessibility-contract) Investigate lookup accessibility contract, commit `7e8daa471`.
- [SHI-10](https://linear.app/fernleafsystems/issue/SHI-10/public-block-recovery-accessibility-mfa-profile-dialog-cleanup) Public block recovery accessibility + MFA profile dialog cleanup, commits `7470d83ca` and `9b969534d`.
- [SHI-11](https://linear.app/fernleafsystems/issue/SHI-11/drill-down-shellfocuslive-region-accessibility) Drill-down shell/focus/live-region accessibility, commits `de5588f93`, `1324b7510`, and `7349a692d`.
- [SHI-12](https://linear.app/fernleafsystems/issue/SHI-12/shared-modaloffcanvas-baseline-hardening) Shared modal/offcanvas baseline hardening, commits `bf41669a5`, `4ce2016b7`, and `80375d2f1`.
- [SHI-13](https://linear.app/fernleafsystems/issue/SHI-13/tableshared-admin-confirm-dialog-improvements) Table/shared admin confirm dialog improvements, commits `4f74cc897`, `36dfc197a`, `1e56c2135`, `d18232cac`, and `a2add4dc3`.

Current active slice:

- [SHI-14](https://linear.app/fernleafsystems/issue/SHI-14/shared-admin-modaloffcanvas-accessibility-contract-tightening) Shared admin modal/offcanvas accessibility contract tightening.

Backlog items migrated to Linear:

- [SHI-20](https://linear.app/fernleafsystems/issue/SHI-20/replace-remaining-native-browser-dialogs) Replace remaining native browser dialogs.
- [SHI-21](https://linear.app/fernleafsystems/issue/SHI-21/complete-investigate-inline-tabs-tab-model) Complete investigate inline tabs tab model.
- [SHI-22](https://linear.app/fernleafsystems/issue/SHI-22/add-datatables-and-live-log-status-announcements) Add DataTables and live-log status announcements.
- [SHI-23](https://linear.app/fernleafsystems/issue/SHI-23/improve-admin-shell-landmarks) Improve admin shell landmarks.
- [SHI-24](https://linear.app/fernleafsystems/issue/SHI-24/normalize-non-navigation-anchors-and-action-controls) Normalize non-navigation anchors and action controls.
- [SHI-25](https://linear.app/fernleafsystems/issue/SHI-25/tighten-expandable-detail-row-semantics) Tighten expandable detail row semantics.
- [SHI-26](https://linear.app/fernleafsystems/issue/SHI-26/standardize-focus-styling-consistency) Standardize focus styling consistency.
- [SHI-27](https://linear.app/fernleafsystems/issue/SHI-27/expand-browser-accessibility-smoke-coverage) Expand browser accessibility smoke coverage.

This document replaced `docs/accessibility-audit-2026-03-06.md` as the 2026-04-26 accessibility audit baseline. After the Linear migration, it is retained as historical context only; it does not claim that accessibility fixes were implemented as part of the original audit task.

## Summary

Shield's admin accessibility maturity is improving but still uneven. Recent work has corrected several obvious labelling, tab, modal title, and search-status defects, especially around Configure, Super Search, Security Admin, and scan modals. The remaining risk is concentrated in shared interaction contracts rather than isolated markup mistakes.

The highest current risk is for visually impaired admin users moving through drill-down operator flows such as Investigate, Actions Queue, and Reports. These screens rely on layered panels, async content replacement, visual breadcrumbs, CSS/data-state transitions, and JS-generated chrome, but they do not yet provide a reliable accessible region/layer contract, focus movement, focus return, or load announcements.

Practical readout:

- Screen-reader readiness: inconsistent; weakest in drill-down and async task flows.
- Keyboard-only readiness: mixed; some modern controls are real buttons, but focus movement and focus visibility remain uneven.
- Remediation leverage: high; most serious findings map to reusable primitives.

## Audit Scope

- Admin shell and custom wp-admin page chrome.
- Operator and drill-down pages, including Investigate, Actions Queue, and Reports.
- Configure landing, search, and option detail flows.
- Investigate lookups, inline tabs, live panels, and IP-analysis surfaces.
- Shared modal, offcanvas, and JS-filled dialog containers.
- MFA/profile management and security-sensitive admin flows.
- Public block, auto-recovery, and unblock modals.
- Tables, DataTables-backed screens, dashboard live logs, and live traffic logs.

## Already Addressed

- Super Search now has a modal title target and input label.
- Security Admin overlay now has dialog labelling, description, and a labelled password field.
- Scan Progress modal now has a non-empty title id.
- Scan item analysis tabs now have matching tab and panel relationships.
- Configure search now has an explicit label plus polite live/busy state on the result container.
- The earlier broad `href="javascript:{}"` pattern is no longer widespread in the legacy config and option templates.
- Table busy state support now exists at the shared DataTables container level.

## No Longer Relevant

- Treating Super Search as placeholder-only is stale.
- Treating the Security Admin overlay as missing accessible label and description is stale.
- Treating the Scan Progress modal as titleless is stale.
- Treating all scan item analysis panels as pointing to the wrong tab ids is stale.
- Treating Configure search as having no live/busy status is stale.
- Treating broad `href="javascript:{}"` usage as a dominant cross-template defect is stale, though targeted non-navigation anchors remain.

## Active Issues

### Critical

1. Drill-down shells need an accessible region and layer contract.

   Impact: visually impaired users can activate a subject, queue group, or report layer without being moved to the newly active content, told which layer is active, or returned to a sensible control when backing out.

   Current evidence: `templates/twig/wpadmin/components/page/drill_down_layer.twig` renders layer classes and `data-drill-layer*` attributes, but no labelled region, hidden-state semantics, heading contract, or focus target. `assets/js/components/mode/DrillDownController.js` changes CSS classes and dispatches events, but does not set `aria-hidden`, move focus, return focus, or announce the layer transition. `assets/js/components/mode/StepTabsController.js` renders visual breadcrumb buttons but not a full screen-reader navigation contract. `assets/js/components/mode/InvestigateLandingController.js` opens panel layers and swaps content without a shared focus or announcement path.

   Remediation: define a drill-down contract where producers supply stable layer ids and titles, Twig renders labelled regions with correct hidden state, and JS owns focus entry, focus return, and transition announcements.

2. Investigate lookups need associated labels, descriptions, and Select2 accessible-name/status treatment.

   Impact: users who rely on field names and descriptions may not understand what lookup value is required, especially when a Select2 replacement is used.

   Current evidence: `templates/twig/wpadmin/components/investigate/lookup_strip.twig` renders a `<label>` without `for`, inputs/selects without generated ids, helper text without `aria-describedby`, and Select2 hooks without a documented accessible-name/status contract.

   Remediation: have PHP producers supply ids, label text, and help ids; have Twig render explicit `for`, `id`, and `aria-describedby`; initialize Select2 without relying on JS to repair missing labels.

3. Async panel changes need reusable focus and live-region handling.

   Impact: Configure, Investigate, Actions Queue, Reports, DataTables, and live-log users can trigger content changes without a reliable announcement of loading, success, failure, or current panel context.

   Current evidence: `assets/js/components/mode/InvestigateLandingController.js` injects loading, success, failure, and live-log markup; `assets/js/components/mode/ActionsQueueLandingController.js` and `assets/js/components/mode/ModePanelStateController.js` manage busy/hidden state; `assets/js/components/tables/ShieldTableBase.js` sets `aria-busy` but does not publish reusable status/error announcements. Existing positive examples, such as Configure search and toaster markup, are not yet generalized.

   Remediation: introduce one shared announcer/focus helper for polite/assertive messages, focus entry, and focus return, and use it from panel loading, table actions, live logs, and drill-down transitions.

4. Shared modal contract still has empty and fallback label risks.

   Impact: dialogs can open without a useful accessible name or description, which makes modal context ambiguous for screen-reader users.

   Current evidence: `templates/twig/components/modals/shield_modal_container.twig` and `templates/twig/wpadmin_pages/insights/scans/modal/scan_item_view.twig` still render empty `aria-labelledby`; `templates/twig/components/html/dialog.twig` relies on JS-filled title/body content without a guaranteed label id; `templates/twig/pages/block/block_page_ip.twig` labels public block modals with the container id rather than a title id.

   Remediation: create one modal/offcanvas contract requiring a non-empty title id, optional description id, labelled close control, deterministic focus entry, and focus return.

5. MFA/profile and security-sensitive browser dialogs still use `alert()`, `confirm()`, and `prompt()` for task-critical flows.

   Impact: browser dialogs interrupt context, vary across assistive technologies, and provide weak recovery paths for destructive or security-sensitive actions.

   Current evidence: `assets/js/components/userprofile/ProviderSMS.js`, `ProviderPasskeys.js`, `ProviderYubikey.js`, `RemoveAllProviders.js`, `assets/js/components/tables/ShieldTableBase.js`, `assets/js/components/mode/InvestigateLandingController.js`, `assets/js/components/general/SecurityAdmin.js`, and related table/security modules still call browser dialogs.

   Remediation: replace browser dialogs with shared accessible confirm, input, and message UI built on the modal contract.

### Warning

1. Investigate inline tabs are visual buttons without a complete tab model.

   Evidence: `assets/js/components/mode/InvestigateInlineTabs.js` rebuilds source tabs as buttons and syncs active styling, but does not render `role="tablist"`, `role="tab"`, `aria-controls`, `aria-selected`, roving tabindex, or arrow-key behavior on the generated inline tabs.

2. Admin shell landmarking and page structure remain weak.

   Evidence: `templates/twig/wpadmin_pages/base.twig` uses generic div containers for the plugin page, sidebar, and main content. Public templates are stronger, but the custom admin shell still lacks clear `main`, `nav`, and page-heading structure.

3. Non-navigation anchors remain in targeted legacy and operational surfaces.

   Evidence: `templates/twig/admin/user/profile/mfa/mfa_sms.twig`, `templates/twig/admin/user/profile/mfa/mfa_u2f.twig`, `templates/twig/wpadmin/components/ip_analyse/ip_general.twig`, `templates/twig/notices/override-forceoff.twig`, `templates/twig/wpadmin/components/investigate/live_traffic_body.twig`, and some legacy action-chip templates still use anchors for actions.

4. Expandable detail rows use wrapper `role="button"` with possible nested actions.

   Evidence: `templates/twig/wpadmin/components/page/shield_detail_row.twig` can make the whole row focusable and clickable while also rendering nested action links.

5. Focus styling has improved but remains uneven in legacy styles.

   Evidence: prior global focus suppression has been reduced, but legacy option, table, investigate, and user-profile styles still need a deliberate cross-surface focus indicator pass.

6. DataTables and live logs expose busy state partly but lack reusable status/error announcements.

   Evidence: `assets/js/components/tables/ShieldTableBase.js` applies `aria-busy`; `templates/twig/wpadmin/components/dashboard/live_monitor.twig` and `assets/js/components/mode/InvestigateLandingController.js` update live output visually without a shared status region.

7. Public block and recovery flows still have modal labelling and messaging gaps.

   Evidence: `templates/twig/pages/block/block_page_ip.twig` uses self-referential modal labelling, and `templates/twig/pages/block/autorecover.twig` uses hidden and disabled recovery controls without a clear shared messaging contract.

### Low priority

1. Badge, counter, and icon-led status text equivalents need broad polish.

   Evidence: mixed patterns remain across `src/Controller/Admin/AdminBarMenu.php`, scan controllers, widgets, and detail rows. Some icons are labelled well, while some counters remain visually led or hidden from assistive technologies.

2. Browser accessibility coverage is narrow.

   Evidence: current browser coverage includes focused accessibility checks for selected modern flows, but there is no broad smoke coverage for drill-down focus movement, shared modal labels, table status announcements, or public block recovery.

3. Wizard/profile legacy polish should follow task-critical admin flows.

   Evidence: legacy MFA/profile and wizard templates still have mixed labels, older table-row layouts, and browser-dialog dependencies, but these should be sequenced after drill-down, modal, lookup, and async contracts.

## Reusable Fix Clusters

- Drill-down contract: producers supply layer ids, titles, summaries, and focus targets; Twig renders labelled regions and hidden state; JS owns active-layer focus and announcements.
- Shared announcer/focus helper: one JS path for polite/assertive announcements, focus entry, focus return, and async success/failure messages.
- Modal/offcanvas contract: one title, description, close, focus-entry, and focus-return contract; no empty `aria-labelledby` values.
- Action control normalization: use buttons for actions and anchors only for real navigation.
- Lookup/form labelling: PHP producers provide ids and help ids; Twig renders associated labels and descriptions; JS initializes widgets without repairing missing labels.
- Accessible dialogs: replace `alert()`, `confirm()`, and `prompt()` with shared confirm, input, and message UI.
- Tab contract: one pattern for tablist, tab, tabpanel, ids, selected state, roving tabindex, and arrow-key behavior.
- Status and table announcements: one status primitive for DataTables, live logs, queued actions, and async panel refreshes.

## Recommended Remediation Order

1. Implement the shared announcer/focus helper and wire it into drill-down transitions first.
2. Formalize the drill-down layer contract in producers, Twig, and JS.
3. Replace modal/offcanvas fallback labelling with a strict shared dialog contract.
4. Replace browser dialogs in MFA/profile, table, and security-sensitive flows.
5. Normalize action controls so non-navigation actions are buttons.
6. Repair lookup and form labelling at the producer/Twig boundary.
7. Standardize tabs and DataTables/live-log status announcements.
8. Add browser accessibility coverage for the shared primitives after the contracts exist.
9. Do a final legacy polish pass for badges, counters, focus styles, wizard screens, and profile table rows.

## Evidence Notes

- Drill-down evidence: `templates/twig/wpadmin/components/page/drill_down_layer.twig`, `assets/js/components/mode/DrillDownController.js`, `assets/js/components/mode/StepTabsController.js`, `assets/js/components/mode/InvestigateLandingController.js`.
- Positive stale-finding evidence: `templates/twig/components/html/modal_super_search.twig`, `templates/twig/wpadmin/plugin_pages/inner/security_admin.twig`, `templates/twig/wpadmin_pages/insights/scans/modal/progress.twig`, `templates/twig/wpadmin_pages/insights/scans/modal/scan_item_analysis/tabpanel.twig`, `templates/twig/wpadmin/plugin_pages/inner/configure_landing.twig`.
- Modal risk evidence: `templates/twig/components/modals/shield_modal_container.twig`, `templates/twig/wpadmin_pages/insights/scans/modal/scan_item_view.twig`, `templates/twig/components/html/dialog.twig`, `templates/twig/pages/block/block_page_ip.twig`.
- Lookup and tabs evidence: `templates/twig/wpadmin/components/investigate/lookup_strip.twig`, `assets/js/components/mode/InvestigateInlineTabs.js`, `templates/twig/wpadmin/components/ip_analyse/container.twig`.
- Async and live status evidence: `assets/js/components/tables/ShieldTableBase.js`, `templates/twig/wpadmin/components/dashboard/live_monitor.twig`, `assets/js/components/mode/InvestigateLandingController.js`, `assets/js/components/mode/ActionsQueueLandingController.js`.
- Browser-dialog evidence: `assets/js/components/userprofile/*`, `assets/js/components/tables/*`, `assets/js/components/general/SecurityAdmin.js`, `assets/js/components/mode/InvestigateLandingController.js`.
- Non-navigation anchor evidence: `templates/twig/admin/user/profile/mfa/mfa_sms.twig`, `templates/twig/admin/user/profile/mfa/mfa_u2f.twig`, `templates/twig/wpadmin/components/ip_analyse/ip_general.twig`, `templates/twig/notices/override-forceoff.twig`, `templates/twig/wpadmin/components/investigate/live_traffic_body.twig`.
