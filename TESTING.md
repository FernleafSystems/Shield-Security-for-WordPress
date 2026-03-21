# Shield Testing Guide

`TESTING.md` is Shield's single source of truth for operational testing guidance. If another document conflicts with this file on testing commands, workflow roles, wrapper status, or verification flow, follow `TESTING.md`.

Supporting docs:

1. [`tests/docker/README.md`](tests/docker/README.md) for Docker-runner specifics only.
2. [`docs/test-suite-full-audit-2026-03-15.md`](docs/test-suite-full-audit-2026-03-15.md) for per-test keep/remove/improve decisions.
3. [`tests/TESTING-RULES-ROADMAP.md`](tests/TESTING-RULES-ROADMAP.md) for the rules/firewall testing roadmap.

## Recommended Command Map

| Goal | Command | Notes |
|---|---|---|
| Fast local subset | `composer test:fast` | Rules-focused subset for rapid iteration |
| Full local test suite | `composer test` | Runs unit and integration suites |
| Unit tests only | `composer test:unit` | Auto mode: parallel by default, serial fallback when `--filter` is passed |
| Unit tests (force serial) | `composer test:unit:serial` | Troubleshooting and compatibility checks |
| Unit tests (force parallel) | `composer test:unit:parallel` | Forces ParaTest regardless of filter args |
| Integration tests only | `composer test:integration` | Includes config generation |
| Local integration DB sidecar | `composer test:integration:local` | Composer wrapper for `php bin/shield test:integration-local` |
| Browser lane | `composer test:browser` | Playwright + axe against the isolated local Docker WordPress test site on port `8889` |
| Source runtime | `php bin/shield test:source` | Canonical source-first Docker runtime lane |
| Package-targeted runtime | `php bin/shield test:package-targeted` | Canonical focused package validation lane |
| Package-full runtime | `php bin/shield test:package-full` | Canonical full packaged Docker runtime lane |
| Tooling guard | `php bin/shield analyze:tooling` | Fail-fast syntax lint + tooling/test-platform PHPStan |
| Source static analysis | `php bin/shield analyze:source` | Canonical source static-analysis lane |
| Packaged static analysis | `php bin/shield analyze:package` | Canonical packaged static-analysis lane |
| Source static analysis (composer) | `composer analyze` | Default maps to `composer analyze:source` |
| Packaged static analysis (composer) | `composer analyze:package` | Composer wrapper for packaged analysis |

## Command Surface Notes

1. Primary CLI: `php bin/shield <command>`.
2. Composer runtime wrappers remain available:
   - `composer test:source`
   - `composer test:integration:local`
   - `composer test:browser`
   - `composer test:package-targeted`
   - `composer test:package-full`
3. `composer analyze` maps to `composer analyze:source`.
4. `test:source` and `analyze:source` cache setup state by default for faster local reruns.
5. Use `--refresh-setup` to force setup refresh:
   - `php bin/shield test:source --refresh-setup`
   - `php bin/shield analyze:source --refresh-setup`

## Compatibility Adapters

These remain supported, but they are adapters around `php bin/shield`, not the primary interface:

| Adapter | Scope |
|---|---|
| `./bin/run-docker-tests.sh` | Backwards-compatible Docker/runtime adapter |
| `php bin/run-docker-tests.php` | Backwards-compatible Docker/runtime adapter |
| `php bin/run-static-analysis.php` | Backwards-compatible static-analysis adapter |

Adapter mode map:

| Adapter Mode | Behavior |
|---|---|
| `./bin/run-docker-tests.sh` or `./bin/run-docker-tests.sh --source` | Source runtime checks against the working tree |
| `./bin/run-docker-tests.sh --package-targeted` | Focused package validation checks |
| `./bin/run-docker-tests.sh --package-full` | Full packaged runtime checks |
| `./bin/run-docker-tests.sh --analyze-source` | Source static analysis checks |
| `./bin/run-docker-tests.sh --analyze-package` | Packaged static analysis checks |
| `php bin/run-static-analysis.php` or `php bin/run-static-analysis.php --source` | Source static analysis checks |
| `php bin/run-static-analysis.php --package` | Packaged static analysis checks |

## Local Integration DB Sidecar

Use this when you want fast local integration loops on host PHP without running the full Docker runtime lanes:

```bash
composer test:integration:local
composer test:integration:local -- -- --filter RuleBuilderTest
```

