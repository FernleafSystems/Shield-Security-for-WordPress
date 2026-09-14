# Changelog

## Unreleased

- Prevent false cloaked-plugin findings and mass alerts from replayed filters or unusable plugin lists. Preserve saved findings when evidence is incomplete, and require WordPress 6.3 or newer for this check.
- Restore successful-login statistics after completed WordPress and Shield MFA logins, including email login links. Exclude pending MFA challenges and cookie rotation, and prevent duplicate success events and premature authenticated bot signals.
