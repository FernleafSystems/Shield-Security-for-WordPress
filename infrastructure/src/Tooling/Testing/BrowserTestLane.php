<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

class BrowserTestLane {

	private ProcessRunner $processRunner;

	private LocalSiteManager $siteManager;

	private ?LocalSiteManager $providedSiteManager;

	private BrowserTestLanePool $lanePool;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?LocalSiteManager $siteManager = null,
		?BrowserTestLanePool $lanePool = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->providedSiteManager = $siteManager;
		$this->siteManager = $siteManager ?? new LocalSiteManager( LocalSiteDefinitions::browserLane( 1 ) );
		$this->lanePool = $lanePool ?? new BrowserTestLanePool();
	}

	/**
	 * @param string[] $playwrightArgs
	 */
	public function run( string $rootDir, array $playwrightArgs = [] ) :int {
		echo 'Mode: browser'.\PHP_EOL;

		$lease = null;
		$skippedLaneIndexes = [];
		$lastPrepareError = null;
		while ( \count( $skippedLaneIndexes ) < $this->lanePool->laneCount() ) {
			try {
				$lease = $this->lanePool->acquire( $rootDir, null, $skippedLaneIndexes );
				$this->siteManager = $this->providedSiteManager ?? new LocalSiteManager( $lease->definition() );

				echo \sprintf(
					'Browser lane: reset lane %d at %s',
					$lease->laneIndex(),
					$lease->definition()->siteUrl()
				).\PHP_EOL;
				$this->siteManager->reset( $rootDir, true, static function () :void {} );
				break;
			}
			catch ( \Throwable $throwable ) {
				$lastPrepareError = $throwable;
				if ( $lease !== null && $this->isRecoverablePortConflict( $throwable ) ) {
					$skippedLaneIndexes[] = $lease->laneIndex();
					\fwrite(
						\STDERR,
						'Browser lane: lane '.$lease->laneIndex().' port unavailable; trying another lane.'.\PHP_EOL
					);
					$lease->release();
					$lease = null;
					continue;
				}

				$this->writeFailureDiagnostic( 'prepare browser lane', $throwable, $lease );
				return 1;
			}
		}
		if ( $lease === null ) {
			if ( $lastPrepareError !== null ) {
				$this->writeFailureDiagnostic( 'prepare browser lane', $lastPrepareError, null );
			}
			return 1;
		}

		$envOverrides = [
			'SHIELD_BROWSER_BASE_URL' => $this->siteManager->definition()->siteUrl(),
			'SHIELD_BROWSER_LANE_INDEX' => (string)$lease->laneIndex(),
			'SHIELD_BROWSER_LANE_DB_NAME' => $this->siteManager->definition()->dbName(),
			'SHIELD_BROWSER_OUTPUT_DIR' => './test-results/playwright/lane-'.$lease->laneIndex(),
		];

		echo 'Browser lane: run Playwright'.\PHP_EOL;
		try {
			return $this->processRunner->runForExitCode(
				\array_merge(
					[
						\PHP_BINARY,
						'./bin/run-node-tool.php',
						'playwright',
						'test',
					],
					$this->withDefaultWorkers( $playwrightArgs )
				),
				$rootDir,
				null,
				$envOverrides
			);
		}
		finally {
			$lease->release();
		}
	}

	/**
	 * @param string[] $playwrightArgs
	 * @return string[]
	 */
	private function withDefaultWorkers( array $playwrightArgs ) :array {
		foreach ( $playwrightArgs as $arg ) {
			if ( $arg === '-j' || \str_starts_with( $arg, '--workers' ) ) {
				return $playwrightArgs;
			}
		}

		return \array_merge( [ '--workers=1' ], $playwrightArgs );
	}

	private function writeFailureDiagnostic(
		string $stage,
		\Throwable $throwable,
		?BrowserTestLaneLease $lease
	) :void {
		$definition = $lease === null ? null : $lease->definition();
		\fwrite( \STDERR, \PHP_EOL.'Browser test lane failed'.\PHP_EOL );
		\fwrite( \STDERR, 'Stage: '.$stage.\PHP_EOL );
		if ( $definition !== null ) {
			\fwrite( \STDERR, 'Lane: '.$lease->laneIndex().\PHP_EOL );
			\fwrite( \STDERR, 'Site URL: '.$definition->siteUrl().\PHP_EOL );
			\fwrite( \STDERR, 'Database: '.$definition->dbName().\PHP_EOL );
			\fwrite( \STDERR, 'Compose project: '.$definition->composeProjectName().\PHP_EOL );
			\fwrite( \STDERR, 'Next diagnostic: SHIELD_BROWSER_LANE_INDEX='.$lease->laneIndex().' php bin/shield test:site:status'.\PHP_EOL );
		}
		\fwrite( \STDERR, 'Error: '.$throwable->getMessage().\PHP_EOL );
	}

	private function isRecoverablePortConflict( \Throwable $throwable ) :bool {
		$message = $throwable->getMessage();
		return \strpos( $message, 'Port ' ) !== false
			&& \strpos( $message, 'already in use' ) !== false
			&& \strpos( $message, 'not responding' ) !== false;
	}
}
