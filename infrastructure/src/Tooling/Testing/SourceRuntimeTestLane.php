<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class SourceRuntimeTestLane {

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver(
			$this->processRunner
		);
	}

	public function run( string $rootDir ) :int {
		echo 'Mode: source'.\PHP_EOL;

		$originalShieldPackagePath = \getenv( 'SHIELD_PACKAGE_PATH' );
		$hasOriginalShieldPackagePath = \is_string( $originalShieldPackagePath );
		\putenv( 'SHIELD_PACKAGE_PATH' );

		try {
			$this->environmentResolver->assertDockerReady( $rootDir );

			$phpVersion = $this->environmentResolver->resolvePhpVersion( $rootDir );
			[ $latestWpVersion, $previousWpVersion ] = $this->environmentResolver->detectWordpressVersions( $rootDir );

			$dockerEnvPath = Path::join( $rootDir, 'tests', 'docker', '.env' );
			$this->environmentResolver->writeDockerEnvFile(
				$dockerEnvPath,
				$this->buildDockerEnvLines( $phpVersion, $latestWpVersion, $previousWpVersion )
			);

			\putenv( 'DOCKER_BUILDKIT=1' );
			\putenv( 'MSYS_NO_PATHCONV=1' );
			\putenv( 'COMPOSE_PROJECT_NAME=shield-tests' );

			$composeArgs = [
				'docker',
				'compose',
				'-f',
				'tests/docker/docker-compose.yml',
			];

			$runCompose = function ( array $subCommand ) use ( $rootDir, $composeArgs ) :int {
				$command = \array_merge( $composeArgs, $subCommand );
				return $this->processRunner->run( $command, $rootDir )->getExitCode() ?? 1;
			};
			$runComposeIgnoringFailure = function ( array $subCommand ) use ( $rootDir, $composeArgs ) :void {
				$command = \array_merge( $composeArgs, $subCommand );
				$this->processRunner->run(
					$command,
					$rootDir,
					static function ( string $type, string $buffer ) :void {
						if ( $type === Process::ERR ) {
							\fwrite( \STDERR, $buffer );
						}
						else {
							echo $buffer;
						}
					}
				);
			};

			$overallExitCode = 0;
			try {
				echo 'Starting source-runtime Docker checks on working tree.'.\PHP_EOL;
				$runComposeIgnoringFailure( [ 'down', '-v', '--remove-orphans' ] );

				if ( $runCompose( [ 'up', '-d', 'mysql-latest', 'mysql-previous' ] ) !== 0 ) {
					return 1;
				}
				if ( $runCompose( [ 'build', 'test-runner-latest', 'test-runner-previous' ] ) !== 0 ) {
					return 1;
				}
				if ( $this->runSourceSetupOnce( $rootDir, $composeArgs ) !== 0 ) {
					return 1;
				}

				if ( $runCompose( [ 'run', '--rm', '-e', 'SHIELD_SKIP_INNER_SETUP=1', 'test-runner-latest' ] ) !== 0 ) {
					$overallExitCode = 1;
				}
				if ( $runCompose( [ 'run', '--rm', '-e', 'SHIELD_SKIP_INNER_SETUP=1', 'test-runner-previous' ] ) !== 0 ) {
					$overallExitCode = 1;
				}

				return $overallExitCode;
			}
			finally {
				$runComposeIgnoringFailure( [ 'down', '-v', '--remove-orphans' ] );
				if ( \is_file( $dockerEnvPath ) ) {
					\unlink( $dockerEnvPath );
				}
			}
		}
		finally {
			if ( $hasOriginalShieldPackagePath ) {
				\putenv( 'SHIELD_PACKAGE_PATH='.$originalShieldPackagePath );
			}
			else {
				\putenv( 'SHIELD_PACKAGE_PATH' );
			}
		}
	}

	/**
	 * @param string[] $composeArgs
	 */
	private function runSourceSetupOnce( string $rootDir, array $composeArgs ) :int {
		echo 'Preparing source mode test setup once before runtime checks.'.\PHP_EOL;

		$runCompose = function ( array $subCommand ) use ( $rootDir, $composeArgs ) :int {
			$command = \array_merge( $composeArgs, $subCommand );
			return $this->processRunner->run( $command, $rootDir )->getExitCode() ?? 1;
		};

		if ( $runCompose( [ 'run', '--rm', '--no-deps', 'test-runner-latest', 'composer', 'install', '--no-interaction', '--no-cache' ] ) !== 0 ) {
			return 1;
		}
		if ( $runCompose( [ 'run', '--rm', '--no-deps', 'test-runner-latest', 'composer', 'build:config' ] ) !== 0 ) {
			return 1;
		}

		$nodeProcess = $this->processRunner->run(
			[
				'docker',
				'run',
				'--rm',
				'-v',
				$rootDir.':/app',
				'-v',
				'/app/node_modules',
				'-w',
				'/app',
				'node:20.10',
				'sh',
				'-c',
				'npm ci --no-audit --no-fund && npm run build',
			],
			$rootDir
		);

		return $nodeProcess->getExitCode() ?? 1;
	}

	/**
	 * @return string[]
	 */
	private function buildDockerEnvLines( string $phpVersion, string $latestWpVersion, string $previousWpVersion ) :array {
		$lines = [
			'PHP_VERSION='.$phpVersion,
			'WP_VERSION_LATEST='.$latestWpVersion,
			'WP_VERSION_PREVIOUS='.$previousWpVersion,
			'TEST_PHP_VERSION='.$phpVersion,
			'SHIELD_TEST_MODE=source',
		];

		foreach ( [ 'PHPUNIT_DEBUG', 'SHIELD_TEST_VERBOSE' ] as $optionalEnvVar ) {
			$value = \getenv( $optionalEnvVar );
			if ( \is_string( $value ) && $value !== '' ) {
				$lines[] = $optionalEnvVar.'='.$value;
			}
		}

		return $lines;
	}
}
