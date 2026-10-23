# Changelog

## Unreleased

- Receive virtual patches as signed rule files for each installed plugin and theme, fetched from the API hourly and soon after a plugin or theme is updated, activated or switched, never during a visitor's request. Each file is verified with Ed25519 against Shield's trusted keys before it is stored, never replaced by an older one, and kept when the API is unreachable; each rule is checked before use. A rule in log mode records the request it would have blocked as a new "Virtual Patch Observed" activity log event and lets it through; block mode blocks as before. Refused files, rules Shield can't use, plugins and themes with no successful update for 3 days, a service that can't be reached for 3 days, and a Shield too old to receive rules are reported in the activity log. The ruleset file moves to format 2 and is rebuilt straight after an update.
