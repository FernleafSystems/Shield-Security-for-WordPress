# Shield Testing Guide

`TESTING.md` is Shield's single source of truth for the supported test command surface and workflow roles. If another document disagrees with this file, follow `TESTING.md`.

Supporting docs:

1. [`tests/docker/README.md`](tests/docker/README.md) for Docker-runner mechanics only.
2. [`docs/test-suite-full-audit-2026-03-15.md`](docs/test-suite-full-audit-2026-03-15.md) for the audit record.
3. [`tests/TESTING-RULES-ROADMAP.md`](tests/TESTING-RULES-ROADMAP.md) for rules/firewall coverage planning only.

Composer 2.8 or newer is required for the supported command surface. Unit command argument isolation relies on Composer's `@no_additional_args` control token.

## Public Commands

| Goal | Command | Notes |
|---|---|---|
| Full local confidence gate | `composer test` | Builds config, then runs unit and integration lanes |
| Unit tests | `composer test:unit` | Enforces filesystem-fixture policy, then builds config and runs the unit runner |
| Integration tests | `composer test:integration` | Public wrapper around the local Docker-backed integration lane |
| Browser lane | `composer test:browser` | Playwright + axe against an automatically leased isolated Docker WordPress browser lane |
| Cross-site sync lane | `composer test:cross-site` | Two Docker WordPress sites exercising Shield import/export master/slave sync |
| Package validation | `composer test:package` | Public wrapper around targeted package validation |
| Public-to-current upgrade smoke lane | `composer test:upgrade-public` | Manual release-confidence lane: install latest public Shield, prime options, upgrade through WordPress updater to a current package zip, and fail on Shield-scoped errors |
| Popular plugin compatibility smoke lane | `composer test:popular-plugins` | Manual release-confidence lane: install and activate the pinned known-good popular plugin set, then install current packaged Shield and fail on fatal or Shield-scoped errors |
| Source static analysis | `composer analyze` | Public wrapper around source static analysis |
| JS static checks | `npm run test:js` | Policy, ESLint, and checkJs TypeScript validation only |

## Verification Policy

Use the narrowest supported command that covers the changed behavior, then widen only when the risk or task requires it. For a completed PHP change, `composer test` is the normal broad local confidence gate.

Source and full-package Docker testing use only the latest WordPress runtime by default. This is the preferred local behavior. Testing the retained previous major is exceptional compatibility coverage, not a routine final gate; use it only when a task explicitly targets that version or when reproducing the source-runtime CI job itself. Required CI selects its own broader coverage, so normal local verification does not need to duplicate it.

