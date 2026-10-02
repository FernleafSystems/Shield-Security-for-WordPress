# Changelog

## Unreleased

- Limit manual malware assessment refreshes to once every five minutes per site, with a dialogue showing the remaining wait.
- Use only provider-neutral reason codes to explain unclassified MALAI assessments.
- Allow authorized administrators to refresh active malware assessments from MALAI immediately using the existing table controls and definitive-clean reconciliation rules.
- Add optional cookie-free silentCAPTCHA with browser refresh timing while preserving existing server-side bot checks and default cookie mode. IP changes are reassessed at the next due refresh; purge page and asset caches and reload open pages when changing mode.
- Clarify malware assessments, including inconclusive results, and check unresolved reports again after ten minutes. Only definitive clean verdicts automatically clear malware findings.
- Keep successful network settings imports from being reported as timed out when the master encounters one transient database write failure.
- Allow authorized import/export clients without a stored Import ID to complete callback verification against their stored trusted target, while preserving private-target, credential, cooldown, and callback rejection controls.
- Prevent false cloaked-plugin findings and mass alerts from replayed filters or unusable plugin lists. Preserve saved findings when evidence is incomplete, and require WordPress 6.3 or newer for this check.
- Restore successful-login statistics after completed WordPress and Shield MFA logins, including email login links. Exclude pending MFA challenges and cookie rotation, and prevent duplicate success events and premature authenticated bot signals.
