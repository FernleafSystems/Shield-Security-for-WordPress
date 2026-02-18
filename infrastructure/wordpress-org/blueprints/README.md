# WordPress.org Plugin Preview Blueprint

This directory stores the source-of-truth blueprint for WordPress.org plugin previews.

## Canonical File

- `blueprint.json`

## Why This Lives Here

- WordPress.org preview blueprints are committed to the plugin SVN repository root at:
  `assets/blueprints/blueprint.json`
- That path is **outside** plugin `trunk/` package content.
- Keeping the blueprint in this Git repo gives us normal code review and version history.

## Sync To WordPress.org SVN

Use:

```bash
php bin/sync-wporg-blueprint.php --svn-root=/path/to/wporg-svn/wp-simple-firewall
```

This copies:

- from `infrastructure/wordpress-org/blueprints/blueprint.json`
- to `/path/to/wporg-svn/wp-simple-firewall/assets/blueprints/blueprint.json`

The script only copies the blueprint file. It does **not** create SVN tags and does **not** run `svn commit`.

## Validate Sync Without Copying

```bash
php bin/sync-wporg-blueprint.php --svn-root=/path/to/wporg-svn/wp-simple-firewall --check-only
```

## Local Preview Validation

### A) Validate the exact WordPress.org blueprint flow

```bash
npx @wp-playground/cli@latest run-blueprint --blueprint=./infrastructure/wordpress-org/blueprints/blueprint.json
```

Note: this blueprint installs `wp-simple-firewall.latest-stable.zip` from WordPress.org.

Interactive local preview session (same blueprint):

```bash
npx @wp-playground/cli@latest server --blueprint=./infrastructure/wordpress-org/blueprints/blueprint.json
```

### B) Validate local trunk/working-copy plugin code before publishing

Use the Composer shortcuts (recommended).

Purpose in plain English:

- `playground:local` = start an interactive Playground site in your browser for manual testing.
- `playground:local:check` = run a smoke check and print explicit PASS/FAIL output.
- `playground:local:clean` = remove old local Playground runtime artifacts created by this helper.

For deterministic local runs (no `npx` network fetch), install a local Playground CLI once:

```bash
npm install --save-dev @wp-playground/cli
```

`playground:local` and `playground:local:check` require this local binary and fail fast if it is missing.

Start interactive local Playground:

```bash
composer playground:local
```

This starts a local Playground server and mounts the current Git working copy as:
`/wordpress/wp-content/plugins/wp-simple-firewall`

Run non-interactive smoke check:

```bash
composer playground:local:check
```

Run cleanup:

```bash
composer playground:local:clean
```

Optional overrides:

```bash
composer playground:local -- --php=8.3 --wp=latest --port=9500
composer playground:local:check -- --strict
```

Version note:

- The local helper writes generated blueprints with `preferredVersions` set from your requested `--php` and `--wp` values.
- This keeps `composer playground:local*` behavior deterministic even if upstream CLI flags drift.

Expected smoke check output includes:

- `Version Verification` section (requested PHP vs actual runtime PHP)
- `Preflight` section
- `Checks` section
- `Warnings` section
- `Errors` section
- `Artifacts` section indicating cleanup bytes reclaimed
- Final `Result: PASS|FAIL|ENVIRONMENT_FAILURE`

Required checks for PASS:

- Runtime PHP probe succeeds
- Runtime PHP major.minor matches requested `--php`
- WordPress bootstrap succeeds
- Shield activation succeeds
- Shield plugin is active after activation
- Admin login step succeeds
- WordPress.org blueprint schema is valid

Warnings and failure diagnostics are shown explicitly in console output, including an output tail on failure. `playground:local:check` always removes per-run temp artifacts after execution. Interactive `playground:local` performs a runtime PHP probe before startup and blocks startup on mismatch.

## WordPress.org Rollout

1. Sync blueprint into SVN root `assets/blueprints/blueprint.json`.
2. Commit SVN changes.
3. Set plugin preview mode to `committer`.
4. Validate with committer account (`?preview=1`).
5. Set preview mode to `public`.
6. Rollback path: set preview mode to `none`.

If this is a blueprint-only update, no SVN tag is required.