Docker-backed commands keep Compose output concise by default. See [`tests/docker/README.md`](tests/docker/README.md#quiet-vs-noisy-compose-output) for troubleshooting output and runner mechanics.

## Private Packagist Composer Auth

Shield consumes the private Composer package `fernleafsystems/worpdrive-client` from the Fernleaf Systems Private Packagist repository at `https://repo.packagist.com/fernleafsystems/`. Any lane that runs Composer install, update, package build, or a Composer-backed test command needs Private Packagist auth before Composer starts.

CI uses the repository secret `PACKAGIST_TOKEN`. The workflow step `Prepare Private Packagist Composer auth` runs `.github/scripts/prepare-packagist-composer-auth.php` before Composer commands. If `PACKAGIST_TOKEN` is missing or blank, the helper exits before dependency install with a clear Packagist-token error. It writes `COMPOSER_AUTH` to `$GITHUB_ENV` and does not print the token or auth JSON.

Use a read-only Private Packagist org token for CI installs after `composer.lock` is committed. Use an update-capable org token or user token only when intentionally generating or updating lock files.

Local preflight:

```bash
export PACKAGIST_TOKEN='<private-packagist-token>'
php .github/scripts/prepare-packagist-composer-auth.php --check-only
```

```powershell
$env:PACKAGIST_TOKEN='<private-packagist-token>'
php .github/scripts/prepare-packagist-composer-auth.php --check-only
```

Local Composer commands also need `COMPOSER_AUTH` or Composer's global auth configured for `repo.packagist.com`. Keep secrets out of shell history, committed files, and command output. Do not commit `auth.json` or literal `COMPOSER_AUTH` JSON.

Auth-required public Composer lanes:

| Lane | Why auth is required |
|---|---|
| `composer install` / `composer update` | Resolves `fernleafsystems/worpdrive-client` from Private Packagist |
| `composer test`, `composer test:unit`, `composer test:integration` | Composer-backed PHP test lanes depend on installed private packages |
| `composer test:browser`, `composer test:cross-site` | Install dependencies before Docker/browser runtime work |
| `composer test:package`, `composer package-plugin`, `composer build-zip` | Package build/validation installs and packages Composer dependencies |
| `composer test:upgrade-public`, `composer test:popular-plugins` | Release-confidence package lanes build or consume the current package |
| `composer analyze` | Source analysis depends on Composer-installed dependencies |

Auth-required internal lanes are the Composer-backed source, package, Docker, browser, cross-site, release, and analysis paths listed in this file, including `test:source`, `test:integration-local`, `test:docker:cleanup`, `test:package-targeted`, `test:package-full`, `analyze:source`, `analyze:package`, `git:pre-commit`, `dev:site:*`, and `test:site:*` when they invoke Composer-installed tooling. JS-only checks and CI helper-script regression tests do not need Packagist auth unless Composer commands are added to those jobs later.

Auth preflight is wired into `.github/workflows/tests.yml`, `reusable-unit-tests.yml`, `reusable-build-package.yml`, `reusable-build-zip.yml`, `unit-serial-sentinel.yml`, `browser-tests.yml`, and `cross-site-tests.yml`. Tag release and customer ZIP workflows inherit secrets through the reusable ZIP build. JS-only jobs, cache cleanup, and standalone shell regression jobs remain outside this path until they invoke Composer.

## Unit Test Narrowing

Use `composer test:unit` for normal unit work, including full-suite, path-focused, and filtered runs:

```bash
composer test:unit
composer test:unit -- tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php
composer test:unit -- --filter PluginNavsOperatorModesTest
composer test:unit -- --filter testSomeMethod tests/Unit/Controller/Plugin/PluginNavsOperatorModesTest.php
```

The unit runner auto-selects ParaTest. Full-suite and path-only runs use ParaTest `WrapperRunner`; ordinary `--filter` and `--filter=...` runs use ParaTest functional mode so standard Composer/PHPUnit-style focused commands stay parallel by default. Native PHPUnit dataset shortcut filters such as `testMethod@dataset` and `testMethod#2` use the serial PHPUnit path in auto mode to preserve PHPUnit parity. Use `php bin/run-unit-tests.php --runner-mode=serial` only for diagnostic work and the serial sentinel lane.

`composer test:unit:policy` is the standalone filesystem-fixture policy check. Unit tests that create temporary files or directories must use `TempDirLifecycleTrait`, obtain fixtures with `createTrackedTempDir()`, `createTrackedTempPath()`, or `createTrackedTempFile()`, and call `cleanupTrackedTempDirs()` from `tearDown()`. The statement-local policy rejects direct `tempnam( sys_get_temp_dir(), ... )`, direct concatenation or `Path::join()` of a system-temp path with `uniqid()` or `random_bytes()`, direct system-temp destinations passed to `mkdir()`, `touch()`, `file_put_contents()`, `copy()`, or `rename()`, and direct writing or dynamic-mode `fopen()` calls. Literal read-only `fopen()` modes (`r`, `rb`, and `rt`) and existing-root inspection are allowed. The policy deliberately does not trace variables, aliases, control flow, or data flow. `TempDirLifecycleTraitTest` is the sole exception because it must exercise the helper against the real temp root.

`composer test:unit:runner` is the CI/internal runner-only command: it builds generated config and invokes the current auto-selecting runner without repeating policy. Normal local use should remain on `composer test:unit`, which runs policy before the runner.

## Pre-commit checks

`php bin/shield git:pre-commit --stdin --null` accepts NUL-delimited changed file paths from Git, filters changed PHP files, and feeds them into the existing syntax lint, PHPStan, and unit test tooling. A local pre-commit hook can stay thin by piping `git diff --cached --name-only --diff-filter=ACMR -z` into that command.

## Choosing Verification Scope

| Situation | Preferred verification |
|---|---|
| Focused PHP behavior | Narrow `composer test:unit` or `composer test:integration` invocation |
| Completed PHP change | `composer test` |
| Source behavior that specifically needs the containerized runtime | `php bin/shield test:source` |
| JavaScript static changes | `npm run test:js` |
| ActionRouter interaction or accessibility | `composer test:browser` |
| Import/export site communication | `composer test:cross-site` |
| Package structure or prefixed dependencies | `composer test:package` |
| Release upgrade or ecosystem compatibility | The relevant manual release-confidence lane |
| Exact reproduction of a CI job | The command recorded under [CI Workflow Roles](#ci-workflow-roles) |

Do not expand a normal local run into every CI, browser, package, release, or compatibility lane without a corresponding risk. CI supplies its configured broader coverage independently.

## Manual Release-Confidence Lanes

These network-dependent lanes are deliberately outside normal local verification and the default PR gate.

### Public-To-Current Upgrade

Use this lane before publishing a release package when you need confidence that the latest public Shield release can upgrade in place to the current package through WordPress' normal plugin updater.

```bash
composer test:upgrade-public
composer test:upgrade-public -- --package-zip=tmp/wp-simple-firewall-current.zip --artifact-dir=tmp/shield-upgrade-public
php bin/shield test:upgrade-public --package-zip=tmp/wp-simple-firewall-current.zip --artifact-dir=tmp/shield-upgrade-public --show-docker-output
```

What it proves:

1. Starts a clean Docker WordPress site dedicated to this lane.
2. Installs the latest public Shield from WordPress.org with `wp plugin install wp-simple-firewall --activate`.
3. Looks up public plugin metadata inside WordPress with `plugins_api( 'plugin_information' )`.
4. Requires the current package zip version to be greater than the installed public version.
5. Copies test-only MU-plugin fixtures into the site to inject a native `update_plugins` offer and collect PHP/log errors.
6. Serves the current package zip from the test WordPress site and verifies the URL with WordPress safe HTTP handling.
7. Primes safe transferable Shield options through the active public plugin runtime and writes `priming-report.json`.
8. Runs `wp plugin update wp-simple-firewall --format=json`; it does not use file replacement or `plugin install --force`.
9. Runs due cron so Shield's scheduled post-upgrade work executes.
10. Fails on updater failure, non-zero WP-CLI failure, fatal errors, or Shield-scoped warnings/notices/deprecations/errors/exceptions in collected logs.

Options and environment:

| Contract | Meaning |
|---|---|
| `--package-zip=<path>` | Use an existing release zip. If omitted, the lane builds one with `bin/build-zip.php`. |
| `--artifact-dir=<path>` | Write lane artifacts here. Overrides `SHIELD_UPGRADE_TEST_ARTIFACT_DIR`. |
| `--show-docker-output` | Mirror Docker/WP-CLI output to the terminal while still writing artifacts. |
| `SHIELD_UPGRADE_TEST_ARTIFACT_DIR` | Default artifact directory when `--artifact-dir` is omitted. |
| `SHIELD_UPGRADE_TEST_COMPOSE_PROJECT` | Override the Docker Compose project. Default: `shield-upgrade-public`. |
| `SHIELD_UPGRADE_TEST_SITE_PORT` | Override the host WordPress port. Default: `8894`. |

Artifacts:

| Artifact | Contents |
|---|---|
| `upgrade-public-summary.json` | Overall status, versions, paths, update result, and log findings. |
| `public-version.json` | WordPress.org metadata returned inside the test site. |
| `priming-report.json` | Safe option primer report: applied, skipped, excluded, safety resets, errors. |
| `update-result.json` | Parsed WP-CLI updater result. |
| `wp-cli.log` | WP-CLI and compose command output. |
| `wordpress-debug.log` | WordPress/PHP debug log; present even when empty. |
| `error-events.jsonl` | PHP error/exception/shutdown-fatal events; present even when empty. |
| `docker.log` | Docker service logs, collected on failure. |

Exit codes:

| Code | Meaning |
|---|---|
| `0` | Upgrade passed and no fatal or Shield-scoped error output was found. |
| `1` | Upgrade/test failure, including updater failure or collected Shield-scoped errors. |
| `2` | Setup/environment failure, such as Docker unavailable or WordPress setup failure. |
| `3` | Version gate non-pass: package version is not greater than the latest public version. |

This lane depends on Docker and WordPress.org availability, so it is intentionally manual in v1 and outside the default PR gate. A release CI job can wire it later without changing the command contract:

```bash
composer test:upgrade-public -- --package-zip="$ZIP_PATH" --artifact-dir="$RUNNER_TEMP/shield-upgrade-public"
```

### Popular Plugin Compatibility

Use this lane before publishing a release package when you need confidence that current packaged Shield can activate alongside the pinned high-popularity WordPress.org plugin stack without library/autoload/runtime conflicts.

```bash
composer test:popular-plugins
composer test:popular-plugins -- --package-zip=tmp/wp-simple-firewall-current.zip --artifact-dir=tmp/popular-plugin-compat
php bin/shield test:popular-plugins --package-zip=tmp/wp-simple-firewall-current.zip --artifact-dir=tmp/popular-plugin-compat --show-docker-output
```

What it proves:

1. Starts a clean Docker WordPress site dedicated to this lane.
2. Installs test-only MU fixtures to collect PHP/log errors using the same collector and scanner as the public upgrade lane.
3. Reads the pinned 20-plugin known-good manifest from `tests/fixtures/popular-plugin-compat/plugin-slugs.json`; the original captured 100-plugin list is archived at `tests/fixtures/popular-plugin-compat/plugin-slugs-popular-100.json`.
4. Installs and activates each companion plugin one at a time from WordPress.org.
5. Collects a companion-only baseline and exits as a baseline failure if that stack is already broken before Shield is active.
6. Builds or uses the current Shield package zip, publishes it into the shared WordPress volume, and installs it with `wp plugin install <package-zip-path> --activate`.
7. Runs an active-plugin probe, a basic bootstrap probe, and due cron.
8. Fails on Shield install/activation failure, non-zero WP-CLI failure, fatal errors, or Shield-scoped warnings/notices/deprecations/errors/exceptions in collected logs.

Options and environment:

| Contract | Meaning |
|---|---|
| `--package-zip=<path>` | Use an existing release zip. If omitted, the lane builds one with `bin/build-zip.php`. |
| `--artifact-dir=<path>` | Write lane artifacts here. Overrides `SHIELD_POPULAR_PLUGIN_TEST_ARTIFACT_DIR`. |
| `--show-docker-output` | Mirror Docker/WP-CLI output to the terminal while still writing artifacts. |
| `SHIELD_POPULAR_PLUGIN_TEST_ARTIFACT_DIR` | Default artifact directory when `--artifact-dir` is omitted. |
| `SHIELD_POPULAR_PLUGIN_TEST_COMPOSE_PROJECT` | Override the Docker Compose project. Default: `shield-popular-plugins`. |
| `SHIELD_POPULAR_PLUGIN_TEST_SITE_PORT` | Override the host WordPress port. Default: `8895`. |

Artifacts:

| Artifact | Contents |
|---|---|
| `popular-plugin-compat-summary.json` | Overall status, package details, companion count, activation results, active plugins, and log findings. |
| `companion-plugins.json` | The pinned companion plugin slugs used for the run. |
| `activation-results.json` | Per-plugin install and activation statuses, with bounded WP-CLI output for each install/activation step. |
| `wp-cli.log` | WP-CLI and compose command output. |
| `wordpress-debug.log` | WordPress/PHP debug log; present even when empty. |
| `error-events.jsonl` | PHP error/exception/shutdown-fatal events; present even when empty. |
| `docker.log` | Docker service logs, collected on failure. |

Exit codes:

| Code | Meaning |
|---|---|
| `0` | Compatibility lane passed and no fatal or Shield-scoped error output was found. |
| `1` | Shield compatibility failure, including Shield activation failure or collected Shield-scoped errors. |
| `2` | Setup/environment failure, such as Docker unavailable or WordPress setup failure. |
| `4` | Companion plugin baseline failure before Shield was installed or activated. |

This lane depends on Docker and WordPress.org availability, so it is intentionally manual and outside the default PR gate.

## Internal Lane Ownership

These commands remain the owned internal lanes behind the public surface and CI workflows. Do not add new public wrappers for them.

| Internal Command | Role |
|---|---|
| `php bin/shield analyze:source` | Canonical source static-analysis lane; source parse-compatibility gate when run on PHP 7.4 |
| `php bin/shield analyze:package` | Packaged static analysis lane |
| `php bin/shield test:source` | Source-first Docker runtime lane; latest WordPress by default |
| `php bin/shield test:integration-local` | Local Docker-backed WordPress integration lane |
| `php bin/shield test:cross-site` | Two-site Docker WordPress import/export sync lane |
| `php bin/shield test:docker:cleanup` | Explicit labeled Docker cleanup for source-test harness scopes |
| `php bin/shield test:package-targeted` | Targeted package validation lane |
| `php bin/shield test:package-full` | Manual local deep packaged runtime lane; latest WordPress by default |
| `php bin/shield test:upgrade-public` | Manual public-to-current package upgrade smoke lane |
| `php bin/shield test:popular-plugins` | Manual packaged Shield compatibility lane against a pinned popular plugin stack |
| `php bin/run-unit-tests.php --runner-mode=serial` | Serial unit sentinel path |

`test:source` and `analyze:source` cache setup state by default for faster local reruns. Use `--refresh-setup` when you need a clean setup pass.

`test:source` and `test:package-full` start, build, and run only the latest WordPress runtime by default. Both accept `--include-previous-wp` for an explicit previous-major compatibility investigation or exact reproduction of the source-runtime CI job. Do not add it to normal local verification or routine final gates.

Source-test Docker resources are labeled with `com.fernleaf.harness`, `com.fernleaf.run-id`, `com.fernleaf.lane`, `com.fernleaf.lifecycle`, and `com.fernleaf.expires-at`. Use the explicit cleanup command for auditable dry-runs and scoped removal:

```bash
php bin/shield test:docker:cleanup --scope=source --dry-run --all
php bin/shield test:docker:cleanup --scope=integration-local --dry-run --all
php bin/shield test:docker:cleanup --scope=cross-site --dry-run --all
php bin/shield test:docker:cleanup --scope=test-site --dry-run --all
php bin/shield test:docker:cleanup --scope=dev-site --dry-run --all
```

The `browser` scope is also supported by `test:docker:cleanup`, but `composer test:browser:cleanup` remains the preferred browser cleanup entry point because it also audits stale runtime-refresh staging workspaces. Cleanup never uses `docker system prune` and only targets the selected harness label.

`composer test:integration` is focused on behaviour-level WordPress runtime coverage. Browser-managed ActionRouter page-shell and DOM-contract tests are intentionally excluded from the default PHPUnit integration lane and covered via `composer test:browser`.

## Local integration lane serialization

`composer test`, `composer test:integration`, and `php bin/shield test:integration-local` are serialized across local terminals, agents, and worktrees with a machine-scoped `flock()` lock. The lock protects the fixed local sidecar resources: Compose project `shield-local-db`, SQL port `127.0.0.1:3311`, database `wordpress_test_local`, and the shared WordPress test-library config.

- Lock file: `<system-temp>/shield-test-locks/integration-local.lock`.
- Default wait: 600 seconds.
- Override wait: `SHIELD_INTEGRATION_LANE_WAIT_SECONDS=<positive-integer>`.
- `--db-down` uses the same lock, so teardown cannot remove the sidecar while another integration run is active.

After Compose reports the DB container healthy, the lane also verifies host PHP can connect over TCP to `127.0.0.1:3311`, select `wordpress_test_local`, and run `SELECT 1`. This is the readiness contract WordPress bootstrap depends on. The lane also removes a cached WordPress test config when its DB constants do not match the fixed local sidecar contract, then asserts the generated config before PHPUnit starts.

Each test run then drops and recreates `wordpress_test_local` inside the lane lock before WordPress installation. This removes retained Shield tables and their InnoDB high-water allocation from earlier runs while preserving a failed run's database for inspection until the next run. The sidecar uses a 2 GiB tmpfs and disables binary logging because this isolated test database has no replication or point-in-time recovery role; otherwise MySQL 8 retains each run's write log until its 30-day binlog expiry.

The lock file may remain after a run and contains diagnostic metadata for the last acquired lease. Do not delete it as stale cleanup; `flock()` releases automatically when the owning process exits. Raw `vendor/bin/phpunit -c phpunit-integration.xml` bypasses this guard and is not part of the supported local integration command surface.

The sidecar DB resources use stable reusable labels under the `integration-local` cleanup scope so normal repeat runs can reuse the same DB container. A run after Docker Compose file changes may recreate the sidecar once; subsequent unchanged runs should not recreate it. `php bin/shield test:integration-local --db-down` remains the normal functional teardown because it observes the lane lock. Use `php bin/shield test:docker:cleanup --scope=integration-local --dry-run --all` when auditing Docker resources directly.

### Database compatibility profiles

The serialized integration lane exposes three fixed database profiles. Arbitrary images are not accepted:

```bash
php bin/shield test:integration-local --db-profile=mysql80 -- --group database-compat
php bin/shield test:integration-local --db-profile=mysql56 -- --group database-compat
php bin/shield test:integration-local --db-profile=mariadb106 -- --group database-compat
php bin/shield test:integration-local --db-down
```

`mysql80` remains the default. Switching profile recreates the tmpfs-backed sidecar under the same serialized Compose project. Run `--db-down` after a non-default compatibility sequence to restore a clean default start for later work. `database-compat` tests cover portable event aggregation, report-period continuity, index application, and MyISAM behavior; the broader consumer suite stays on the default profile.

## Local Browser Lane

Use this lane for ActionRouter interaction and accessibility checks that now live in Playwright instead of PHPUnit DOM assertions. Browser tests run against an automatically leased isolated Docker WordPress lane, while `dev:site:*` continues to manage the persistent manual development site.

Most developers and agents should start with `composer test:browser`. It rebuilds production browser/runtime bundles, reuses warm lanes for practical local speed, and defaults warm local runtime refresh to `auto` so repeated runs avoid a full content scan when the metadata cache is valid. Clean mode and CI still force full runtime freshness.

Browser lane counts describe parallel isolated sites, not different WordPress-version streams.

```bash
npm run playwright:install
composer test:browser
composer test:browser -- -- --list
composer test:browser -- --warm -- -g "Select2 lookup flow"
composer test:browser -- --warm --runtime-refresh=full -- tests/browser/action-router/security-headers-readiness.spec.js --workers=1
composer test:browser -- --warm -- tests/browser/action-router/drill-down-flows.spec.js --workers=1
composer test:browser -- --clean -- tests/browser/action-router/drill-down-flows.spec.js -g "configure opens a prefetched diagnosis without a standalone diagnosis request"
composer test:browser:cleanup -- --all --lanes=2
```

Operational notes:

1. `php bin/shield dev:site:up` starts or reuses the persistent local Docker WordPress dev site at `http://127.0.0.1:8888` for normal manual development.
2. `php bin/shield test:site:up` remains available for the legacy/manual isolated test site at `http://127.0.0.1:8889`, but browser tests do not use that port.
3. Local `composer test:browser` defaults to warm mode, auto runtime refresh, two lanes, two Playwright workers, and Playwright `fullyParallel`. The first default lane is `http://127.0.0.1:8890`.
4. The browser CI workflow runs clean mode with two browser lanes and two Playwright workers in one job. `composer test:browser` rebuilds assets before runtime hashing, so the workflow only needs dependency install and Playwright browser install before the browser lane command.
5. `php bin/shield dev:site:reset` and `php bin/shield test:site:reset` destroy and reprovision their respective manual sites; `dev:site:down` and `test:site:down` stop them while preserving state.
6. `php bin/shield dev:site:wp plugin list` and `php bin/shield test:site:wp plugin list` run WP-CLI against the appropriate local `wp-cli` container after ensuring the site is ready. The command appends `--allow-root` automatically when it is not already present.
7. Browser lanes fail fast if required source prerequisites are missing. At minimum, keep Composer dependencies, npm dependencies, `plugin.json`, Docker, and Playwright current before running browser tests; compiled browser assets are rebuilt automatically for non-list browser runs.
8. The browser lane is intentionally source-only. Do not add packaged-only `vendor_prefixed` content to this runtime; prefixed dependency validation belongs to the package lanes.
9. Local browser work requires Docker plus a supported Node 20 binary for webpack and Playwright. `php bin/run-node-tool.php` resolves that on demand without changing the machine default Node.
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
- Default local run: `mode=warm`, `runtime-refresh=auto`, `lanes=2`, `workers=2`, `fullyParallel=true`.
- Default CLI CI run: `mode=clean`, `lanes=1`, `workers=1`; the GitHub browser workflow overrides this to `lanes=2`, `workers=2`.
- Each lane has its own WordPress container, port, database, and Playwright output directory. Browser lanes start at port `8890`, leaving the legacy/manual `test:site` port `8889` alone.
- All lanes share one MySQL container, so parallel browser commands avoid starting multiple database servers.
- Browser worker isolation is keyed by Playwright `parallelIndex`. PHP passes `SHIELD_BROWSER_LANE_MAP` as a JSON object keyed by `parallelIndex`, and every worker uses its mapped lane URL, fixture token, auth state file, and output directory.
- Warm mode starts or reuses lane containers, refreshes the copied runtime, installs the runtime-only fixture endpoint, and skips baseline provisioning only when the readiness marker still matches the lane and the site is healthy.
- The browser readiness marker includes the lane profile, site/database contract, fixture contract, fixture token hash, runtime manifest hash, and expiry. Warm reuse is skipped when any of those values are stale.
- Clean mode preserves reset semantics: reset lane containers and volumes, recreate the lane database, refresh runtime, install the fixture endpoint, provision baseline state, and write the readiness marker. Clean mode also forces full runtime-refresh hashing.
- Runtime refresh defaults to `--runtime-refresh=auto` for warm local runs, based on a per-file metadata cache under `tmp/`; the cache enumerates files and does not use directory mtimes. Auto mode still rebuilds bundles before cache evaluation, validates both the cache wrapper and cached manifest payload, and silently rebuilds the manifest on stale or corrupt cache. Clean mode and CI force `--runtime-refresh=full`, which rebuilds one content-hash host manifest per browser command before applying each lane's differential copy/delete.
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

Cleanup:

```bash
composer test:browser:cleanup
composer test:browser:cleanup -- --all --lanes=2
composer test:browser:cleanup -- --dry-run --all --lanes=2
php bin/shield test:docker:cleanup --scope=browser --dry-run --all --lanes=2
```

Automatic browser-run cleanup removes current-run transient resources, expired or malformed labeled resources, and old unlabeled browser resources. Manual cleanup removes expired, malformed, and old unlabeled browser resources; `--all` also purges reusable warm volumes. `--dry-run` audits the Docker resources and stale runtime workspaces that would be removed without deleting them. Runtime refresh staging workspaces under `tmp/.browser-runtime-refresh` are removed after each refresh and stale workspaces are garbage-collected by the cleanup command.

Docker list, inspect, compose, and remove failures are reported as cleanup findings; they are not treated as an empty cleanup result.

Browser Docker containers, volumes, and networks use the labels `com.fernleaf.harness`, `com.fernleaf.run-id`, `com.fernleaf.lane`, `com.fernleaf.lifecycle`, and `com.fernleaf.expires-at`. Warm mode keeps valid reusable volumes only; containers and networks are transient.

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
5. If the failure names missing `vendor/autoload.php`, invalid `plugin.json`, missing npm/webpack dependencies, or missing Playwright browsers, refresh the named prerequisite and rerun the same browser command. Do not run a separate browser-lane asset build; `composer test:browser` rebuilds those bundles before runtime hashing.

```bash
composer install
composer build:config
npm install
npm run playwright:install
```

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

## Local Cross-Site Lane

Use this lane for Shield-to-Shield import/export communication. It provisions a master WordPress site and a slave WordPress site on one Docker network, uses dotted Docker DNS aliases for site-to-site HTTP, and drives setup, cron, queue processing, and assertions with WP-CLI.

```bash
composer test:cross-site
composer test:cross-site -- --warm
composer test:cross-site -- --clean --show-setup-output
```

Operational notes:

1. The lane uses internal URLs `http://wordpress-master.shield-cross-site.example.com` and `http://wordpress-slave.shield-cross-site.example.com`; exposed host ports are only for diagnostics.
2. Local runs default to warm mode. CI defaults to clean mode.
3. Successful runs stay quiet except for the final lane result; use `--show-setup-output` when Docker, provisioning, or runtime-refresh setup logs are needed.
4. The lane has a single lock under `tmp/cross-site-test-lane` because both sites share one Compose project and one database container.
5. The runtime helper grants every capability required by transferable Shield options, plus WP-CLI, before generating the option corpus.
6. The comparison excludes only explicit non-corpus keys: slave-local sync state such as `importexport_masterurl`, and runtime prerequisites such as `global_enable_plugin_features` and `importexport_enable`. Every generated corpus key must change from its baseline after Shield option normalization.
7. `SHIELD_CROSS_SITE_MASTER_PORT` and `SHIELD_CROSS_SITE_SLAVE_PORT` override the diagnostic host ports if `8892` or `8893` are unavailable.
8. This lane covers Shield import/export sync only. MainWP scenarios should be added as explicit consumers of the same harness when they exist.
9. Cross-site containers, volumes, and networks are labeled under the `cross-site` cleanup scope. CI removes them with `php bin/shield test:docker:cleanup --scope=cross-site --all`; use `--dry-run` locally to audit before removal.

## Optional Playground Tooling

Raw Playground is no longer part of the supported test surface. Keep the local helper only for standalone smoke or debugging work:

```bash
npm ci --prefix tools/playground --no-audit --no-fund
php bin/run-playground-local.php
php bin/run-playground-local.php --run-blueprint
php bin/run-playground-local.php --clean
```

## CI Workflow Roles

CI behavior is recorded here for diagnosis and exact job reproduction; it does not define the preferred local test scope. Required checks use job-level path filters because workflow-level `paths` can leave skipped required checks pending. A docs-only change should normally run only the lightweight changed-file detector.

| Workflow | Trigger and role |
|---|---|
| [Required source-first gate](.github/workflows/tests.yml) | Job-level path-gated by [`.github/ci-path-filters.yml`](.github/ci-path-filters.yml); manual dispatch runs the full gate. It runs source analysis on PHP 7.4, JS checks, unit tests on PHP 7.4 and 8.4, package build/targeted validation, and `php bin/shield test:source --skip-unit-tests --include-previous-wp --show-docker-output`. CI explicitly owns this exceptional two-stream WordPress run. |
| [Serial compatibility sentinel](.github/workflows/unit-serial-sentinel.yml) | Runs `php bin/run-unit-tests.php --runner-mode=serial` manually and weekly at 05:00 UTC Monday. |
| [Browser tests](.github/workflows/browser-tests.yml) | Runs for browser-relevant pull requests, pushes to `develop`, manual dispatch, and weekdays at 06:30 UTC. It installs Composer and Node dependencies plus Chromium, then runs `composer test:browser -- --clean --lanes=2 -- --workers=2`. |
| [Cross-site tests](.github/workflows/cross-site-tests.yml) | Runs for pull requests affecting import/export, WP-CLI, the test harness, Docker, Composer, or this workflow; also runs manually and weekdays at 06:45 UTC. It installs dependencies, builds assets, and runs `composer test:cross-site -- --clean`. |
| [Customer test ZIP](.github/workflows/customer-test-zip.yml) | Manual artifact-only workflow that builds a selected branch or ref through the reusable `composer build-zip` path. It records the ref, commit, artifact URL, ZIP SHA-256, and artifact digest without creating a tag or GitHub Release. |

For local reproduction of the required gate's individual jobs, use `composer analyze`, `npm run test:js`, `composer test:unit:policy` followed by `composer test:unit:runner` on the relevant PHP runtime, and the source command shown above. Reproduce the package path with:

```bash
composer package-plugin -- --output=tmp/shield-package-ci
php bin/shield test:package-targeted --package-path=tmp/shield-package-ci
```

The source-runtime job uploads its raw per-phase logs as a failure artifact.

Do not use `php bin/shield analyze:tooling` as a source compatibility gate. Source PHP compatibility belongs to `composer analyze` / `php bin/shield analyze:source`.

## Tooling Verification Commands

The WordPress version detector's hermetic shell regression command runs the real detector with fixture-backed command shims; it does not call live WordPress or GitHub endpoints:

```bash
bash .github/scripts/test-detect-wp-versions.sh
```

Use these to verify the command surface and documentation alignment:

```bash
php bin/shield --help
composer run-script --list
```

For focused unit work, use the supported commands under [Unit test narrowing](#unit-test-narrowing). Direct vendor PHPUnit/ParaTest commands are diagnostic tools, not normal workflow entry points.

For GitHub authentication issues during Docker or source runs, use the troubleshooting steps in [`tests/docker/README.md`](tests/docker/README.md).

## Testing Boundaries

1. Keep testing validation focused on runtime, static analysis, package correctness, and browser coverage where it replaces brittle PHP UI assertions.
2. Do not add tests that assert documentation prose.
