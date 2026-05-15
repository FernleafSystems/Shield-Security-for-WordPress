# Docker Test Runner

This document is subordinate to [TESTING.md](../../TESTING.md). Use `TESTING.md` for command selection, workflow policy, and verification flow. Use this file only for Docker-runner-specific behavior, environment variables, topology, and troubleshooting.

## Primary Entry Point

```bash
php bin/shield <command>
```

## Modes

| Mode | Behavior | Typical Use |
|---|---|---|
| `test:source` | Source runtime checks against working tree (quiet compose output by default) | Daily local CI-like runtime checks |
| `test:integration-local` | Host PHP integration tests with local Docker MySQL sidecar (quiet compose output by default) | Fast local integration loop with persistent DB |
| `test:package-targeted` | Focused package validation checks | Package-targeted validation |
| `test:package-full` | Full packaged runtime checks (quiet compose output by default) | Full-pathway package runtime mode |
| `analyze:source` | Run source static analysis pathway | Source static analysis |
| `analyze:package` | Run packaged static analysis pathway | Packaged static analysis |

Show live help at any time:

```bash
php bin/shield --help
```

## Environment Variables

| Variable | Default | Purpose |
|---|---|---|
| `PHP_VERSION` | from `.github/config/matrix.conf` (`DEFAULT_PHP`) | Select PHP version used by runner |
| `PHPUNIT_DEBUG` | auto-resolved | Force PHPUnit debug on/off (`1` or `0`) |
| `SHIELD_TEST_VERBOSE` | `0` | Canonical verbose flag; enables debug behavior |
| `SHIELD_UNIT_TEST_MODE` | `parallel` | Unit runner mode in Docker runtime lanes (`auto`, `parallel`, or `serial`) |
| `SHIELD_SKIP_UNIT_TESTS` | `0` | Low-level fallback to skip the Docker unit stage and run integration-only runtime checks |
| `SHIELD_INTEGRATION_LANE_WAIT_SECONDS` | `600` | Seconds `test:integration-local` waits for the machine-scoped lane lock |
| `SHIELD_DEBUG` / `SHIELD_DEBUG_PATHS` | unset | Legacy verbose aliases |
| `DEBUG_MODE` | `false` | Optional extra bash/process monitoring for custom local debug runs |

`PHPUNIT_DEBUG` resolution in `bin/run-tests-docker.sh`:

1. Explicit `PHPUNIT_DEBUG` value.
2. `SHIELD_TEST_VERBOSE=1`.
3. Legacy aliases (`SHIELD_DEBUG=1` or `SHIELD_DEBUG_PATHS=1`).
4. CI/GitHub Actions defaults debug off.
5. Local defaults debug on.

`SHIELD_UNIT_TEST_MODE` behavior in `bin/run-tests-docker.sh`:

1. Default is `parallel` for unit stage.
2. Set `SHIELD_UNIT_TEST_MODE=auto` to allow filter-aware serial fallback.
3. Set `SHIELD_UNIT_TEST_MODE=serial` to force serial PHPUnit.
4. Integration stage remains serial PHPUnit.

`SHIELD_SKIP_UNIT_TESTS` behavior in `bin/run-tests-docker.sh`:

1. Default is `0`, so Docker runtime lanes run both unit and integration stages.
2. Set `SHIELD_SKIP_UNIT_TESTS=1` to skip only the unit stage.
3. Prefer `php bin/shield test:source --skip-unit-tests` for local or CI source-runtime parity; the environment variable remains a lower-level escape hatch for direct Docker runner usage.

## Runtime Topology

Source mode:

1. Uses `tests/docker/docker-compose.yml`.
2. Runs one setup pass before runtime streams.
3. Runs latest and previous WordPress streams with `SHIELD_SKIP_INNER_SETUP=1`.
4. Uses setup cache by default for source dependency/build steps.
5. In GitHub Actions, the source runtime lane captures raw per-phase logs as failure artifacts and runs `php bin/shield test:source --skip-unit-tests --show-docker-output` so Docker focuses on runtime/integration checks after the dedicated unit lanes.
6. Use `php bin/shield test:source --refresh-setup` to force setup refresh.