Teardown is explicit and isolated to the sidecar project:

```bash
php bin/shield test:integration-local --db-down
```

## Local Browser Lane

Use this lane for ActionRouter interaction and accessibility checks that now live in Playwright instead of PHPUnit DOM assertions. The browser lane runs against a dedicated isolated local Docker WordPress test site, while `dev:site:*` continues to manage the persistent manual development site.

```bash
npm run playwright:install
php bin/shield dev:site:up
php bin/shield test:site:up
composer test:browser
composer test:browser -- --grep "Select2 lookup flow"
```

Operational notes:

1. `php bin/shield dev:site:up` starts or reuses the persistent local Docker WordPress dev site at `http://127.0.0.1:8888` for normal manual development.
2. `php bin/shield test:site:up` starts or reuses the isolated local Docker WordPress test site at `http://127.0.0.1:8889`.
3. `composer test:browser` hard-resets the isolated test site on every run before launching Playwright, so browser fixtures do not contaminate the persistent dev site or later runs.
4. `php bin/shield dev:site:reset` and `php bin/shield test:site:reset` destroy and reprovision their respective sites; `dev:site:down` and `test:site:down` stop them while preserving state.
5. `php bin/shield dev:site:wp plugin list` and `php bin/shield test:site:wp plugin list` run WP-CLI against the appropriate local `wp-cli` container after ensuring the site is ready. The command appends `--allow-root` automatically when it is not already present.
6. Both local sites fail fast if required source prerequisites are missing. At minimum, keep Composer dependencies, `plugin.json`, and built assets current before starting either site or running Playwright.
7. The browser lane is intentionally source-only. Do not add packaged-only `vendor_prefixed` content to this runtime; prefixed dependency validation belongs to `test:package-targeted` and `test:package-full`.
8. Local browser work requires Docker plus a supported Node 20 binary for Playwright. `php bin/run-node-tool.php` resolves that on demand without changing the machine default Node.
9. CI runs Chromium only. Headed debugging is still available by forwarding Playwright flags through the browser command, for example: `composer test:browser -- --headed`.

### Optional Playground Tooling

Raw Playground is no longer the default browser lane. Keep it for standalone smoke/debug work only:

```bash
npm install --prefix tools/playground --no-audit --no-fund
composer playground:local
composer playground:local:check
composer playground:local:clean
```

## CI Workflow Role Split

Required source-first gate: [`.github/workflows/tests.yml`](.github/workflows/tests.yml)

1. Tooling guard on PHP 7.4 via `php bin/shield analyze:tooling`.
2. Source static analysis via `composer analyze:source`.
3. Parallel unit tests via `composer test:unit`.
4. Source Docker runtime checks focused on runtime and integration coverage.
5. Package-targeted validation against the built artifact.

Serial compatibility sentinel: [`.github/workflows/unit-serial-sentinel.yml`](.github/workflows/unit-serial-sentinel.yml)

1. Runs `composer test:unit:serial`.
2. Triggered by `workflow_dispatch`.
3. Runs weekly at `0 5 * * 1` (05:00 UTC every Monday).

Scheduled/manual packaged pathway: [`.github/workflows/docker-tests.yml`](.github/workflows/docker-tests.yml)

1. Runs the full packaged matrix runtime checks.
2. Runs packaged static analysis.
3. Runs package playground smoke checks.
4. Triggered by `workflow_dispatch` and the weekday schedule `0 6 * * 1-5` (06:00 UTC Monday through Friday).

Scheduled/manual browser lane: [`.github/workflows/browser-tests.yml`](.github/workflows/browser-tests.yml)

1. Installs Composer and Node dependencies.
2. Rebuilds admin assets for the checked-out source tree.
3. Installs Chromium and runs the ActionRouter Playwright + axe lane against the isolated local Docker WordPress test site.
4. Triggered by `workflow_dispatch` and the weekday schedule `30 6 * * 1-5` (06:30 UTC Monday through Friday).

## Local Verification Commands

Use these to verify the command surface and documentation alignment:

```bash
php bin/shield --help
php bin/run-docker-tests.php --help
php bin/run-static-analysis.php --help
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

1. Keep testing validation focused on runtime, static analysis, and package correctness.
2. Do not add tests that assert documentation prose.
3. Ignore unrelated non-conflicting workspace changes while implementing testing-doc updates.
4. If conflicting changes are found in the touched testing-doc slice, stop and report before continuing.
