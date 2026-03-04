<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class SourceRuntimeTestLane {

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private SourceSetupCacheCoordinator $setupCacheCoordinator;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?SourceSetupCacheCoordinator $setupCacheCoordinator = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver(
			$this->processRunner
		);
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->setupCacheCoordinator = $setupCacheCoordinator ?? new SourceSetupCacheCoordinator();
	}

	public function run( string $rootDir, bool $refreshSetup = false ) :int {
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
			$dockerProcessEnvOverrides = $this->environmentResolver->buildDockerProcessEnvOverrides(
				'shield-tests',
				true
			);
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
				if ( $this->runSourceSetupOnce( $rootDir, $phpVersion, $refreshSetup, $dockerProcessEnvOverrides ) !== 0 ) {
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
	private function runSourceSetupOnce(
		string $rootDir,
		string $phpVersion,
		bool $refreshSetup = false,
		?array $envOverrides = null
	) :int {
		echo 'Preparing source mode test setup once before runtime checks.'.\PHP_EOL;

		if ( $refreshSetup ) {
			echo 'Refreshing source setup cache state.'.\PHP_EOL;
			$this->setupCacheCoordinator->clearState( $rootDir );
			$this->purgeNodeModulesVolume(
				$rootDir,
				$this->setupCacheCoordinator->getNodeModulesVolumeName( $rootDir ),
				$envOverrides
			);
		}

		$setup = $this->setupCacheCoordinator->evaluateRuntimeSetup( $rootDir, $phpVersion, $refreshSetup );
		$composeFiles = $this->buildComposeFiles();

		if ( $setup[ 'needs_composer_install' ] ) {
			echo 'Running source composer install setup.'.\PHP_EOL;
			if ( $this->dockerComposeExecutor->run(
				$rootDir,
				$composeFiles,
				$this->buildSourceComposerInstallSetupCommand(),
				$envOverrides
			) !== 0 ) {
				return 1;
			}
		}
		else {
			echo 'Skipping composer install setup (cache hit).'.\PHP_EOL;
		}

		if ( $setup[ 'needs_build_config' ] ) {
			echo 'Running source build-config setup.'.\PHP_EOL;
			if ( $this->dockerComposeExecutor->run(
				$rootDir,
				$composeFiles,
				$this->buildSourceBuildConfigSetupCommand(),
				$envOverrides
			) !== 0 ) {
				return 1;
			}
		}
		else {
			echo 'Skipping build-config setup (cache hit).'.\PHP_EOL;
		}

		if ( $setup[ 'needs_npm_install' ] ) {
			echo 'Running node dependency install and asset build.'.\PHP_EOL;
			$nodeExitCode = $this->processRunner->runForExitCode(
				$this->buildNodeAssetBuildCommand( $rootDir, $setup[ 'node_modules_volume' ], true ),
				$rootDir,
				null,
				$envOverrides
			);
			if ( $nodeExitCode !== 0 ) {
				return $nodeExitCode;
			}
		}
		elseif ( $setup[ 'needs_npm_build' ] ) {
			echo 'Running asset build only.'.\PHP_EOL;
			$nodeExitCode = $this->processRunner->runForExitCode(
				$this->buildNodeAssetBuildCommand( $rootDir, $setup[ 'node_modules_volume' ], false ),
				$rootDir,
				null,
				$envOverrides
			);
			if ( $nodeExitCode !== 0 ) {
				return $nodeExitCode;
			}
		}
		else {
			echo 'Skipping node install/build setup (cache hit).'.\PHP_EOL;
		}

		$this->setupCacheCoordinator->persistRuntimeSetupState( $rootDir, $setup[ 'fingerprints' ] );
		return 0;
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
	 * @return string[]
	 */
	private function buildSourceComposerInstallSetupCommand() :array {
		return [ 'run', '--rm', '--no-deps', 'test-runner-latest', 'composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress' ];
	}

	/**
	 * @return string[]
	 */
	private function buildSourceBuildConfigSetupCommand() :array {
		return [ 'run', '--rm', '--no-deps', 'test-runner-latest', 'composer', 'build:config' ];
	}

	/**
	 * @return string[]
	 */
	private function buildNodeAssetBuildCommand(
		string $rootDir,
		string $nodeModulesVolume,
		bool $installDependencies
	) :array {
		$command = $installDependencies
			? 'npm ci --no-audit --no-fund && npm run build'
			: 'npm run build';

		return [
			'docker',
			'run',
			'--rm',
			'-v',
			$rootDir.':/app',
			'-v',
			$nodeModulesVolume.':/app/node_modules',
			'-w',
			'/app',
			$this->setupCacheCoordinator->getNodeImageTag(),
			'sh',
			'-c',
			$command,
		];
	}

	/**
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function purgeNodeModulesVolume(
		string $rootDir,
		string $nodeModulesVolume,
		?array $envOverrides = null
	) :void {
		$this->processRunner->run(
			[
				'docker',
				'volume',
				'rm',
				'-f',
				$nodeModulesVolume,
			],
			$rootDir,
			static function () :void {
			},
			$envOverrides
		);
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