Packaged modes (`test:package-targeted`, `test:package-full`, `analyze:package`):

1. Resolved through `php bin/shield` lane services.
2. Package path resolution supports explicit `--package-path` or deterministic temp package build.

Local sidecar mode (`test:integration-local`):

1. Uses `tests/docker/docker-compose.local-db.yml` (DB-only compose file).
2. Uses `COMPOSE_PROJECT_NAME=shield-local-db` and port `3311` for isolation.
3. Keeps the DB container running for repeat local runs.
4. Serializes every run and `--db-down` through `<system-temp>/shield-test-locks/integration-local.lock` because the Docker project, port, database, and WordPress test config are fixed machine-wide.
5. Teardown is explicit with `php bin/shield test:integration-local --db-down`.
6. Raw `vendor/bin/phpunit -c phpunit-integration.xml` bypasses the lane lock; use the `php bin/shield test:integration-local` or `composer test:integration` wrappers for local runs.

Local site mode (`dev:site:*` / `test:site:*`):

1. Both site families use `tests/docker/docker-compose.local-site.yml`.
2. `TESTING.md` owns command behavior, reset semantics, and workflow guidance for these site families.
3. Docker-specific identifiers remain:
   `dev:site:*` -> project `shield-local-site`, DB `shield_local_site`, port `8888`
   `test:site:*` -> project `shield-test-site`, DB `shield_test_site`, port `8889`

## Static Analysis Entrypoints

Use the direct static-analysis runner when Docker routing is not required:

```bash
php bin/shield analyze:source
php bin/shield analyze:package
```

Source static analysis also uses setup cache for `build-config` and supports:

```bash
php bin/shield analyze:source --refresh-setup
```

## Quick Examples

```bash
# Source runtime checks
php bin/shield test:source
php bin/shield test:source --show-docker-output
# Required CI parity form: php bin/shield test:source --skip-unit-tests --show-docker-output

# Local integration with DB sidecar
php bin/shield test:integration-local
php bin/shield test:integration-local --show-docker-output

# Package-targeted runtime checks
php bin/shield test:package-targeted

# Full-pathway packaged runtime mode
php bin/shield test:package-full
php bin/shield test:package-full --show-docker-output

# Source static analysis
php bin/shield analyze:source

# Packaged static analysis
php bin/shield analyze:package
```

## Quiet vs noisy compose output

These modes default to reduced compose noise while preserving test output:

- `test:source`
- `test:integration-local`
- `test:package-full`

To inspect noisy compose output during troubleshooting:

```bash
php bin/shield test:source --show-docker-output
php bin/shield test:integration-local --show-docker-output -- tests/Integration/ActionRouter/WpDashboardSummaryIntegrationTest.php
php bin/shield test:package-full --show-docker-output
```

Composer wrapper equivalent for filtered integration runs:

```bash
composer test:integration -- --show-docker-output -- tests/Integration/ActionRouter/WpDashboardSummaryIntegrationTest.php
```

## Troubleshooting

1. Ensure Docker is installed and the daemon is running.
2. Use `php bin/shield --help` to verify mode flags.
3. If a mode fails immediately, check for unknown arguments and conflicting mode flags.
4. If Composer reports `Could not authenticate against github.com`, verify auth:

```bash
gh auth status -h github.com
composer diagnose
```

5. Re-authenticate GH CLI, then sync the Composer GitHub OAuth token:

```bash
gh auth login -h github.com --git-protocol https --web
composer config --global github-oauth.github.com "$(gh auth token)"
```

6. Source runtime uses a persistent Composer cache at `tmp/.docker-composer-cache`; if cache corruption is suspected, remove that directory and rerun.
