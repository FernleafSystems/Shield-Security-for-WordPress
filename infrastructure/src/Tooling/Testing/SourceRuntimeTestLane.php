<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class SourceRuntimeTestLane {

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver(
			$this->processRunner
		);
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
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

			$composeFiles = $this->buildComposeFiles();
			$dockerProcessEnvOverrides = $this->buildDockerProcessEnvOverrides();
			$overallExitCode = 0;
			try {
				echo 'Starting source-runtime Docker checks on working tree.'.\PHP_EOL;
				$this->dockerComposeExecutor->runIgnoringFailure(
					$rootDir,
					$composeFiles,
					$this->buildComposeCleanupCommand(),
					$dockerProcessEnvOverrides
				);

				if ( $this->dockerComposeExecutor->run(
					$rootDir,
					$composeFiles,
					$this->buildComposeMysqlUpCommand(),
					$dockerProcessEnvOverrides
				) !== 0 ) {
					return 1;
				}
				if ( $this->dockerComposeExecutor->run(
					$rootDir,
					$composeFiles,
					$this->buildComposeBuildRunnersCommand(),
					$dockerProcessEnvOverrides
				) !== 0 ) {
					return 1;
				}
				if ( $this->runSourceSetupOnce( $rootDir, $dockerProcessEnvOverrides ) !== 0 ) {
					return 1;
				}

				if ( $this->dockerComposeExecutor->run(
					$rootDir,
					$composeFiles,
					$this->buildComposeRunLatestCommand(),
					$dockerProcessEnvOverrides
				) !== 0 ) {
					$overallExitCode = 1;
				}
				if ( $this->dockerComposeExecutor->run(
					$rootDir,
					$composeFiles,
					$this->buildComposeRunPreviousCommand(),
					$dockerProcessEnvOverrides
				) !== 0 ) {
					$overallExitCode = 1;
				}

				return $overallExitCode;
			}
			finally {
				$this->dockerComposeExecutor->runIgnoringFailure(
					$rootDir,
					$composeFiles,
					$this->buildComposeCleanupCommand(),
					$dockerProcessEnvOverrides
				);
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
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function runSourceSetupOnce( string $rootDir, ?array $envOverrides = null ) :int {
		echo 'Preparing source mode test setup once before runtime checks.'.\PHP_EOL;

		$composeFiles = $this->buildComposeFiles();
		foreach ( $this->buildSourceSetupComposeCommands() as $subCommand ) {
			if ( $this->dockerComposeExecutor->run(
				$rootDir,
				$composeFiles,
				$subCommand,
				$envOverrides
			) !== 0 ) {
				return 1;
			}
		}

		$nodeProcess = $this->processRunner->run(
			$this->buildNodeAssetBuildCommand( $rootDir ),
			$rootDir,
			null,
			$envOverrides
		);

		return $nodeProcess->getExitCode() ?? 1;
	}

	/**
	 * @return string[]
	 */
	private function buildComposeFiles() :array {
		return [
			'tests/docker/docker-compose.yml',
		];
	}

	/**
	 * @return array<string,string|false>
	 */
	private function buildDockerProcessEnvOverrides() :array {
		return [
			'DOCKER_BUILDKIT' => '1',
			'MSYS_NO_PATHCONV' => '1',
			'COMPOSE_PROJECT_NAME' => 'shield-tests',
			'SHIELD_PACKAGE_PATH' => false,
		];
	}

	/**
	 * @return string[]
	 */
	private function buildComposeCleanupCommand() :array {
		return [ 'down', '-v', '--remove-orphans' ];
	}

	/**
	 * @return string[]
	 */
	private function buildComposeMysqlUpCommand() :array {
		return [ 'up', '-d', 'mysql-latest', 'mysql-previous' ];
	}

	/**
	 * @return string[]
	 */
	private function buildComposeBuildRunnersCommand() :array {
		return [ 'build', 'test-runner-latest', 'test-runner-previous' ];
	}

	/**
	 * @return string[]
	 */
	private function buildComposeRunLatestCommand() :array {
		return [ 'run', '--rm', '-e', 'SHIELD_SKIP_INNER_SETUP=1', 'test-runner-latest' ];
	}

	/**
	 * @return string[]
	 */
	private function buildComposeRunPreviousCommand() :array {
		return [ 'run', '--rm', '-e', 'SHIELD_SKIP_INNER_SETUP=1', 'test-runner-previous' ];
	}

	/**
	 * @return array<int,array<int,string>>
	 */
	private function buildSourceSetupComposeCommands() :array {
		return [
			[ 'run', '--rm', '--no-deps', 'test-runner-latest', 'composer', 'install', '--no-interaction', '--no-cache' ],
			[ 'run', '--rm', '--no-deps', 'test-runner-latest', 'composer', 'build:config' ],
		];
	}

	/**
	 * @return string[]
	 */
	private function buildNodeAssetBuildCommand( string $rootDir ) :array {
		return [
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
		];
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
