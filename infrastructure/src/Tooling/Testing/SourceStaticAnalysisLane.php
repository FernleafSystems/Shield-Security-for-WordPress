<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class SourceStaticAnalysisLane {

	private ProcessRunner $processRunner;

	private SourceSetupCacheCoordinator $setupCacheCoordinator;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?SourceSetupCacheCoordinator $setupCacheCoordinator = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->setupCacheCoordinator = $setupCacheCoordinator ?? new SourceSetupCacheCoordinator();
	}

	public function run( string $rootDir, bool $refreshSetup = false ) :int {
		echo 'Mode: analyze-source'.\PHP_EOL;

		if ( $refreshSetup ) {
			echo 'Refreshing source setup cache state.'.\PHP_EOL;
			$this->setupCacheCoordinator->clearState( $rootDir );
		}

		$setup = $this->setupCacheCoordinator->evaluateAnalyzeSetup( $rootDir, $refreshSetup );
		if ( $setup[ 'needs_build_config' ] ) {
			echo 'Running build-config setup.'.\PHP_EOL;
			$buildCode = $this->processRunner->runForExitCode(
				[ \PHP_BINARY, Path::join( '.', 'bin', 'build-config.php' ) ],
				$rootDir
			);
			if ( $buildCode !== 0 ) {
				return $buildCode;
			}

			$this->setupCacheCoordinator->persistBuildConfigState( $rootDir, $setup[ 'fingerprint' ] );
		}
		else {
			echo 'Skipping build-config setup (cache hit).'.\PHP_EOL;
		}

		return $this->processRunner->runForExitCode(
			[
				\PHP_BINARY,
				Path::join( '.', 'vendor', 'phpstan', 'phpstan', 'phpstan' ),
				'analyse',
				'-c',
				Path::join( '.', 'phpstan.neon.dist' ),
				'--no-progress',
				'--memory-limit=1G',
			],
			$rootDir
		);
	}
}
