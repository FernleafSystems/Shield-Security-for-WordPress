<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class PackageFullTestLane {

	private ProcessRunner $processRunner;

	private PackagePathResolver $packagePathResolver;

	private TestingEnvironmentResolver $environmentResolver;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?PackagePathResolver $packagePathResolver = null,
		?TestingEnvironmentResolver $environmentResolver = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->packagePathResolver = $packagePathResolver ?? new PackagePathResolver( $this->processRunner );
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver(
			$this->processRunner
		);
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

		\putenv( 'DOCKER_BUILDKIT=1' );
		\putenv( 'MSYS_NO_PATHCONV=1' );
		\putenv( 'COMPOSE_PROJECT_NAME=shield-tests' );

		$composeArgs = [
			'docker',
			'compose',
			'-f',
			'tests/docker/docker-compose.yml',
			'-f',
			'tests/docker/docker-compose.package.yml',
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

		try {
			$runComposeIgnoringFailure( [ 'down', '-v', '--remove-orphans' ] );

			if ( $runCompose( [ 'up', '-d', 'mysql-latest', 'mysql-previous' ] ) !== 0 ) {
				return 1;
			}
			if ( $runCompose( [ 'build', 'test-runner-latest', 'test-runner-previous' ] ) !== 0 ) {
				return 1;
			}

			$overallExitCode = 0;
			if ( $runCompose( [ 'run', '--rm', 'test-runner-latest' ] ) !== 0 ) {
				$overallExitCode = 1;
			}
			if ( $runCompose( [ 'run', '--rm', 'test-runner-previous' ] ) !== 0 ) {
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
