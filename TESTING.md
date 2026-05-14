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
| Cross-site sync lane | `composer test:cross-site` | Two Docker WordPress sites exercising Shield import/export master/slave sync |
| Package validation | `composer test:package` | Public wrapper around targeted package validation |
| Source static analysis | `composer analyze` | Public wrapper around source static analysis |
| JS static checks | `npm run test:js` | Policy, ESLint, and checkJs TypeScript validation only |

`test:source`, `test:integration-local`, and `test:package-full` now default to reduced Docker output to keep signal dense. Add `--show-docker-output` when you need full compose output for a failing run.

## Pre-commit checks

`php bin/shield git:pre-commit --stdin --null` accepts NUL-delimited changed file paths from Git, filters changed PHP files, and feeds them into the existing syntax lint, PHPStan, and unit test tooling. A local pre-commit hook can stay thin by piping `git diff --cached --name-only --diff-filter=ACMR -z` into that command.

## Required PR CI local parity

The required PR CI gate is [`.github/workflows/tests.yml`](.github/workflows/tests.yml). It is broader than `composer test` because CI also proves static analysis, JS checks, package build/validation, and a source Docker runtime lane. Use these local equivalents when you need to reproduce the required CI gate:

| CI lane | Local equivalent | Notes |
|---|---|---|
| Source static analysis | `composer analyze` | CI runs this on PHP 7.4; use a PHP 7.4 shell when reproducing parse-compatibility exactly. |
| JS static checks | `npm run test:js` | Static policy, ESLint, and checkJs only. |
| Unit PHP 7.4 | `composer test:unit` | Run under PHP 7.4 for exact CI parity. |
| Unit latest PHP | `composer test:unit` | Run under the latest supported CI PHP version. |
| Source Docker runtime | `php bin/shield test:source --skip-unit-tests --show-docker-output` | Mirrors required CI by focusing Docker on runtime/integration checks after the unit lanes have already run. |
| Package-targeted validation | `composer package-plugin -- --output=tmp/shield-package-ci` then `php bin/shield test:package-targeted --package-path=tmp/shield-package-ci` | Mirrors CI's built-artifact validation path. |

The workflow starts for the configured branch events, then uses job-level changed-file filters from [`.github/ci-path-filters.yml`](.github/ci-path-filters.yml) to skip expensive lanes when their inputs were not touched. This deliberately avoids workflow-level `paths` filters for the required gate, because skipped workflows can leave required checks pending while skipped jobs report a successful skipped state. Manual `workflow_dispatch` runs execute the full required gate.

`composer test` remains the everyday local confidence gate: it builds config, runs unit tests, and runs the local Docker-backed integration lane. It is intentionally faster and narrower than required PR CI, while scheduled/manual browser and cross-site workflows remain deeper coverage rather than default local requirements. Use `php bin/shield test:package-full` when you need the manual full packaged runtime lane.

## Internal Lane Ownership

These commands remain the owned internal lanes behind the public surface and CI workflows. Do not add new public wrappers for them.

