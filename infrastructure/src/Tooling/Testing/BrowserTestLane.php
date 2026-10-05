<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

/**
 * @phpstan-type BrowserLane array{laneIndex:int,baseUrl:string,fixtureToken:string,authStatePath:string,outputDir:string}
 * @phpstan-type BrowserLaneMap array<int|string,BrowserLane>
 */
class BrowserTestLane {

	private const MODE_CLEAN = 'clean';
	private const MODE_WARM = 'warm';
	private const DEFAULT_LOCAL_LANES = 2;
	private const DEFAULT_CI_LANES = 1;
	private const RUNTIME_REFRESH_FULL = LocalSiteRuntimeHostManifestProvider::MODE_FULL;
	private const RUNTIME_REFRESH_AUTO = LocalSiteRuntimeHostManifestProvider::MODE_AUTO;

	private ProcessRunner $processRunner;

	private ?LocalSiteManager $providedSiteManager;

	private BrowserTestLanePool $lanePool;

	private LocalSiteRuntimeHostManifestProvider $hostManifestProvider;

	private SourceGeneratedConfigReadiness $generatedConfigReadiness;

	private SourceAssetBuildReadiness $assetBuildReadiness;

	private DockerResourceSweeper $resourceSweeper;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?LocalSiteManager $siteManager = null,
		?BrowserTestLanePool $lanePool = null,
		?LocalSiteRuntimeHostManifestProvider $hostManifestProvider = null,
		?SourceGeneratedConfigReadiness $generatedConfigReadiness = null,
		?SourceAssetBuildReadiness $assetBuildReadiness = null,
		?DockerResourceSweeper $resourceSweeper = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->providedSiteManager = $siteManager;
		$this->lanePool = $lanePool ?? new BrowserTestLanePool();
		$this->hostManifestProvider = $hostManifestProvider ?? new LocalSiteRuntimeHostManifestProvider();
		$this->generatedConfigReadiness = $generatedConfigReadiness ?? new SourceGeneratedConfigReadiness( $this->processRunner );
		$this->assetBuildReadiness = $assetBuildReadiness ?? new SourceAssetBuildReadiness( $this->processRunner );
		$this->resourceSweeper = $resourceSweeper ?? new DockerResourceSweeper();
	}

	/**
	 * @param string[] $playwrightArgs
	 * @param array{mode?:?string,lanes?:?string,show_setup_output?:bool,runtime_refresh?:?string} $options
	 */
	public function run( string $rootDir, array $playwrightArgs = [], array $options = [] ) :int {
		return $this->execute( $rootDir, $playwrightArgs, $options );
	}

	/**
	 * Keep the lane leases alive until the external browser consumer finishes.
	 * @param callable(BrowserLaneMap):int $consumer
	 * @param array{mode?:?string,lanes?:?string,show_setup_output?:bool,runtime_refresh?:?string} $options
	 */
	public function runWithConsumer( string $rootDir, callable $consumer, array $options = [] ) :int {
		$lanes = $this->resolveLaneCount( $options[ 'lanes' ] ?? null );
		return $this->execute( $rootDir, [ '--workers='.$lanes ], $options, $consumer, $lanes );
	}

	/** @param callable(BrowserLaneMap):int|null $consumer */
	private function execute( string $rootDir, array $playwrightArgs, array $options, ?callable $consumer = null, ?int $resolvedLaneCount = null ) :int {
		echo 'Mode: browser'.\PHP_EOL;

		$playwrightArgs = $this->normalizePlaywrightArgs( $playwrightArgs );
		$runMode = $this->resolveRunMode( $options[ 'mode' ] ?? null );
		try {
			$runtimeRefreshMode = $this->resolveRuntimeRefreshMode( $options[ 'runtime_refresh' ] ?? null, $runMode );
		}
		catch ( \Throwable $throwable ) {
			$this->writeFailureDiagnostic( 'resolve runtime refresh mode', $throwable, null );
			return 1;
		}
		$showSetupOutput = (bool)( $options[ 'show_setup_output' ] ?? false );
		if ( $this->isListOnlyRun( $playwrightArgs ) ) {
			return $this->runPlaywright(
				$rootDir,
				$playwrightArgs,
				$this->inertLaneMap()
			);
		}

		$laneCount = $resolvedLaneCount ?? $this->resolveLaneCount( $options[ 'lanes' ] ?? null );
		$workerCount = $this->resolveWorkerCount( $playwrightArgs, $laneCount );
		if ( $workerCount > $laneCount ) {
			\fwrite(
				\STDERR,
				\sprintf(
					'Browser workers (%d) cannot exceed available lanes (%d). Use --lanes or reduce --workers.',
					$workerCount,
					$laneCount
				).\PHP_EOL
			);
			return 1;
		}

		try {
			return $this->lanePool->withSharedServiceAdmission( $rootDir, function () use ( $rootDir, $playwrightArgs, $runMode, $runtimeRefreshMode, $showSetupOutput, $laneCount, $workerCount, $consumer ) :int {
				return $this->executeAdmitted( $rootDir, $playwrightArgs, $runMode, $runtimeRefreshMode, $showSetupOutput, $laneCount, $workerCount, $consumer );
			} );
		}
		catch ( \Throwable $throwable ) {
			$this->writeFailureDiagnostic( 'browser shared-service admission', $throwable, null );
			return 1;
		}
	}

	/**
	 * @param string[] $playwrightArgs
	 * @param callable(BrowserLaneMap):int|null $consumer
	 */
	private function executeAdmitted(
		string $rootDir,
		array $playwrightArgs,
		string $runMode,
		string $runtimeRefreshMode,
		bool $showSetupOutput,
		int $laneCount,
		int $workerCount,
		?callable $consumer
	) :int {
		$runId = $this->buildRunId();
		$transientExpiresAt = \gmdate( \DATE_ATOM, \time() + 6*60*60 );
		$reusableExpiresAt = \gmdate( \DATE_ATOM, \time() + 7*24*60*60 );
		try {
			$this->resourceSweeper->startupSweep( $rootDir );
		}
		catch ( \Throwable $throwable ) {
			$this->writeFailureDiagnostic( 'browser Docker startup sweep', $throwable, null );
			return 1;
		}

		try {
			$this->generatedConfigReadiness->ensureReady(
				$rootDir,
				$showSetupOutput ? null : static function () :void {}
			);
		}
		catch ( \Throwable $throwable ) {
			$this->writeFailureDiagnostic( 'prepare generated config', $throwable, null );
			return 1;
		}

		try {
			$this->assetBuildReadiness->ensureReady(
				$rootDir,
				$showSetupOutput ? null : static function () :void {},
				'browser tests'
			);
		}
		catch ( \Throwable $throwable ) {
			$this->writeFailureDiagnostic( 'build browser assets', $throwable, null );
			return 1;
		}

		try {
			$hostManifest = $this->hostManifestProvider->manifest(
				$rootDir,
				$runtimeRefreshMode,
				$showSetupOutput ? null : static function () :void {}
			);
		}
		catch ( \Throwable $throwable ) {
			$this->writeFailureDiagnostic( 'build browser runtime host manifest', $throwable, null );
			return 1;
		}

		$leases = [];
		try {
			while ( \count( $leases ) < $workerCount ) {
				$lease = $this->lanePool->acquire(
					$rootDir,
					null,
					\array_keys( $leases ),
					$laneCount
				);
				$leases[ $lease->laneIndex() ] = $lease;
			}
		}
		catch ( \Throwable $throwable ) {
			$this->releaseLeases( $leases );
			$this->writeFailureDiagnostic( 'acquire browser lanes', $throwable, null );
			return 1;
		}

		$laneMap = [];
		$parallelIndex = 0;
		$exitCode = 0;
		$preparations = [];
		foreach ( $leases as $lease ) {
			try {
				$siteManager = $this->providedSiteManager ?? new LocalSiteManager( $lease->definition() );
				$labelEnv = $this->browserLabelEnv(
					$runId,
					'lane-'.$lease->laneIndex(),
					$transientExpiresAt,
					$reusableExpiresAt
				);
				if ( $parallelIndex === 0 ) {
					$siteManager->ensureSharedDatabaseReady( $rootDir, $showSetupOutput ? null : static function () :void {}, $labelEnv );
				}

				echo \sprintf(
					'Browser lane: prepare lane %d at %s (%s)',
					$lease->laneIndex(),
					$lease->definition()->siteUrl(),
					$runMode
				).\PHP_EOL;
				$fixtureToken = \bin2hex( \random_bytes( 24 ) );
				if ( $this->providedSiteManager === null && $workerCount > 1 ) {
					$process = new Process( [ \PHP_BINARY, __DIR__.'/prepare-browser-lane.php' ], $rootDir );
					$process->setInput( \json_encode( [
						'laneIndex' => $lease->laneIndex(),
						'mode' => $runMode,
						'requirePlaywright' => $consumer === null,
						'fixtureToken' => $fixtureToken,
						'hostManifest' => $hostManifest,
						'labelEnv' => $labelEnv,
					], \JSON_THROW_ON_ERROR ) );
					$process->setTimeout( 600 );
					$process->start( $showSetupOutput ? static function ( string $type, string $buffer ) :void {
						echo $buffer;
					} : null );
					$preparations[ $lease->laneIndex() ] = $process;
				}
				else {
					$siteManager->prepareBrowserLane(
						$rootDir,
						$runMode,
						$consumer === null,
						$fixtureToken,
						$showSetupOutput ? null : static function () :void {},
						$hostManifest,
						$labelEnv,
						true
					);
				}
				$outputDir = './test-results/playwright/lane-'.$lease->laneIndex();
				$laneMap[ (string)$parallelIndex ] = [
					'laneIndex'     => $lease->laneIndex(),
					'baseUrl'       => $lease->definition()->siteUrl(),
					'fixtureToken'  => $fixtureToken,
					'authStatePath' => $outputDir.'/.auth/admin.json',
					'outputDir'     => $outputDir,
				];
				$parallelIndex++;
			}
			catch ( \Throwable $throwable ) {
				$this->writeFailureDiagnostic( 'prepare browser lane', $throwable, $lease );
				$exitCode = 1;
				break;
			}
		}

		try {
			// Drain every child's pipes so large manifests and diagnostics cannot serialize preparation.
			do {
				$running = false;
				foreach ( $preparations as $laneIndex => $process ) {
					if ( $process->isRunning() ) {
						$process->checkTimeout();
						$running = true;
					}
					elseif ( !$process->isSuccessful() ) {
						throw new \RuntimeException( 'Browser lane '.$laneIndex.' preparation failed: '.$process->getErrorOutput().$process->getOutput() );
					}
				}
				if ( $running ) {
					\usleep( 10000 );
				}
			} while ( $running );
			if ( $exitCode === 0 ) {
				foreach ( $laneMap as $lane ) {
					( $this->providedSiteManager ?? new LocalSiteManager( $leases[ $lane[ 'laneIndex' ] ]->definition() ) )->cleanupCentralBrowserFixture( $lane[ 'fixtureToken' ] );
				}
				$exitCode = $consumer !== null ? $consumer( $laneMap ) : $this->runPlaywright(
					$rootDir,
					$this->withResolvedWorkers( $playwrightArgs, $workerCount ),
					$laneMap
				);
			}
		}
		catch ( \Throwable $throwable ) {
			$this->writeFailureDiagnostic( 'browser consumer', $throwable, null );
			$exitCode = 1;
		}
		finally {
			foreach ( $preparations as $process ) {
				if ( $process->isRunning() ) {
					$process->stop();
				}
			}
			if ( $consumer !== null ) {
				foreach ( $laneMap as $lane ) {
					try {
						( $this->providedSiteManager ?? new LocalSiteManager( $leases[ $lane[ 'laneIndex' ] ]->definition() ) )->cleanupCentralBrowserFixture( $lane[ 'fixtureToken' ] );
					}
					catch ( \Throwable $throwable ) {
						$this->writeFailureDiagnostic( 'cleanup Central fixture', $throwable, $leases[ $lane[ 'laneIndex' ] ] );
						$exitCode = 1;
					}
				}
			}
			$cleanupFindings = $this->cleanupRunResourcesSafely(
				$rootDir,
				$runId,
				$laneCount,
				$this->requiresFullCleanup( $runMode )
			);
			if ( $cleanupFindings !== [] ) {
				$this->writeCleanupFindings( $cleanupFindings );
				$exitCode = 1;
			}

			$this->releaseLeases( $leases );
		}
		return $exitCode;
	}

	/**
	 * @param BrowserLaneMap $laneMap
	 */
	private function encodeLaneMap( array $laneMap ) :string {
		return \json_encode( (object)$laneMap, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR );
	}

	/**
	 * @param string[] $playwrightArgs
	 * @param BrowserLaneMap $laneMap
	 */
	private function runPlaywright( string $rootDir, array $playwrightArgs, array $laneMap ) :int {
		echo 'Browser lane: run Playwright'.\PHP_EOL;

		return $this->processRunner->runForExitCode(
			\array_merge(
				[
					\PHP_BINARY,
					'./bin/run-node-tool.php',
					'playwright',
					'test',
				],
				$playwrightArgs
			),
			$rootDir,
			null,
			[
				'SHIELD_BROWSER_LANE_MAP' => $this->encodeLaneMap( $laneMap ),
			]
		);
	}

	/**
	 * @param string[] $playwrightArgs
	 * @return string[]
	 */
	private function withResolvedWorkers( array $playwrightArgs, int $workerCount ) :array {
		foreach ( $playwrightArgs as $arg ) {
			if ( $arg === '-j' || \str_starts_with( $arg, '--workers' ) ) {
				return $playwrightArgs;
			}
		}

		return \array_merge( [ '--workers='.$workerCount ], $playwrightArgs );
	}

	/**
	 * @param string[] $playwrightArgs
	 * @return string[]
	 */
	private function normalizePlaywrightArgs( array $playwrightArgs ) :array {
		return \array_values( \array_filter(
			$playwrightArgs,
			static fn( string $arg ) :bool => $arg !== '--'
		) );
	}

	private function resolveRunMode( ?string $explicitMode ) :string {
		if ( $explicitMode === self::MODE_CLEAN || $explicitMode === self::MODE_WARM ) {
			return $explicitMode;
		}
		$envMode = \getenv( 'SHIELD_BROWSER_MODE' );
		if ( $envMode === self::MODE_CLEAN || $envMode === self::MODE_WARM ) {
			return $envMode;
		}
		return \getenv( 'CI' ) ? self::MODE_CLEAN : self::MODE_WARM;
	}

	private function resolveRuntimeRefreshMode( ?string $explicitMode, string $runMode ) :string {
		if ( $explicitMode !== null && $explicitMode !== ''
			&& $explicitMode !== self::RUNTIME_REFRESH_FULL
			&& $explicitMode !== self::RUNTIME_REFRESH_AUTO
		) {
			throw new \InvalidArgumentException( 'Runtime refresh mode must be "full" or "auto".' );
		}
		if ( $runMode === self::MODE_CLEAN ) {
			return self::RUNTIME_REFRESH_FULL;
		}

		return $explicitMode ?: self::RUNTIME_REFRESH_AUTO;
	}

	/**
	 * @param string[] $playwrightArgs
	 */
	private function isListOnlyRun( array $playwrightArgs ) :bool {
		return \in_array( '--list', $playwrightArgs, true );
	}

	/**
	 * @return BrowserLaneMap
	 */
	private function inertLaneMap() :array {
		$outputDir = './test-results/playwright/list-only';
		return [
			'0' => [
				'laneIndex' => 0,
				'baseUrl' => 'http://127.0.0.1:0',
				'fixtureToken' => 'list-only',
				'authStatePath' => $outputDir.'/.auth/admin.json',
				'outputDir' => $outputDir,
			],
		];
	}

	private function resolveLaneCount( ?string $explicitLaneCount ) :int {
		if ( $explicitLaneCount !== null && $explicitLaneCount !== '' ) {
			return PositiveIntegerInput::parse( $explicitLaneCount, '--lanes' );
		}
		$envLaneCount = \getenv( 'SHIELD_BROWSER_LANE_COUNT' );
		if ( \is_string( $envLaneCount ) && $envLaneCount !== '' ) {
			return PositiveIntegerInput::parse( $envLaneCount, 'SHIELD_BROWSER_LANE_COUNT' );
		}
		return \getenv( 'CI' ) ? self::DEFAULT_CI_LANES : self::DEFAULT_LOCAL_LANES;
	}

	/**
	 * @param string[] $playwrightArgs
	 */
	private function resolveWorkerCount( array $playwrightArgs, int $laneCount ) :int {
		$playwrightWorkerCount = $this->extractPlaywrightWorkerCount( $playwrightArgs );
		if ( $playwrightWorkerCount !== null ) {
			return $playwrightWorkerCount;
		}
		$envWorkerCount = \getenv( 'SHIELD_BROWSER_WORKERS' );
		if ( \is_string( $envWorkerCount ) && $envWorkerCount !== '' ) {
			return PositiveIntegerInput::parse( $envWorkerCount, 'SHIELD_BROWSER_WORKERS' );
		}
		return \getenv( 'CI' ) ? 1 : $laneCount;
	}

	/**
	 * @param string[] $playwrightArgs
	 */
	private function extractPlaywrightWorkerCount( array $playwrightArgs ) :?int {
		foreach ( $playwrightArgs as $index => $arg ) {
			if ( \preg_match( '/^--workers=(\d+)$/', $arg, $matches ) === 1
				|| \preg_match( '/^-j=(\d+)$/', $arg, $matches ) === 1
			) {
				return PositiveIntegerInput::parse( $matches[ 1 ], $arg );
			}
			if ( ( $arg === '--workers' || $arg === '-j' ) && isset( $playwrightArgs[ $index + 1 ] ) ) {
				return PositiveIntegerInput::parse( $playwrightArgs[ $index + 1 ], $arg );
			}
			if ( \str_starts_with( $arg, '--workers' ) || $arg === '-j' ) {
				throw new \InvalidArgumentException( 'Playwright workers must be a positive integer for browser lane mapping.' );
			}
		}

		return null;
	}

	private function buildRunId() :string {
		return 'shield-plugin-browser-'.\gmdate( 'YmdHis' ).'-'.\bin2hex( \random_bytes( 4 ) );
	}

	/**
	 * @return array<string,string|false>
	 */
	private function browserLabelEnv(
		string $runId,
		string $lane,
		string $transientExpiresAt,
		string $reusableExpiresAt
	) :array {
		return $this->resourceSweeper->labelEnvironment(
			$runId,
			'transient',
			$lane,
			$transientExpiresAt,
			$runId,
			'reusable',
			$reusableExpiresAt
		);
	}

	private function requiresFullCleanup( string $runMode ) :bool {
		return \getenv( 'CI' ) || $runMode === self::MODE_CLEAN;
	}

	/**
	 * @return string[]
	 */
	private function cleanupRunResourcesSafely(
		string $rootDir,
		string $runId,
		int $laneCount,
		bool $fullCleanup
	) :array {
		try {
			return $this->resourceSweeper->cleanupRunResources( $rootDir, $runId, $laneCount, $fullCleanup )->findings();
		}
		catch ( \Throwable $throwable ) {
			return [
				'Browser harness Docker cleanup failed: '.$throwable->getMessage(),
			];
		}
	}

	/**
	 * @param string[] $cleanupFindings
	 */
	private function writeCleanupFindings( array $cleanupFindings ) :void {
		\fwrite( \STDERR, 'Browser harness cleanup left unexpected Docker resources:'.\PHP_EOL );
		foreach ( $cleanupFindings as $finding ) {
			\fwrite( \STDERR, '- '.$finding.\PHP_EOL );
		}
	}

	/**
	 * @param BrowserTestLaneLease[] $leases
	 */
	private function releaseLeases( array $leases ) :void {
		foreach ( $leases as $lease ) {
			$lease->release();
		}
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

}
