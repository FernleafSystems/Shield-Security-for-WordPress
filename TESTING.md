# Shield Testing Guide

This is the canonical operational testing guide for Shield.

For Docker-runner internals and environment variables, see [tests/docker/README.md](tests/docker/README.md).

## Canonical Command Map

| Goal | Command | Notes |
|---|---|---|
| Fast local subset | `composer test:fast` | Rules-focused subset for rapid iteration |
| Full local test suite | `composer test` | Runs unit and integration suites |
| Unit tests only | `composer test:unit` | Includes config generation |
| Integration tests only | `composer test:integration` | Includes config generation |
| Source runtime (default) | `./bin/run-docker-tests.sh` | Source-first working-tree Docker checks |
| Source runtime (explicit) | `./bin/run-docker-tests.sh --source` | Same behavior as default mode |
| Package-targeted runtime | `./bin/run-docker-tests.sh --package-targeted` | Packaged runtime checks |
| Package-full runtime | `./bin/run-docker-tests.sh --package-full` | Packaged runtime checks via full-pathway mode |
| Source static analysis | `composer analyze` | Default maps to `analyze:source` |
| Source static analysis (explicit) | `composer analyze:source` | Runs `php bin/run-static-analysis.php --source` |
| Packaged static analysis | `composer analyze:package` | Runs `php bin/run-static-analysis.php --package` |

## Docker Runner Modes

`./bin/run-docker-tests.sh` supports these modes:

| Mode | Behavior |
|---|---|
| `(default)` | Source runtime checks against working tree |
| `--source` | Source runtime checks against working tree |
| `--package-targeted` | Build package and run packaged runtime checks |
| `--package-full` | Build package and run packaged runtime checks |
| `--analyze-source` | Run source static analysis pathway |
| `--analyze-package` | Build package and run packaged static analysis |

Source defaults are intentional:

1. Uses working-tree changes.
2. Does not implicitly run `composer package-plugin` in source mode.

## Static Analysis Entrypoints

`php bin/run-static-analysis.php`:

1. Default or `--source`: source static analysis (`build-config` + PHPStan).
2. `--package`: packaged static analysis via `./bin/run-docker-tests.sh --analyze-package`.

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
