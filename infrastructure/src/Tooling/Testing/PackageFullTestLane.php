<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class PackageFullTestLane {

	private ProcessRunner $processRunner;

	private PackagePathResolver $packagePathResolver;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?PackagePathResolver $packagePathResolver = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->packagePathResolver = $packagePathResolver ?? new PackagePathResolver( $this->processRunner );
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver(
			$this->processRunner
		);
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
	}

	public function run( string $rootDir, ?string $packagePath = null ) :int {
		echo 'Mode: package-full'.\PHP_EOL;

		$this->environmentResolver->assertDockerReady( $rootDir );
		$resolvedPackagePath = $this->packagePathResolver->resolve( $rootDir, $packagePath );
		echo 'Using package path: '.$resolvedPackagePath.\PHP_EOL;

		$phpVersion = $this->environmentResolver->resolvePhpVersion( $rootDir );
		[ $latestWpVersion, $previousWpVersion ] = $this->environmentResolver->detectWordpressVersions( $rootDir );
		$packagerConfig = $this->environmentResolver->resolvePackagerConfig( $rootDir );

		$dockerEnvPath = Path::join( $rootDir, 'tests', 'docker', '.env' );
		$this->environmentResolver->writeDockerEnvFile(
			$dockerEnvPath,
			$this->buildDockerEnvLines(
				$phpVersion,
				$latestWpVersion,
				$previousWpVersion,
				$resolvedPackagePath,
				$packagerConfig
			)
		);

		$composeFiles = $this->buildComposeFiles();
		$dockerProcessEnvOverrides = $this->buildDockerProcessEnvOverrides();

		try {
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

			$overallExitCode = 0;
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

	/**
	 * @return string[]
	 */
	private function buildComposeFiles() :array {
		return [
			'tests/docker/docker-compose.yml',
			'tests/docker/docker-compose.package.yml',
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function buildDockerProcessEnvOverrides() :array {
		return [
			'DOCKER_BUILDKIT' => '1',
			'MSYS_NO_PATHCONV' => '1',
			'COMPOSE_PROJECT_NAME' => 'shield-tests',
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
		return [ 'run', '--rm', 'test-runner-latest' ];
	}

	/**
	 * @return string[]
	 */
	private function buildComposeRunPreviousCommand() :array {
		return [ 'run', '--rm', 'test-runner-previous' ];
	}

	/**
	 * @param array{strauss_version:?string,strauss_fork_repo:?string} $packagerConfig
	 * @return string[]
	 */
	private function buildDockerEnvLines(
		string $phpVersion,
		string $latestWpVersion,
		string $previousWpVersion,
		string $packagePath,
		array $packagerConfig
	) :array {
		$lines = [
			'PHP_VERSION='.$phpVersion,
			'WP_VERSION_LATEST='.$latestWpVersion,
			'WP_VERSION_PREVIOUS='.$previousWpVersion,
			'TEST_PHP_VERSION='.$phpVersion,
			'PLUGIN_SOURCE='.$packagePath,
			'SHIELD_PACKAGE_PATH='.$packagePath,
			'SHIELD_TEST_MODE=package-full',
		];

		if ( \is_string( $packagerConfig[ 'strauss_version' ] ) && $packagerConfig[ 'strauss_version' ] !== '' ) {
			$lines[] = 'SHIELD_STRAUSS_VERSION='.$packagerConfig[ 'strauss_version' ];
		}
		if ( \is_string( $packagerConfig[ 'strauss_fork_repo' ] ) && $packagerConfig[ 'strauss_fork_repo' ] !== '' ) {
			$lines[] = 'SHIELD_STRAUSS_FORK_REPO='.$packagerConfig[ 'strauss_fork_repo' ];
		}
		foreach ( [ 'PHPUNIT_DEBUG', 'SHIELD_TEST_VERBOSE' ] as $optionalEnvVar ) {
			$value = \getenv( $optionalEnvVar );
			if ( \is_string( $value ) && $value !== '' ) {
				$lines[] = $optionalEnvVar.'='.$value;
			}
		}

		return $lines;
	}
}
