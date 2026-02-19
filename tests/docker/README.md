# Docker Testing

Run the full CI-equivalent test suite locally with zero setup:

```bash
./bin/run-docker-tests.sh
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `PHP_VERSION` | from `matrix.conf` | PHP version to test against |
| `PHPUNIT_DEBUG` | auto | Explicitly force PHPUnit `--debug` on/off (`1`/`0`) |
| `SHIELD_TEST_VERBOSE` | `0` | Enable verbose Shield test bootstrap and force PHPUnit debug (`1`) |
| `DEBUG_MODE` | `false` | Enable verbose bash/process monitoring |

`PHPUNIT_DEBUG` is resolved in `bin/run-tests-docker.sh` in this order:
1. Explicit `PHPUNIT_DEBUG` value.
2. `SHIELD_TEST_VERBOSE=1` (forces debug on).
3. CI/GitHub Actions default to debug off.
4. Local default is debug on.

### Examples

```bash
# Normal run (debug enabled by default)
./bin/run-docker-tests.sh

# Suppress PHPUnit debug output
PHPUNIT_DEBUG=0 ./bin/run-docker-tests.sh

# Enable verbose Shield bootstrap output and PHPUnit debug
SHIELD_TEST_VERBOSE=1 ./bin/run-docker-tests.sh

# Test against a specific PHP version
PHP_VERSION=8.4 ./bin/run-docker-tests.sh
```

## Architecture

```
bin/run-docker-tests.sh          # Local orchestrator: builds assets, packages plugin, writes .env, runs compose
  -> tests/docker/.env           # Generated env vars (not committed)
  -> tests/docker/docker-compose.yml        # Base services: MySQL + test runners
  -> tests/docker/docker-compose.package.yml # Override: mounts built package
  -> bin/run-tests-docker.sh     # Runs inside Docker: installs WP test env, runs PHPUnit
```
