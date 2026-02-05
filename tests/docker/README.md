# Docker Testing

Run the full CI-equivalent test suite locally with zero setup:

```bash
./bin/run-docker-tests.sh
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `PHP_VERSION` | from `matrix.conf` | PHP version to test against |
| `PHPUNIT_DEBUG` | `1` | Set to `0` to disable PHPUnit `--debug` output |
| `DEBUG_MODE` | `false` | Enable verbose bash/process monitoring |

The `PHPUNIT_DEBUG` default is controlled in one place: `bin/run-tests-docker.sh`. The value flows through the `.env` file and docker-compose into the container automatically.

### Examples

```bash
# Normal run (debug enabled by default)
./bin/run-docker-tests.sh

# Suppress PHPUnit debug output
PHPUNIT_DEBUG=0 ./bin/run-docker-tests.sh

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
