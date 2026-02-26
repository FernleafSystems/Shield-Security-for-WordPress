<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

class PackageTargetedTestLane {

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
		echo 'Mode: package-targeted'.\PHP_EOL;

		$resolvedPackagePath = $this->packagePathResolver->resolve( $rootDir, $packagePath );
		echo 'Using package path: '.$resolvedPackagePath.\PHP_EOL;

		$packagerConfig = $this->environmentResolver->resolvePackagerConfig( $rootDir );
		$env = [
			'SHIELD_PACKAGE_PATH' => $resolvedPackagePath,
		];
		if ( \is_string( $packagerConfig[ 'strauss_version' ] ) && $packagerConfig[ 'strauss_version' ] !== '' ) {
			$env[ 'SHIELD_STRAUSS_VERSION' ] = $packagerConfig[ 'strauss_version' ];
		}
		if ( \is_string( $packagerConfig[ 'strauss_fork_repo' ] ) && $packagerConfig[ 'strauss_fork_repo' ] !== '' ) {
			$env[ 'SHIELD_STRAUSS_FORK_REPO' ] = $packagerConfig[ 'strauss_fork_repo' ];
		}

		return $this->runInTemporaryEnv( $env, function () use ( $rootDir ) :int {
			$strictArgs = $this->strictSkipFailureArgs();
			$unitExitCode = $this->runCommand(
				\array_merge(
					[
					\PHP_BINARY,
					'./vendor/phpunit/phpunit/phpunit',
					'--no-configuration',
					'--no-coverage',
					'tests/Unit/PluginPackageValidationTest.php',
					],
					$strictArgs
				),
				$rootDir
			);
			if ( $unitExitCode !== 0 ) {
				return $unitExitCode;
			}

			return $this->runCommand(
				\array_merge(
					[
					\PHP_BINARY,
					'./vendor/phpunit/phpunit/phpunit',
					'--no-configuration',
					'--no-coverage',
					'--group',
					'package-targeted',
					'tests/Integration/Infrastructure/PluginPackagerStraussTest.php',
					],
					$strictArgs
				),
				$rootDir
			);
		} );
	}

	/**
	 * @param string[] $command
	 */
	private function runCommand( array $command, string $rootDir ) :int {
		return $this->processRunner->run( $command, $rootDir )->getExitCode() ?? 1;
	}

	/**
	 * Keep CI strictness while avoiding expected Windows-specific skips from failing local execution.
	 *
	 * @return string[]
	 */
	private function strictSkipFailureArgs() :array {
		return \PHP_OS_FAMILY === 'Windows' ? [] : [ '--fail-on-skipped' ];
	}

	/**
	 * @param array<string,string> $env
	 */
	private function runInTemporaryEnv( array $env, callable $callback ) :int {
		$previous = [];
		foreach ( $env as $key => $value ) {
			$oldValue = \getenv( $key );
			$previous[ $key ] = [
				'process' => \is_string( $oldValue ) ? $oldValue : null,
				'server_exists' => \array_key_exists( $key, $_SERVER ),
				'server_value' => $_SERVER[ $key ] ?? null,
				'env_exists' => \array_key_exists( $key, $_ENV ),
				'env_value' => $_ENV[ $key ] ?? null,
			];
			\putenv( $key.'='.$value );
			$_SERVER[ $key ] = $value;
			$_ENV[ $key ] = $value;
		}

		try {
			return (int)$callback();
		}
		finally {
			foreach ( $env as $key => $_ ) {
				$oldValue = $previous[ $key ] ?? null;
				if ( \is_array( $oldValue ) && \is_string( $oldValue[ 'process' ] ?? null ) ) {
					\putenv( $key.'='.(string)$oldValue[ 'process' ] );
				}
				else {
					\putenv( $key );
				}

				if ( \is_array( $oldValue ) && ( $oldValue[ 'server_exists' ] ?? false ) ) {
					$_SERVER[ $key ] = $oldValue[ 'server_value' ];
				}
				else {
					unset( $_SERVER[ $key ] );
				}

				if ( \is_array( $oldValue ) && ( $oldValue[ 'env_exists' ] ?? false ) ) {
					$_ENV[ $key ] = $oldValue[ 'env_value' ];
				}
				else {
					unset( $_ENV[ $key ] );
				}
			}
		}
	}
}
