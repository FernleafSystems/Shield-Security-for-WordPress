# Docker Test Runner

This document covers Docker-runner specifics only.

For canonical testing commands, CI role split, and overall testing guidance, see [TESTING.md](../../TESTING.md).

## Documentation Ownership

1. `TESTING.md` owns canonical testing commands and workflow policy.
2. `tests/docker/README.md` owns Docker runner behavior, environment variables, and troubleshooting.

## Primary Entry Point

```bash
php bin/shield <command>
```

Compatibility entrypoints remain:

```bash
./bin/run-docker-tests.sh
php bin/run-docker-tests.php
php bin/run-static-analysis.php
```

## Modes

| Mode | Behavior | Typical Use |
|---|---|---|
| `test:source` | Source runtime checks against working tree | Daily local CI-like runtime checks |
| `test:package-targeted` | Focused package validation checks | Package-targeted validation |
| `test:package-full` | Full packaged runtime checks | Full-pathway package runtime mode |
| `analyze:source` | Run source static analysis pathway | Source static analysis |
| `analyze:package` | Run packaged static analysis pathway | Packaged static analysis |

Show live help at any time:

```bash
php bin/shield --help
php bin/run-docker-tests.php --help
```

## Environment Variables

| Variable | Default | Purpose |
|---|---|---|
| `PHP_VERSION` | from `.github/config/matrix.conf` (`DEFAULT_PHP`) | Select PHP version used by runner |
| `PHPUNIT_DEBUG` | auto-resolved | Force PHPUnit debug on/off (`1` or `0`) |
| `SHIELD_TEST_VERBOSE` | `0` | Canonical verbose flag; enables debug behavior |
| `SHIELD_DEBUG` / `SHIELD_DEBUG_PATHS` | unset | Legacy verbose aliases |
| `DEBUG_MODE` | `false` | Optional extra bash/process monitoring for custom local debug runs |

`PHPUNIT_DEBUG` resolution in `bin/run-tests-docker.sh`:

1. Explicit `PHPUNIT_DEBUG` value.
2. `SHIELD_TEST_VERBOSE=1`.
3. Legacy aliases (`SHIELD_DEBUG=1` or `SHIELD_DEBUG_PATHS=1`).
4. CI/GitHub Actions defaults debug off.
5. Local defaults debug on.

## Runtime Topology

Source mode (`default` / `--source`):

1. Uses `tests/docker/docker-compose.yml`.
2. Runs one setup pass before runtime streams.
3. Runs latest and previous WordPress streams with `SHIELD_SKIP_INNER_SETUP=1`.

Packaged modes (`test:package-targeted`, `test:package-full`, `analyze:package`):

1. Resolved through `php bin/shield` lane services.
2. Package path resolution supports explicit `--package-path` or deterministic temp package build.
3. Compatibility adapters map legacy flags to these commands.

## Static Analysis Entrypoints

Use the direct static-analysis runner when Docker routing is not required:

```bash
php bin/shield analyze:source
php bin/shield analyze:package
```

## Quick Examples

```bash
# Source runtime checks (default)
php bin/shield test:source

# Compatibility explicit source runtime checks
./bin/run-docker-tests.sh --source

# Package-targeted runtime checks
php bin/shield test:package-targeted

# Full-pathway packaged runtime mode
php bin/shield test:package-full

# Source static analysis
php bin/shield analyze:source

# Packaged static analysis
php bin/shield analyze:package
```

## Troubleshooting

1. Ensure Docker is installed and daemon is running.
2. Use `php bin/run-docker-tests.php --help` to verify mode flags.
3. If a mode fails immediately, check for unknown arguments and conflicting mode flags.