| Internal Command | Role |
|---|---|
| `php bin/shield analyze:source` | Canonical source static-analysis lane; source parse-compatibility gate when run on PHP 7.4 |
| `php bin/shield analyze:package` | Packaged static analysis lane |
| `php bin/shield test:source` | Source-first Docker runtime lane |
| `php bin/shield test:integration-local` | Local Docker-backed WordPress integration lane |
| `php bin/shield test:cross-site` | Two-site Docker WordPress import/export sync lane |
| `php bin/shield test:package-targeted` | Targeted package validation lane |
| `php bin/shield test:package-full` | Manual local deep packaged runtime lane |
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
php bin/shield test:source --skip-unit-tests --show-docker-output
```

## Local Browser Lane

Use this lane for ActionRouter interaction and accessibility checks that now live in Playwright instead of PHPUnit DOM assertions. Browser tests run against an automatically leased isolated Docker WordPress lane, while `dev:site:*` continues to manage the persistent manual development site.

Most developers and agents should start with `composer test:browser`. It reuses warm lanes for practical local speed, but still uses full content-hash runtime freshness. Use `--runtime-refresh=auto` only for repeated local iteration where startup speed matters and a full/default run has already established current runtime freshness recently.

```bash
npm run playwright:install
composer test:browser
composer test:browser -- -- --list
composer test:browser -- --warm --runtime-refresh=auto -- -g "Select2 lookup flow"
composer test:browser -- --warm --runtime-refresh=full -- tests/browser/action-router/security-headers-readiness.spec.js --workers=1
composer test:browser -- --warm -- -g "Select2 lookup flow"
composer test:browser -- --warm -- tests/browser/action-router/drill-down-flows.spec.js --workers=1
composer test:browser -- --clean -- tests/browser/action-router/drill-down-flows.spec.js -g "configure opens a prefetched diagnosis without a standalone diagnosis request"
```

Operational notes:

1. `php bin/shield dev:site:up` starts or reuses the persistent local Docker WordPress dev site at `http://127.0.0.1:8888` for normal manual development.
2. `php bin/shield test:site:up` remains available for the legacy/manual isolated test site at `http://127.0.0.1:8889`, but browser tests do not use that port.
3. Local `composer test:browser` defaults to warm mode, full runtime-refresh hashing, two lanes, two Playwright workers, and Playwright `fullyParallel`. The first default lane is `http://127.0.0.1:8890`.
4. The browser CI workflow runs clean mode with two browser lanes and two Playwright workers in one job, so Composer, npm, asset build, and browser install setup happen once for the run.
5. `php bin/shield dev:site:reset` and `php bin/shield test:site:reset` destroy and reprovision their respective manual sites; `dev:site:down` and `test:site:down` stop them while preserving state.
6. `php bin/shield dev:site:wp plugin list` and `php bin/shield test:site:wp plugin list` run WP-CLI against the appropriate local `wp-cli` container after ensuring the site is ready. The command appends `--allow-root` automatically when it is not already present.
7. Browser lanes fail fast if required source prerequisites are missing. At minimum, keep Composer dependencies, `plugin.json`, built assets, Docker, and Playwright current before running browser tests.
8. The browser lane is intentionally source-only. Do not add packaged-only `vendor_prefixed` content to this runtime; prefixed dependency validation belongs to the package lanes.
9. Local browser work requires Docker plus a supported Node 20 binary for Playwright. `php bin/run-node-tool.php` resolves that on demand without changing the machine default Node.
10. CI installs Chromium headless shell only via `npm run playwright:install -- --with-deps --only-shell`. Headed debugging is still available locally by forwarding Playwright flags through the browser command, for example: `composer test:browser -- -- --headed`.
11. CI does not cache Playwright browser binaries; Playwright's own CI guidance says Linux browser cache restore time is comparable to installing them, while OS dependencies still need installation.
12. Composer browser-arg forwarding is two-stage and must be explicit:
    - First `--` stops Composer argument parsing.
    - Second `--` is passed through to `php bin/shield test:browser` so Symfony stops parsing options and forwards the remaining arguments to Playwright.
    - Do not use `composer test:browser -- --grep "..."`; that is parsed at the wrong layer and fails.
    - Use `composer test:browser -- -- -g "..."` for a pure Playwright grep, or `composer test:browser -- -- <path-or-filter> -g "..."` when you also want to narrow to a file.
13. `php bin/shield test:site:fixture` is a manual diagnostic path only. Playwright specs must use the REST-backed fixture API exposed by `tests/browser/action-router/support/shield-test.js`.

### Browser lane parallelism

`composer test:browser` automatically leases isolated browser lanes, prepares those lanes before Playwright starts, and lets Playwright schedule tests. No lane configuration is required for normal local use.

- Default pool: 2 browser lanes.
- Default local run: `mode=warm`, `runtime-refresh=full`, `lanes=2`, `workers=2`, `fullyParallel=true`.
- Default CLI CI run: `mode=clean`, `lanes=1`, `workers=1`; the GitHub browser workflow overrides this to `lanes=2`, `workers=2`.
- Each lane has its own WordPress container, port, database, and Playwright output directory. Browser lanes start at port `8890`, leaving the legacy/manual `test:site` port `8889` alone.
- All lanes share one MySQL container, so parallel browser commands avoid starting multiple database servers.
- Browser worker isolation is keyed by Playwright `parallelIndex`. PHP passes `SHIELD_BROWSER_LANE_MAP` as a JSON object keyed by `parallelIndex`, and every worker uses its mapped lane URL, fixture token, auth state file, and output directory.
- Warm mode starts or reuses lane containers, refreshes the copied runtime, installs the runtime-only fixture endpoint, and skips baseline provisioning only when the readiness marker still matches the lane and the site is healthy.
- Clean mode preserves reset semantics: reset lane containers and volumes, recreate the lane database, refresh runtime, install the fixture endpoint, provision baseline state, and write the readiness marker. Clean mode also forces full runtime-refresh hashing.
- Runtime refresh defaults to `--runtime-refresh=full`, which builds one content-hash host manifest per browser command and then applies each lane's normal differential copy/delete. Use `--runtime-refresh=auto` for opt-in warm local speedups based on a per-file metadata cache under `tmp/`; the cache enumerates files and does not use directory mtimes. Auto mode validates both the cache wrapper and cached manifest payload, and silently rebuilds on stale or corrupt cache.
- Playwright `--list` is discovery-only and is the recommended Docker-free way to inspect available browser tests. Browser lane acquisition and Docker preparation are skipped for list-only runs because no test opens WordPress or calls the fixture API.
- Requested workers greater than the available lane count is a hard error.

