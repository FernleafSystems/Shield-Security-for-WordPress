# Shield Testing Guide

`TESTING.md` is Shield's single source of truth for the supported test command surface and workflow roles. If another document disagrees with this file, follow `TESTING.md`.

Supporting docs:

1. [`tests/docker/README.md`](tests/docker/README.md) for Docker-runner mechanics only.
2. [`docs/test-suite-full-audit-2026-03-15.md`](docs/test-suite-full-audit-2026-03-15.md) for the audit record.
3. [`tests/TESTING-RULES-ROADMAP.md`](tests/TESTING-RULES-ROADMAP.md) for rules/firewall coverage planning only.

## Public Commands

| Goal | Command | Notes |
|---|---|---|
| Full local confidence gate | `composer test` | Builds config, then runs unit and integration lanes |
| Unit tests | `composer test:unit` | Default developer unit entry point |
| Integration tests | `composer test:integration` | Public wrapper around the local Docker-backed integration lane |
| Browser lane | `composer test:browser` | Playwright + axe against an automatically leased isolated Docker WordPress browser lane |
| Package validation | `composer test:package` | Public wrapper around targeted package validation |
| Source static analysis | `composer analyze` | Public wrapper around source static analysis |
| JS static checks | `npm run test:js` | Policy, ESLint, and checkJs TypeScript validation only |

`test:source`, `test:integration-local`, and `test:package-full` now default to reduced Docker output to keep signal dense. Add `--show-docker-output` when you need full compose output for a failing run.

## Internal Lane Ownership

These commands remain the owned internal lanes behind the public surface and CI workflows. Do not add new public wrappers for them.

| Internal Command | Role |
|---|---|
| `php bin/shield analyze:tooling` | Fail-fast syntax lint and tooling/test-platform analysis on PHP 7.4 |
| `php bin/shield analyze:source` | Canonical source static-analysis lane |
| `php bin/shield analyze:package` | Packaged static analysis lane |
| `php bin/shield test:source` | Source-first Docker runtime lane |
| `php bin/shield test:integration-local` | Local Docker-backed WordPress integration lane |
| `php bin/shield test:package-targeted` | Targeted package validation lane |
| `php bin/shield test:package-full` | Scheduled/manual deep packaged runtime lane |
| `php bin/run-unit-tests.php --runner-mode=serial` | Serial unit sentinel path |

`test:source` and `analyze:source` cache setup state by default for faster local reruns. Use `--refresh-setup` when you need a clean setup pass.

`composer test:integration` is now focused on behaviour-level WordPress runtime coverage. Browser-managed ActionRouter page-shell and DOM-contract tests are intentionally excluded from the default PHPUnit integration lane and covered via `composer test:browser`.

## Quiet vs noisy test runs

Default behavior for Docker-backed lanes is intentionally quieter:

- `php bin/shield test:source`
- `php bin/shield test:integration-local`
- `php bin/shield test:package-full`

To get full Docker compose output during a troubleshooting run, append `--show-docker-output`:

```bash
php bin/shield test:source --show-docker-output
php bin/shield test:integration-local --show-docker-output -- tests/Integration/ActionRouter/WpDashboardSummaryIntegrationTest.php
php bin/shield test:package-full --show-docker-output
```

When running through Composer wrappers, place `--show-docker-output` before PHPUnit arguments:

```bash
composer test:integration -- --show-docker-output -- tests/Integration/ActionRouter/WpDashboardSummaryIntegrationTest.php
```

Automated CI workflows can enforce noisy mode by invoking the command form directly:

```bash
php bin/shield test:source --show-docker-output
```

## Local Browser Lane

Use this lane for ActionRouter interaction and accessibility checks that now live in Playwright instead of PHPUnit DOM assertions. Browser tests run against an automatically leased isolated Docker WordPress lane, while `dev:site:*` continues to manage the persistent manual development site.

