# Shield Testing Guide

This is the canonical operational testing guide for Shield.

For Docker-runner internals and environment variables, see [tests/docker/README.md](tests/docker/README.md).

## Documentation Ownership

1. `TESTING.md` is the canonical overall testing guide (commands, CI role split, verification flow).
2. `tests/docker/README.md` is Docker-runner-specific (modes, topology, environment variables, troubleshooting).

## Canonical Command Map

| Goal | Command | Notes |
|---|---|---|
| Fast local subset | `composer test:fast` | Rules-focused subset for rapid iteration |
| Full local test suite | `composer test` | Runs unit and integration suites |
| Unit tests only | `composer test:unit` | Includes config generation |
| Integration tests only | `composer test:integration` | Includes config generation |
| Source runtime (canonical) | `php bin/shield test:source` | Source-first working-tree Docker checks |
| Package-targeted runtime (canonical) | `php bin/shield test:package-targeted` | Focused package validation lane |
| Package-full runtime (canonical) | `php bin/shield test:package-full` | Full packaged Docker runtime lane |
| Source static analysis (canonical) | `php bin/shield analyze:source` | Build config + PHPStan on source |
| Packaged static analysis (canonical) | `php bin/shield analyze:package` | Packaged PHPStan via Docker |
| Source runtime (compat) | `./bin/run-docker-tests.sh --source` | Backwards-compatible adapter |
| Source static analysis (compat) | `php bin/run-static-analysis.php --source` | Backwards-compatible adapter |
| Source static analysis (composer) | `composer analyze` | Default maps to `analyze:source` |
| Packaged static analysis (composer) | `composer analyze:package` | Runs `php bin/shield analyze:package` |

## Docker Runner Modes

Primary CLI supports these commands:

| Command | Behavior |
|---|---|
| `php bin/shield test:source` | Source runtime checks against working tree |
| `php bin/shield test:package-targeted` | Focused package validation checks |
| `php bin/shield test:package-full` | Full packaged runtime checks |
| `php bin/shield analyze:source` | Source static analysis pathway |
| `php bin/shield analyze:package` | Packaged static analysis pathway |

Compatibility adapter `./bin/run-docker-tests.sh` supports these modes:

| Mode | Behavior |
|---|---|
| `(default)` | Source runtime checks against working tree |
| `--source` | Source runtime checks against working tree |
| `--package-targeted` | Focused package validation checks |
| `--package-full` | Full packaged runtime checks |
| `--analyze-source` | Source static analysis pathway |
| `--analyze-package` | Packaged static analysis pathway |

Source defaults are intentional:

1. Uses working-tree changes.
2. Does not implicitly run `composer package-plugin` in source mode.

## Static Analysis Entrypoints

`php bin/shield`:

1. `analyze:source`: source static analysis (`build-config` + PHPStan).
2. `analyze:package`: packaged static analysis via Docker.

Compatibility adapter `php bin/run-static-analysis.php`:

1. Default or `--source`: maps to `php bin/shield analyze:source`.
2. `--package`: maps to `php bin/shield analyze:package`.

Composer mapping:

1. `composer analyze` -> `composer analyze:source`
2. `composer analyze:package` remains explicit opt-in.

## CI Workflow Role Split

Required source-first gate: `.github/workflows/tests.yml`

1. Source static analysis (`composer analyze:source`).
2. Source Docker runtime checks.
3. Package-targeted validation against built artifact.

Scheduled/manual full packaged pathway: `.github/workflows/docker-tests.yml`

1. Full packaged matrix runtime checks.
2. Packaged static analysis.
3. Package playground smoke checks.
4. Triggered by `workflow_dispatch` and schedule.

## Schedule Semantics

For `.github/workflows/docker-tests.yml`:

1. Weekday schedule is `0 6 * * 1-5`.
2. This is 06:00 UTC Monday through Friday.
3. Scheduled runs execute regardless of code-change state.

## Local Verification Commands

Use these to verify command surface and docs alignment:

```bash
php bin/shield --help
php bin/run-docker-tests.php --help
php bin/run-static-analysis.php --help
composer run-script --list
```

## Operational Boundaries

1. Keep test validation focused on runtime, static analysis, and package correctness.
2. Do not add tests that assert documentation prose.
3. Ignore unrelated non-conflicting workspace changes while implementing testing-doc updates.
4. If conflicting changes are found in WP9 target files, stop and report before continuing.

## Related Files

1. [tests/docker/README.md](tests/docker/README.md) - Docker runner specifics.
2. [.github/workflows/tests.yml](.github/workflows/tests.yml) - Required source-first gate.
3. [.github/workflows/docker-tests.yml](.github/workflows/docker-tests.yml) - Scheduled/manual full packaged pathway.
4. [.github/config/matrix.conf](.github/config/matrix.conf) - PHP matrix and default PHP source of truth.