Browser harness options are parsed before Playwright arguments:

```bash
composer test:browser -- --warm -- -g "flow"
composer test:browser -- --clean --lanes=3 -- --workers=3
composer test:browser -- --show-setup-output -- --headed
composer test:browser -- --warm --runtime-refresh=auto -- tests/browser/action-router/security-headers-readiness.spec.js --workers=1
```

Precedence rules:

1. Explicit CLI options beat environment variables.
2. Environment variables beat defaults.
3. Playwright `--workers=N` or `-j N` beats `SHIELD_BROWSER_WORKERS`.
4. `--lanes=N` beats `SHIELD_BROWSER_LANE_COUNT`.
5. `--runtime-refresh=full|auto` is CLI-only; there is no environment override. `--clean` always uses full runtime-refresh hashing.

Pool-size override examples:

```bash
SHIELD_BROWSER_LANE_COUNT=3 composer test:browser
SHIELD_BROWSER_WORKERS=1 composer test:browser
```

```powershell
$env:SHIELD_BROWSER_LANE_COUNT='3'; composer test:browser; Remove-Item Env:\SHIELD_BROWSER_LANE_COUNT
$env:SHIELD_BROWSER_WORKERS='1'; composer test:browser; Remove-Item Env:\SHIELD_BROWSER_WORKERS
```

For a local CI-equivalent clean two-lane check, force CI defaults only for that shell command and then apply the same lane/worker overrides used by the browser workflow:

```bash
CI=true composer test:browser -- --clean --lanes=2 -- --workers=2
```

```powershell
$env:CI='true'; composer test:browser -- --clean --lanes=2 -- --workers=2; Remove-Item Env:\CI
```

The lane setup prints concise setup stages. If setup fails, the failure output includes the lane, URL, database, Compose project, error message, and a diagnostic command. For lane-specific site diagnostics, pass the lane environment shown in the failure, for example:

```bash
SHIELD_BROWSER_LANE_INDEX=2 php bin/shield test:site:status
```

```powershell
$env:SHIELD_BROWSER_LANE_INDEX='2'; php bin/shield test:site:status; Remove-Item Env:\SHIELD_BROWSER_LANE_INDEX
```

If a browser run fails before Playwright starts:

1. Read the diagnostic block first. It names the failed stage, lane metadata, and error message.
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

## Local Cross-Site Lane

Use this lane for Shield-to-Shield import/export communication. It provisions a master WordPress site and a slave WordPress site on one Docker network, uses Docker service-name URLs for site-to-site HTTP, and drives setup, cron, queue processing, and assertions with WP-CLI.

```bash
composer test:cross-site
composer test:cross-site -- --warm
composer test:cross-site -- --clean --show-setup-output
```

Operational notes:

1. The lane uses internal URLs `http://wordpress-master` and `http://wordpress-slave`; exposed host ports are only for diagnostics.
2. Local runs default to warm mode. CI defaults to clean mode.
3. Successful runs stay quiet except for the final lane result; use `--show-setup-output` when Docker, provisioning, or runtime-refresh setup logs are needed.
4. The lane has a single lock under `tmp/cross-site-test-lane` because both sites share one Compose project and one database container.
5. The runtime helper grants every capability required by transferable Shield options, plus WP-CLI, before generating the option corpus.
6. The comparison excludes only explicit non-corpus keys: slave-local sync state such as `importexport_masterurl`, and runtime prerequisites such as `global_enable_plugin_features` and `importexport_enable`. Every generated corpus key must change from its baseline after Shield option normalization.
7. `SHIELD_CROSS_SITE_MASTER_PORT` and `SHIELD_CROSS_SITE_SLAVE_PORT` override the diagnostic host ports if `8892` or `8893` are unavailable.
8. This lane covers Shield import/export sync only. MainWP scenarios should be added as explicit consumers of the same harness when they exist.

