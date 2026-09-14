<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;

class CrossSiteTestLane {

	private const LOCK_DIR = 'tmp/cross-site-test-lane';
	private const LOCK_FILE = 'lane.lock';

	private CrossSitePairManager $pairManager;

	public function __construct( ?CrossSitePairManager $pairManager = null ) {
		$this->pairManager = $pairManager ?? new CrossSitePairManager();
	}

	/**
	 * @param array{show_setup_output?:bool} $options
	 */
	public function run( string $rootDir, array $options = [] ) :int {
		$showSetupOutput = (bool)( $options[ 'show_setup_output' ] ?? false );

		try {
			$exitCode = $this->withLock(
				$rootDir,
				function () use ( $rootDir, $showSetupOutput ) :int {
					$scenarioFailure = null;
					try {
						$this->pairManager->prepare( $rootDir, $showSetupOutput );
						$this->pairManager->preparePublicRuntimeScenario( $rootDir, $showSetupOutput );
						$this->pairManager->runPublicUpgradeScenario( $rootDir );
						$this->pairManager->prepareCurrentRuntimeScenario( $rootDir, $showSetupOutput );
						$this->pairManager->runImportExportScenario( $rootDir );
						return 0;
					}
					catch ( \Throwable $throwable ) {
						$scenarioFailure = $throwable;
						throw $throwable;
					}
					finally {
						try {
							$this->pairManager->cleanupRun( $rootDir );
						}
						catch ( \Throwable $cleanupFailure ) {
							if ( $scenarioFailure === null ) {
								throw $cleanupFailure;
							}
							throw new \RuntimeException(
								'Cross-site scenario failed: '.$scenarioFailure->getMessage()
								.' | cleanup also failed: '.$cleanupFailure->getMessage(),
								0,
								$scenarioFailure
							);
						}
					}
				}
			);
			if ( $exitCode === 0 ) {
				echo 'Cross-site test lane passed'.\PHP_EOL;
			}
			return $exitCode;
		}
		catch ( \Throwable $throwable ) {
			\fwrite( \STDERR, \PHP_EOL.'Cross-site test lane failed'.\PHP_EOL );
			\fwrite( \STDERR, 'Stage: '.$this->pairManager->lastStage().\PHP_EOL );
			\fwrite( \STDERR, 'Compose project: '.$this->pairManager->composeProjectName().\PHP_EOL );
			\fwrite( \STDERR, 'Master URL: '.$this->pairManager->masterInternalUrl().\PHP_EOL );
			\fwrite( \STDERR, 'Slave URL: '.$this->pairManager->slaveInternalUrl().\PHP_EOL );
			\fwrite( \STDERR, 'Master DB: '.$this->pairManager->masterDbName().\PHP_EOL );
			\fwrite( \STDERR, 'Slave DB: '.$this->pairManager->slaveDbName().\PHP_EOL );
			\fwrite( \STDERR, 'Error: '.$throwable->getMessage().\PHP_EOL );
			$diagnostics = $this->pairManager->lastDiagnostics();
			if ( !empty( $diagnostics ) ) {
				\fwrite( \STDERR, 'Diagnostics: '.\json_encode( $diagnostics, \JSON_UNESCAPED_SLASHES ).\PHP_EOL );
			}
			return 1;
		}
	}

	/**
	 * @param callable():int $callback
	 */
	private function withLock( string $rootDir, callable $callback ) :int {
		$lockDir = Path::join( $rootDir, self::LOCK_DIR );
		if ( !\is_dir( $lockDir ) && !\mkdir( $lockDir, 0777, true ) && !\is_dir( $lockDir ) ) {
			throw new \RuntimeException( 'Failed to create cross-site lane lock directory: '.$lockDir );
		}

		$lockPath = Path::join( $lockDir, self::LOCK_FILE );
		$handle = \fopen( $lockPath, 'c+' );
		if ( $handle === false ) {
			throw new \RuntimeException( 'Failed to open cross-site lane lock file: '.$lockPath );
		}

		$startedAt = \time();
		try {
			do {
				if ( \flock( $handle, \LOCK_EX | \LOCK_NB ) ) {
					$this->writeLeaseMetadata( $handle );
					return $callback();
				}
				\usleep( 500000 );
			} while ( \time() - $startedAt < 600 );

			throw new \RuntimeException( 'No cross-site test lane became available within 600 seconds. Lock: '.$lockPath );
		}
		finally {
			if ( \is_resource( $handle ) ) {
				@\flock( $handle, \LOCK_UN );
				@\fclose( $handle );
			}
		}
	}

	/**
	 * @param resource $handle
	 */
	private function writeLeaseMetadata( $handle ) :void {
		\rewind( $handle );
		\ftruncate( $handle, 0 );
		\fwrite( $handle, \json_encode( [
			'lane' => 'cross-site',
			'pid' => \getmypid(),
			'acquired_at_unix' => \time(),
		], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ).\PHP_EOL );
		\fflush( $handle );
	}
}
