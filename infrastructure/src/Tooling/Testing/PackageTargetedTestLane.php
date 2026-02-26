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

	public function run( string $rootDir, ?string $packagePath = null, ?bool $failOnSkipped = null ) :int {
		echo 'Mode: package-targeted'.\PHP_EOL;

		$resolvedPackagePath = $this->packagePathResolver->resolve( $rootDir, $packagePath );
		echo 'Using package path: '.$resolvedPackagePath.\PHP_EOL;

		$packagerConfig = $this->environmentResolver->resolvePackagerConfig( $rootDir );
		$envOverrides = $this->buildProcessEnvOverrides( $resolvedPackagePath, $packagerConfig );
		$strictSkipArgs = $this->resolveStrictSkipArgs( $failOnSkipped );

		$unitExitCode = $this->runCommand(
			$this->buildUnitValidationCommand( $strictSkipArgs ),
			$rootDir,
			$envOverrides
		);
		if ( $unitExitCode !== 0 ) {
			return $unitExitCode;
		}

		return $this->runCommand(
			$this->buildIntegrationValidationCommand( $strictSkipArgs ),
			$rootDir,
			$envOverrides
		);
	}

	/**
	 * @param string[] $command
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function runCommand( array $command, string $rootDir, ?array $envOverrides = null ) :int {
		return $this->processRunner->run( $command, $rootDir, null, $envOverrides )->getExitCode() ?? 1;
	}

	/**
	 * @return string[]
	 */
	private function resolveStrictSkipArgs( ?bool $failOnSkipped ) :array {
		if ( $failOnSkipped === true ) {
			return [ '--fail-on-skipped' ];
		}
		if ( $failOnSkipped === false ) {
			return [];
		}

		return \PHP_OS_FAMILY === 'Windows' ? [] : [ '--fail-on-skipped' ];
	}

	/**
	 * @return string[]
	 */
	private function buildUnitValidationCommand( array $strictSkipArgs ) :array {
		return \array_merge(
			[
				\PHP_BINARY,
				'./vendor/phpunit/phpunit/phpunit',
				'--no-configuration',
				'--no-coverage',
				'tests/Unit/PluginPackageValidationTest.php',
			],
			$strictSkipArgs
		);
	}

	/**
	 * @param string[] $strictSkipArgs
	 * @return string[]
	 */
	private function buildIntegrationValidationCommand( array $strictSkipArgs ) :array {
		return \array_merge(
			[
				\PHP_BINARY,
				'./vendor/phpunit/phpunit/phpunit',
				'--no-configuration',
				'--no-coverage',
				'--group',
				'package-targeted',
				'tests/Integration/Infrastructure/PluginPackagerStraussTest.php',
			],
			$strictSkipArgs
		);
	}

	/**
	 * @param array{strauss_version:?string,strauss_fork_repo:?string} $packagerConfig
	 * @return array<string,string>
	 */
	private function buildProcessEnvOverrides( string $resolvedPackagePath, array $packagerConfig ) :array {
		$env = [
			'SHIELD_PACKAGE_PATH' => $resolvedPackagePath,
		];
		if ( \is_string( $packagerConfig[ 'strauss_version' ] ) && $packagerConfig[ 'strauss_version' ] !== '' ) {
			$env[ 'SHIELD_STRAUSS_VERSION' ] = $packagerConfig[ 'strauss_version' ];
		}
		if ( \is_string( $packagerConfig[ 'strauss_fork_repo' ] ) && $packagerConfig[ 'strauss_fork_repo' ] !== '' ) {
			$env[ 'SHIELD_STRAUSS_FORK_REPO' ] = $packagerConfig[ 'strauss_fork_repo' ];
		}

		return $env;
	}
}