### Browser spec authoring contract

Use these rules for every Playwright spec under `tests/browser/action-router`:

1. Import only from the Shield fixture module:

   ```js
   const { test, expect } = require( './support/shield-test' );
   ```

2. Do not import `@playwright/test` directly from specs. `shield-test.js` owns lane selection, per-worker login, `baseURL`, storage state, and fixture API setup.
3. Keep specs independent and safe for `fullyParallel`. Do not depend on file order, test order, or state left by another test.
4. Use `fixtureApi.withActionsQueueFixture( scenario, async ( fixture ) => { ... } )` for ActionRouter actions-queue state.
5. Use `fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => { ... } )` for IP activity meta state.
6. Do not call `php bin/shield test:site:fixture`, WP-CLI, shell commands, or child processes from Playwright specs. Fixture seed/cleanup goes through the REST fixture API.
7. If a new browser fixture is needed, add it to `tests/Helpers/BrowserFixtureRegistry.php`, allow it in `tests/browser/support/shield-browser-fixtures.php`, and keep the required PHP files inside `LocalSiteRuntimeRefresher` managed roots. Do not add the whole `tests/` tree to the browser runtime.
8. The REST fixture endpoint returns success as `{ ok: true, fixture, action, data }` and errors as `{ ok: false, error: { code, message } }`. JS specs should call the fixture API wrappers instead of constructing these payloads manually.
9. Let the fixture wrappers seed and clean state with `try/finally`. Avoid manual cleanup in specs unless a new wrapper cannot express the scenario.
10. Use Playwright's own narrowing flags after the second `--`, for example `composer test:browser -- --warm -- tests/browser/action-router/example.spec.js -g "flow" --workers=1`.

### Optional Playground Tooling

Raw Playground is no longer part of the supported test surface. Keep the local helper only for standalone smoke or debugging work:

```bash
npm ci --prefix tools/playground --no-audit --no-fund
php bin/run-playground-local.php
php bin/run-playground-local.php --run-blueprint
php bin/run-playground-local.php --clean
```

## CI Workflow Role Split

Required source-first gate: [`.github/workflows/tests.yml`](.github/workflows/tests.yml)

The required workflow is job-level path gated by [`.github/ci-path-filters.yml`](.github/ci-path-filters.yml). A docs-only change should normally run only the lightweight changed-file detector, while manual dispatch runs the full gate.

1. Source static analysis on PHP 7.4 via `composer analyze`.
2. JS static checks via `npm run test:js`.
3. Unit tests on PHP 7.4 and latest supported PHP via `composer test:unit`.
4. Source Docker runtime checks focused on runtime and integration coverage via `php bin/shield test:source --skip-unit-tests --show-docker-output`.
5. Source Docker runtime in CI skips its unit stage because the dedicated unit lanes have already run.
6. Package-targeted validation against the built artifact.

Do not use `php bin/shield analyze:tooling` as a source compatibility gate. Source PHP compatibility belongs to `composer analyze` / `php bin/shield analyze:source`.

Serial compatibility sentinel: [`.github/workflows/unit-serial-sentinel.yml`](.github/workflows/unit-serial-sentinel.yml)

1. Runs `php bin/run-unit-tests.php --runner-mode=serial`.
2. Triggered by `workflow_dispatch`.
3. Runs weekly at `0 5 * * 1` (05:00 UTC every Monday).

Path-gated/scheduled/manual browser lane: [`.github/workflows/browser-tests.yml`](.github/workflows/browser-tests.yml)

1. Installs Composer and Node dependencies.
2. Rebuilds admin assets for the checked-out source tree.
3. Installs Chromium headless shell and runs the ActionRouter Playwright + axe lane against isolated local Docker WordPress browser lanes.
4. Runs one clean two-lane Playwright job with two workers: `composer test:browser -- --clean --lanes=2 -- --workers=2`.
5. Triggered by `workflow_dispatch`, the weekday schedule `30 6 * * 1-5` (06:30 UTC Monday through Friday), PRs with browser-relevant changed files, and browser-relevant pushes to `develop`.

Scheduled/manual cross-site lane: [`.github/workflows/cross-site-tests.yml`](.github/workflows/cross-site-tests.yml)

1. Installs Composer dependencies and builds source config/assets.
2. Runs `composer test:cross-site -- --clean`.
3. Triggered by `workflow_dispatch`, the weekday schedule, and PRs that touch import/export, WP-CLI, plugin action routing, cross-site tooling, Docker test files, Composer scripts, or the workflow.

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
