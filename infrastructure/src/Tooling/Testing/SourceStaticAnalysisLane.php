<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class SourceStaticAnalysisLane {

	private const MAX_ANALYSIS_COMMAND_LENGTH = 6000;

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

		foreach ( $this->phpStanCommands( $command, $phpStanPaths ) as $phpStanCommand ) {
			$exitCode = $this->processRunner->runForExitCode( $phpStanCommand, $rootDir );
			if ( $exitCode !== 0 ) {
				return $exitCode;
			}
		}
		return 0;
	}

	/**
	 * Symfony's Windows process transport uses a command-line form with a much
	 * smaller practical limit than CreateProcess. Batch narrowed PHPStan paths
	 * so the pre-commit lane remains usable on large merges.
	 *
	 * @param string[] $command
	 * @param string[] $paths
	 * @return array<int,string[]>
	 */
	private function phpStanCommands( array $command, array $paths ) :array {
		if ( $paths === [] ) {
			return [ $command ];
		}

		$commands = [];
		$currentCommand = $command;
		$baseLength = \strlen( \implode( ' ', $command ) );
		$currentLength = $baseLength;
		foreach ( $paths as $path ) {
			$pathLength = \strlen( $path ) + 1;
			if ( \count( $currentCommand ) > \count( $command )
				&& $currentLength + $pathLength > self::MAX_ANALYSIS_COMMAND_LENGTH ) {
				$commands[] = $currentCommand;
				$currentCommand = $command;
				$currentLength = $baseLength;
			}
			$currentCommand[] = $path;
			$currentLength += $pathLength;
		}
		$commands[] = $currentCommand;
		return $commands;
	}
}
