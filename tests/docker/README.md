# Docker Test Runner

This document covers Docker-runner specifics only.

For canonical testing commands, CI role split, and overall testing guidance, see [TESTING.md](../../TESTING.md).

## Documentation Ownership

1. `TESTING.md` owns canonical testing commands and workflow policy.
2. `tests/docker/README.md` owns Docker runner behavior, environment variables, and troubleshooting.

## Entry Point

```bash
./bin/run-docker-tests.sh
```

The shell script is a thin delegator to `bin/run-docker-tests.php`.

## Modes

| Mode | Behavior | Typical Use |
|---|---|---|
| `(default)` | Source runtime checks against working tree | Daily local CI-like runtime checks |
| `--source` | Source runtime checks against working tree | Explicit source-mode invocation |
| `--package-targeted` | Build package and run packaged runtime checks | Package-targeted runtime validation |
| `--package-full` | Build package and run full packaged pathway | Full-pathway package runtime mode |
| `--analyze-source` | Run source static analysis pathway | Source static analysis from runner |
| `--analyze-package` | Build package and run packaged static analysis | Packaged static analysis |

Show live help at any time:

```bash
php bin/run-docker-tests.php --help
```

## Environment Variables

| Variable | Default | Purpose |
|---|---|---|
| `PHP_VERSION` | from `.github/config/matrix.conf` (`DEFAULT_PHP`) | Select PHP version used by runner |
| `PHPUNIT_DEBUG` | auto-resolved | Force PHPUnit debug on/off (`1` or `0`) |
| `SHIELD_TEST_VERBOSE` | `0` | Canonical verbose flag; enables debug behavior |
| `SHIELD_DEBUG` / `SHIELD_DEBUG_PATHS` | unset | Legacy verbose aliases |
| `DEBUG_MODE` | `false` | Extra bash/process monitoring in packaged legacy path |

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

Packaged modes (`--package-targeted`, `--package-full`, `--analyze-package`):

1. Routed through `bin/run-docker-tests.legacy.sh` by `bin/run-docker-tests.php`.
2. Used for packaged runtime/static analysis pathways.
3. `--package-targeted` and `--package-full` are distinct lane selectors, but currently share the same packaged legacy implementation path.

## Static Analysis Entrypoints

Use the direct static-analysis runner when Docker routing is not required:

```bash
php bin/run-static-analysis.php --source
php bin/run-static-analysis.php --package
```

## Quick Examples

```bash
# Source runtime checks (default)
./bin/run-docker-tests.sh

# Explicit source runtime checks
./bin/run-docker-tests.sh --source

# Package-targeted runtime checks
./bin/run-docker-tests.sh --package-targeted

# Full-pathway packaged runtime mode
./bin/run-docker-tests.sh --package-full

# Source static analysis via Docker runner command surface
./bin/run-docker-tests.sh --analyze-source

# Packaged static analysis
./bin/run-docker-tests.sh --analyze-package
```

## Troubleshooting

1. Ensure Docker is installed and daemon is running.
2. Use `php bin/run-docker-tests.php --help` to verify mode flags.
3. If a mode fails immediately, check for unknown arguments and conflicting mode flags.
