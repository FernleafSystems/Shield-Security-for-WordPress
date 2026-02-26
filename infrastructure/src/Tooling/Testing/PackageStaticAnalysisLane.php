<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanAnalysisOrchestrator;
use Symfony\Component\Filesystem\Path;

class PackageStaticAnalysisLane {

	private PackagePathResolver $packagePathResolver;

	private TestingEnvironmentResolver $environmentResolver;

	private PackagedPhpStanAnalysisOrchestrator $orchestrator;

	public function __construct(
		?PackagePathResolver $packagePathResolver = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?PackagedPhpStanAnalysisOrchestrator $orchestrator = null
	) {
		$this->packagePathResolver = $packagePathResolver ?? new PackagePathResolver();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver();
		$this->orchestrator = $orchestrator ?? new PackagedPhpStanAnalysisOrchestrator();
	}

	public function run( string $rootDir, ?string $packagePath = null ) :int {
		echo 'Mode: analyze-package'.\PHP_EOL;

		$this->environmentResolver->assertDockerReady( $rootDir );
		$resolvedPackagePath = $this->packagePathResolver->resolve( $rootDir, $packagePath );
		$this->orchestrator->assertPreflight( $rootDir, $resolvedPackagePath );

		$composerImage = \trim( (string)( \getenv( 'SHIELD_COMPOSER_IMAGE' ) ?: 'composer:2' ) );
		echo 'Running PHPStan against packaged plugin...'.\PHP_EOL;
		echo '   Using config: /app/phpstan.package.neon.dist'.\PHP_EOL;
		echo '   Using package path: '.$resolvedPackagePath.\PHP_EOL;
		echo '   Using composer image: '.$composerImage.\PHP_EOL;

		$outcome = $this->orchestrator->runCommand(
			$this->buildDockerCommand( $rootDir, $resolvedPackagePath, $composerImage ),
			$rootDir
		);

		echo $outcome->toConsoleMessage().\PHP_EOL;
		return $outcome->toExitCode();
	}

	/**
	 * @return string[]
	 */
	private function buildDockerCommand( string $rootDir, string $packagePath, string $composerImage ) :array {
		$relativePath = Path::makeRelative( $packagePath, $rootDir );
		if ( $relativePath !== '' && $relativePath[ 0 ] !== '.' ) {
			return $this->orchestrator->buildDockerCommand( $rootDir, $composerImage, $relativePath );
		}

		return [
			'docker',
			'run',
			'--rm',
			'--name',
			'shield-phpstan-package',
			'-v',
			$rootDir.':/app',
			'-v',
			$packagePath.':/shield-package',
			'-w',
			'/app',
			'-e',
			'SHIELD_PACKAGE_PATH=/shield-package',
			$composerImage,
			'php',
			'/app/vendor/phpstan/phpstan/phpstan',
			'analyse',
			'-c',
			'/app/phpstan.package.neon.dist',
			'--error-format=json',
			'--no-progress',
			'--memory-limit=1G',
		];
	}
}
