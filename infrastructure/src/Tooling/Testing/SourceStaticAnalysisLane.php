<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class SourceStaticAnalysisLane {

	/**
	 * Path characters per PHPStan run, counting a space and quotes per path.
	 */
	private const MAX_PATH_CHARS_PER_RUN = 6000;

	private ProcessRunner $processRunner;

	private SourceSetupCacheCoordinator $setupCacheCoordinator;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?SourceSetupCacheCoordinator $setupCacheCoordinator = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->setupCacheCoordinator = $setupCacheCoordinator ?? new SourceSetupCacheCoordinator();
	}

	/**
	 * @param string[] $phpStanPaths
	 */
	public function run( string $rootDir, bool $refreshSetup = false, array $phpStanPaths = [] ) :int {
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

		$command = [
			\PHP_BINARY,
			Path::join( '.', 'vendor', 'phpstan', 'phpstan', 'phpstan' ),
			'analyse',
			'-c',
			Path::join( '.', 'phpstan.neon.dist' ),
			'--no-progress',
			'--memory-limit=2G',
		];

		foreach ( $this->pathBatches( $phpStanPaths ) as $batch ) {
			$exitCode = $this->processRunner->runForExitCode( \array_merge( $command, $batch ), $rootDir );
			if ( $exitCode !== 0 ) {
				return $exitCode;
			}
		}
		return 0;
	}

	/**
	 * Windows runs commands through cmd.exe, which refuses a command line over 8191 characters without any output.
	 * Explicit paths are therefore analysed in batches short enough to leave room for the rest of the command.
	 * No paths means one run over the configured paths.
	 * @param string[] $paths
	 * @return string[][]
	 */
	private function pathBatches( array $paths ) :array {
		$batches = [];
		$batch = [];
		$length = 0;
		foreach ( $paths as $path ) {
			$pathLength = \strlen( $path ) + 3;
			if ( !empty( $batch ) && $length + $pathLength > self::MAX_PATH_CHARS_PER_RUN ) {
				$batches[] = $batch;
				$batch = [];
				$length = 0;
			}
			$batch[] = $path;
			$length += $pathLength;
		}
		$batches[] = $batch;
		return $batches;
	}
}
