<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

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

		$laneCount = $this->resolveLaneCount( $options[ 'lanes' ] ?? null );
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
		foreach ( $leases as $lease ) {
			try {
				$siteManager = $this->providedSiteManager ?? new LocalSiteManager( $lease->definition() );
				$labelEnv = $this->browserLabelEnv(
					$runId,
					'lane-'.$lease->laneIndex(),
					$transientExpiresAt,
					$reusableExpiresAt
				);

				echo \sprintf(
					'Browser lane: prepare lane %d at %s (%s)',
					$lease->laneIndex(),
					$lease->definition()->siteUrl(),
					$runMode
				).\PHP_EOL;
				$fixtureToken = \bin2hex( \random_bytes( 24 ) );
				$siteManager->prepareBrowserLane(
					$rootDir,
					$runMode,
					true,
					$fixtureToken,
					$showSetupOutput ? null : static function () :void {},
					$hostManifest,
					$labelEnv
				);
				$laneMap[ (string)$parallelIndex ] = [
					'laneIndex'     => $lease->laneIndex(),
					'baseUrl'       => $lease->definition()->siteUrl(),
					'fixtureToken'  => $fixtureToken,
					'authStatePath' => './test-results/playwright/lane-'.$lease->laneIndex().'/.auth/admin.json',
					'outputDir'     => './test-results/playwright/lane-'.$lease->laneIndex(),
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
			if ( $exitCode === 0 ) {
				$exitCode = $this->runPlaywright(
					$rootDir,
					$this->withResolvedWorkers( $playwrightArgs, $workerCount ),
					$laneMap
				);
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

			return $exitCode;
		}
		finally {
			$this->releaseLeases( $leases );
		}
	}

	/**
	 * @param array<int|string,array<string,int|string>> $laneMap
	 */
	private function encodeLaneMap( array $laneMap ) :string {
		return \json_encode( (object)$laneMap, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR );
	}

	/**
	 * @param string[] $playwrightArgs
	 * @param array<int|string,array<string,int|string>> $laneMap
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
	 * @return array<string,array<string,int|string>>
	 */
	private function inertLaneMap() :array {
		return [
			'0' => [
				'laneIndex' => 0,
				'baseUrl' => 'http://127.0.0.1:0',
				'fixtureToken' => 'list-only',
				'authStatePath' => './test-results/playwright/list-only/.auth/admin.json',
				'outputDir' => './test-results/playwright/list-only',
			],
		];
	}

	private function resolveLaneCount( ?string $explicitLaneCount ) :int {
		if ( $explicitLaneCount !== null && $explicitLaneCount !== '' ) {
			return $this->positiveInteger( $explicitLaneCount, '--lanes' );
		}
		$envLaneCount = \getenv( 'SHIELD_BROWSER_LANE_COUNT' );
		if ( \is_string( $envLaneCount ) && $envLaneCount !== '' ) {
			return $this->positiveInteger( $envLaneCount, 'SHIELD_BROWSER_LANE_COUNT' );
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
			return $this->positiveInteger( $envWorkerCount, 'SHIELD_BROWSER_WORKERS' );
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
				return $this->positiveInteger( $matches[ 1 ], $arg );
			}
			if ( ( $arg === '--workers' || $arg === '-j' ) && isset( $playwrightArgs[ $index + 1 ] ) ) {
				return $this->positiveInteger( $playwrightArgs[ $index + 1 ], $arg );
			}
			if ( \str_starts_with( $arg, '--workers' ) || $arg === '-j' ) {
				throw new \InvalidArgumentException( 'Playwright workers must be a positive integer for browser lane mapping.' );
			}
		}

		return null;
	}

	private function positiveInteger( string $value, string $source ) :int {
		if ( !\ctype_digit( $value ) || (int)$value < 1 ) {
			throw new \InvalidArgumentException( $source.' must be a positive integer.' );
		}

		return (int)$value;
	}

	private function buildRunId() :string {
		return 'shield-plugin-browser-'.\gmdate( 'YmdHis' ).'-'.\bin2hex( \random_bytes( 4 ) );
	}

	/**
	 * @return array<string,string>
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
