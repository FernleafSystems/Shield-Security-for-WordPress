# Changelog

## Unreleased

- Record backup starts, finishes and refused backup requests in the Activity Log, including a correct backup key sent from an IP outside the backup service. Show the last backup on the License page. Add `wp shield backups on|off|status` and a remote backup pause through a general status file that fails open; backups behave as before unless either switch is used.
- Stop a backup's database export from retrying without end when a request fails on the site. Shield no longer turns the failed request's progress into the map WorpDrive resumes from, since that request made no archive, and WorpDrive client 0.5.0 names the table and the failure's class instead.

- Hash binary files byte for byte in crowd-sourced hash submissions, matching the AptoWeb API: a file whose first 8,000 bytes contain a NUL byte keeps its bytes, while text files still have line endings normalised to LF. Submitted collections for plugins and themes that ship `.ico` or `.mo` files can then verify against the API's WordPress.org hashes. Scan comparisons are unchanged and accept both forms.

- Request SHA256 for new plugin/theme snapshots and eligible published promotions in Shield's scan preparation, snapshot maintenance, and update cleanup. Support explicit MD5, SHA1, or SHA256 helper parameters with validated references and matching metadata; preserve helper MD5 defaults, existing baselines, local-versus-published trust, and SHA1 crowd submission contracts.

- Keep required silentCAPTCHA checks pending when signal storage fails, preserving confirmed server state until a successful retry.
- Keep cookie-free silentCAPTCHA retry delays bounded after the browser clock moves backward.
- Stop treating an unchanged silentCAPTCHA signal record saved twice in one second as a storage failure that made visitors repeat their checks.

- Limit manual malware assessment refreshes to once every five minutes per site, with a dialogue showing the remaining wait.
- Use only provider-neutral reason codes to explain unclassified MALAI assessments.
- Allow authorized administrators to refresh active malware assessments from MALAI immediately using the existing table controls and definitive-clean reconciliation rules.
- Add optional cookie-free silentCAPTCHA with browser refresh timing while preserving existing server-side bot checks and default cookie mode. Browsers re-check a changed IP on the next uncached page, at most once per refresh window, and failures the server answered pause checks across pages; purge page caches when changing mode.
- Clarify malware assessments, including inconclusive results, and check unresolved reports again after ten minutes. Only definitive clean verdicts automatically clear malware findings.
- Keep successful network settings imports from being reported as timed out when the master encounters one transient database write failure.
- Allow authorized import/export clients without a stored Import ID to complete callback verification against their stored trusted target, while preserving private-target, credential, cooldown, and callback rejection controls.
- Prevent false cloaked-plugin findings and mass alerts from replayed filters or unusable plugin lists. Preserve saved findings when evidence is incomplete, and require WordPress 6.3 or newer for this check.
- Restore successful-login statistics after completed WordPress and Shield MFA logins, including email login links. Exclude pending MFA challenges and cookie rotation, and prevent duplicate success events and premature authenticated bot signals.