```bash
npm run playwright:install
composer test:browser
composer test:browser -- -- -g "Select2 lookup flow"
composer test:browser -- -- tests/browser/action-router/drill-down-flows.spec.js -g "configure opens a prefetched diagnosis without a standalone diagnosis request" --list
```

Operational notes:

1. `php bin/shield dev:site:up` starts or reuses the persistent local Docker WordPress dev site at `http://127.0.0.1:8888` for normal manual development.
2. `php bin/shield test:site:up` remains available for the legacy/manual isolated test site at `http://127.0.0.1:8889`, but browser tests do not use that port.
3. `composer test:browser` leases a browser lane automatically, hard-resets that lane before launching Playwright, then runs against the lane URL. The first default lane is `http://127.0.0.1:8890`.
4. Browser fixtures inherit the leased lane environment, so fixture setup and cleanup hit the same lane as Playwright.
5. `php bin/shield dev:site:reset` and `php bin/shield test:site:reset` destroy and reprovision their respective manual sites; `dev:site:down` and `test:site:down` stop them while preserving state.
6. `php bin/shield dev:site:wp plugin list` and `php bin/shield test:site:wp plugin list` run WP-CLI against the appropriate local `wp-cli` container after ensuring the site is ready. The command appends `--allow-root` automatically when it is not already present.
7. Browser lanes fail fast if required source prerequisites are missing. At minimum, keep Composer dependencies, `plugin.json`, built assets, Docker, and Playwright current before running browser tests.
8. The browser lane is intentionally source-only. Do not add packaged-only `vendor_prefixed` content to this runtime; prefixed dependency validation belongs to the package lanes.
9. Local browser work requires Docker plus a supported Node 20 binary for Playwright. `php bin/run-node-tool.php` resolves that on demand without changing the machine default Node.
10. CI runs Chromium only. Headed debugging is still available by forwarding Playwright flags through the browser command, for example: `composer test:browser -- -- --headed`.
11. Composer browser-arg forwarding is two-stage and must be explicit:
    - First `--` stops Composer argument parsing.
    - Second `--` is passed through to `php bin/shield test:browser` so Symfony stops parsing options and forwards the remaining arguments to Playwright.
    - Do not use `composer test:browser -- --grep "..."`; that is parsed at the wrong layer and fails.
    - Use `composer test:browser -- -- -g "..."` for a pure Playwright grep, or `composer test:browser -- -- <path-or-filter> -g "..."` when you also want to narrow to a file.

### Browser lane parallelism

`composer test:browser` automatically leases an isolated browser lane before resetting that lane. No lane configuration is required for normal use.

- Default pool: 2 browser lanes.
- Override pool size only when the machine can run more WordPress lanes.
- Each lane has its own WordPress container, port, database, and Playwright output directory. Browser lanes start at port `8890`, leaving the legacy/manual `test:site` port `8889` alone.
- All lanes share one MySQL container, so parallel browser commands avoid starting multiple database servers.
- Each Playwright invocation runs with one worker by default; command-level parallelism comes from separate `composer test:browser ...` processes. Pass Playwright's `--workers` explicitly only when you are deliberately testing intra-run parallelism.

Pool-size override examples:

```bash
SHIELD_BROWSER_LANE_COUNT=3 composer test:browser
```

```powershell
$env:SHIELD_BROWSER_LANE_COUNT='3'; composer test:browser; Remove-Item Env:\SHIELD_BROWSER_LANE_COUNT
```

The lane setup prints concise setup stages. If setup fails, the failure output includes the lane, URL, database, Compose project, failed command, and a diagnostic command. For lane-specific site diagnostics, pass the lane environment shown in the failure, for example:

```bash
SHIELD_BROWSER_LANE_INDEX=2 php bin/shield test:site:status
```

```powershell
$env:SHIELD_BROWSER_LANE_INDEX='2'; php bin/shield test:site:status; Remove-Item Env:\SHIELD_BROWSER_LANE_INDEX
```

If a browser run fails before Playwright starts:

