<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class SourceStaticAnalysisLane {

	private ProcessRunner $processRunner;

	public function __construct( ?ProcessRunner $processRunner = null ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
	}

	public function run( string $rootDir ) :int {
		echo 'Mode: analyze-source'.\PHP_EOL;

		$buildCode = $this->runCommand(
			[ \PHP_BINARY, Path::join( '.', 'bin', 'build-config.php' ) ],
			$rootDir
		);
		if ( $buildCode !== 0 ) {
			return $buildCode;
		}

		return $this->runCommand(
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

	/**
	 * @param string[] $command
	 */
	private function runCommand( array $command, string $rootDir ) :int {
		return $this->processRunner->run( $command, $rootDir )->getExitCode() ?? 1;
	}
}