1. Read the diagnostic block first. It names the failed stage and the exact command that failed.
2. Run the suggested `php bin/shield test:site:status` command with the displayed `SHIELD_BROWSER_LANE_INDEX`.
3. If Docker is unavailable or unhealthy, start Docker and rerun `composer test:browser`.
4. If the failure is a port conflict, stop the process or container using the reported lane port, then rerun. Browser lane ports start at `8890`; the legacy `8889` site is not part of browser test execution.
5. If the failure names missing assets, missing `vendor/autoload.php`, invalid `plugin.json`, or missing Playwright browsers, refresh the named prerequisite and rerun the same browser command:

```bash
composer install
composer build:config
npm install
npm run build
npm run playwright:install
```

### Optional Playground Tooling

Raw Playground is no longer part of the supported test surface. Keep the local helper only for standalone smoke or debugging work:

```bash
npm install --prefix tools/playground --no-audit --no-fund
php bin/run-playground-local.php
php bin/run-playground-local.php --run-blueprint
php bin/run-playground-local.php --clean
```

## CI Workflow Role Split

Required source-first gate: [`.github/workflows/tests.yml`](.github/workflows/tests.yml)

1. Tooling guard on PHP 7.4 via `php bin/shield analyze:tooling`.
2. JS static checks via `npm run test:js`.
3. Source static analysis via `composer analyze`.
4. Unit tests on PHP 7.4 and latest supported PHP via `composer test:unit`.
5. Source Docker runtime checks focused on runtime and integration coverage.
6. Source Docker runtime in CI runs with `--show-docker-output` for full compose logs.
7. Package-targeted validation against the built artifact.

Serial compatibility sentinel: [`.github/workflows/unit-serial-sentinel.yml`](.github/workflows/unit-serial-sentinel.yml)

1. Runs `php bin/run-unit-tests.php --runner-mode=serial`.
2. Triggered by `workflow_dispatch`.
3. Runs weekly at `0 5 * * 1` (05:00 UTC every Monday).

Scheduled/manual packaged pathway: [`.github/workflows/docker-tests.yml`](.github/workflows/docker-tests.yml)

1. Runs the full packaged matrix runtime checks.
2. Runs packaged static analysis.
3. Triggered by `workflow_dispatch` and the weekday schedule `0 6 * * 1-5` (06:00 UTC Monday through Friday).

Scheduled/manual browser lane: [`.github/workflows/browser-tests.yml`](.github/workflows/browser-tests.yml)

1. Installs Composer and Node dependencies.
2. Rebuilds admin assets for the checked-out source tree.
3. Installs Chromium and runs the ActionRouter Playwright + axe lane against an isolated local Docker WordPress browser lane.
4. Triggered by `workflow_dispatch`, the weekday schedule `30 6 * * 1-5` (06:30 UTC Monday through Friday), and PRs that touch ActionRouter/browser-owned UI paths.

## Local Verification Commands

Use these to verify the command surface and documentation alignment:

```bash
php bin/shield --help
composer run-script --list
```

For a single focused unit file or a small set of unit files, run PHPUnit directly by path instead of going through the composer suite wrappers:

```bash
php vendor/phpunit/phpunit/phpunit -c phpunit-unit.xml tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php
php vendor/phpunit/phpunit/phpunit -c phpunit-unit.xml tests/Unit/ActionRouter/Render/ScansResultsViewBuilderSummaryRailTest.php
```

Use direct file paths for targeted unit work when you need deterministic, serial execution of a specific suite and clear per-file failure output.

For GitHub authentication issues during Docker or source runs, use the troubleshooting steps in [`tests/docker/README.md`](tests/docker/README.md).

## Operational Boundaries

1. Keep testing validation focused on runtime, static analysis, package correctness, and browser coverage where it replaces brittle PHP UI assertions.
2. Do not add tests that assert documentation prose.
3. Ignore unrelated non-conflicting workspace changes while implementing testing updates.
4. If conflicting changes are found in the touched testing slice, stop and report before continuing.
