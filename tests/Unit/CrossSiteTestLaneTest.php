<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSitePairManager;
use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSiteTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerCleanupReport;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerResourceSweeper;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CrossSiteTestLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		foreach ( [
			'CI',
			'SHIELD_CROSS_SITE_MODE',
		] as $name ) {
			\putenv( $name );
		}
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRunUsesWarmModeByDefaultForLocalRuns() :void {
		\putenv( 'CI' );
		\putenv( 'SHIELD_CROSS_SITE_MODE' );
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-' );
		$manager = $this->buildPairManagerMock( 'warm' );

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $projectRoot )
		);

		$this->assertSame( 0, $exitCode );
		$this->assertTrue( \is_file( $projectRoot.'/tmp/cross-site-test-lane/lane.lock' ) );
	}

	public function testRunUsesCleanModeByDefaultForCiRuns() :void {
		\putenv( 'CI=true' );
		\putenv( 'SHIELD_CROSS_SITE_MODE' );
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-ci-' );
		$manager = $this->buildPairManagerMock( 'clean', false, true );

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $projectRoot )
		);

		$this->assertSame( 0, $exitCode );
		$this->assertDirectoryDoesNotExist( $projectRoot.'/tmp/cross-site-test-lane/archive-workspace' );
	}

	public function testCleanRunOrdersPublicUpgradeBeforeCheckoutScenario() :void {
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-order-' );
		$manager = new CrossSiteLaneOrderRecordingPairManager();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $projectRoot, [ 'mode' => 'clean' ] )
		);

		$this->assertSame( 0, $exitCode );
		$this->assertSame(
			[ 'prepare:clean', 'public-upgrade', 'refresh-checkout', 'current-scenario' ],
			$manager->calls
		);
	}

	public function testWarmRunDoesNotReplacePublicRuntimeBeforeCurrentScenario() :void {
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-warm-order-' );
		$manager = new CrossSiteLaneOrderRecordingPairManager();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $projectRoot, [ 'mode' => 'warm' ] )
		);

		$this->assertSame( 0, $exitCode );
		$this->assertSame( [ 'prepare:warm', 'current-scenario' ], $manager->calls );
	}

	public function testExplicitModeBeatsEnvironment() :void {
		\putenv( 'CI=true' );
		\putenv( 'SHIELD_CROSS_SITE_MODE=clean' );
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-explicit-' );
		$manager = $this->buildPairManagerMock( 'warm', true );

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager ) )->run(
				$projectRoot,
				[
					'mode' => 'warm',
					'show_setup_output' => true,
				]
			)
		);

		$this->assertSame( 0, $exitCode );
	}

	public function testSuccessfulRunWritesOnlyFinalResultLine() :void {
		\putenv( 'CI' );
		\putenv( 'SHIELD_CROSS_SITE_MODE' );
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-output-' );
		$manager = $this->buildPairManagerMock( 'warm' );

		\ob_start();
		try {
			$exitCode = ( new CrossSiteTestLane( $manager ) )->run( $projectRoot );
			$output = (string)\ob_get_contents();
		}
		finally {
			\ob_end_clean();
		}

		$this->assertSame( 0, $exitCode );
		$this->assertSame( 'Cross-site test lane passed'.\PHP_EOL, $output );
		$this->assertStringNotContainsString( 'Mode:', $output );
		$this->assertStringNotContainsString( 'Stage:', $output );
	}

	public function testTeardownUsesTheFullCrossSiteCleanupAudit() :void {
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-teardown-' );
		$manager = $this->buildPairManagerMock( 'warm' );
		$sweeper = new CrossSiteLaneResourceSweeperRecorder();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager, $sweeper ) )->run(
				$projectRoot,
				[ 'mode' => 'warm', 'teardown' => true ]
			)
		);

		$this->assertSame( 0, $exitCode );
		$this->assertSame( [ [
			'root_dir' => $projectRoot,
			'lane_count' => 1,
			'full_cleanup' => true,
		] ], $sweeper->calls );
	}

	public function testTeardownCleanupFindingsFailTheLane() :void {
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-teardown-finding-' );
		$manager = $this->buildPairManagerMock( 'warm' );
		$sweeper = new CrossSiteLaneResourceSweeperRecorder( [ 'Cross-site cleanup failed.' ] );

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager, $sweeper ) )->run(
				$projectRoot,
				[ 'mode' => 'warm', 'teardown' => true ]
			)
		);

		$this->assertSame( 1, $exitCode );
		$this->assertTrue( $sweeper->calls[ 0 ][ 'full_cleanup' ] );
	}

	public function testFailureStillRemovesArchiveWorkspaceAndRunsTeardown() :void {
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-failure-cleanup-' );
		$manager = new CrossSiteLaneFailingPublicPairManager();
		$sweeper = new CrossSiteLaneResourceSweeperRecorder();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager, $sweeper ) )->run(
				$projectRoot,
				[ 'mode' => 'clean', 'teardown' => true ]
			)
		);

		$this->assertSame( 1, $exitCode );
		$this->assertDirectoryDoesNotExist( $projectRoot.'/tmp/cross-site-test-lane/archive-workspace' );
		$this->assertSame( [ [
			'root_dir' => $projectRoot,
			'lane_count' => 1,
			'full_cleanup' => true,
		] ], $sweeper->calls );
	}

	/**
	 * @return CrossSitePairManager&MockObject
	 */
	private function buildPairManagerMock(
		string $expectedMode,
		bool $showSetupOutput = false,
		bool $expectPublicUpgrade = false
	) :CrossSitePairManager {
		$manager = $this->getMockBuilder( CrossSitePairManager::class )
			->disableOriginalConstructor()
			->onlyMethods( [
				'prepare',
				'runPublicUpgradeScenario',
				'refreshCheckoutRuntime',
				'refreshCheckoutRuntimeAfterPublicUpgrade',
				'runImportExportScenario',
			] )
			->getMock();
		$manager->expects( $this->once() )
			->method( 'prepare' )
			->with(
				$this->isType( 'string' ),
				$expectedMode,
				$showSetupOutput
			);
		$publicExpectation = $expectPublicUpgrade ? $this->once() : $this->never();
		$manager->expects( $publicExpectation )
			->method( 'runPublicUpgradeScenario' )
			->with(
				$this->isType( 'string' ),
				$this->stringEndsWith( 'tmp/cross-site-test-lane/archive-workspace' )
			)
			->willReturnCallback( static function ( string $rootDir, string $workspace ) :void {
				if ( !\is_dir( $workspace ) ) {
					\mkdir( $workspace, 0777, true );
				}
				\file_put_contents( $workspace.'/package.zip', 'fixture' );
			} );
		$manager->expects( $expectPublicUpgrade ? $this->once() : $this->never() )
			->method( 'refreshCheckoutRuntimeAfterPublicUpgrade' )
			->with( $this->isType( 'string' ), $showSetupOutput );
		$manager->expects( $this->once() )
			->method( 'runImportExportScenario' )
			->with( $this->isType( 'string' ) );

		return $manager;
	}

	/**
	 * @param callable():int $callback
	 */
	private function runQuietly( callable $callback ) :int {
		\ob_start();
		try {
			return $callback();
		}
		finally {
			\ob_end_clean();
		}
	}
}

class CrossSiteLaneResourceSweeperRecorder extends DockerResourceSweeper {

	/** @var array<int,array{root_dir:string,lane_count:int,full_cleanup:bool}> */
	public array $calls = [];

	/** @var string[] */
	private array $findings;

	/**
	 * @param string[] $findings
	 */
	public function __construct( array $findings = [] ) {
		$this->findings = $findings;
	}

	public function cleanupRunResources(
		string $rootDir,
		string $runId,
		int $laneCount,
		bool $fullCleanup,
		bool $dryRun = false
	) :DockerCleanupReport {
		$this->calls[] = [
			'root_dir' => $rootDir,
			'lane_count' => $laneCount,
			'full_cleanup' => $fullCleanup,
		];
		$report = new DockerCleanupReport( $dryRun );
		foreach ( $this->findings as $finding ) {
			$report->addFinding( $finding );
		}
		return $report;
	}

	public function cleanupAllHarnessResources(
		string $rootDir,
		int $laneCount,
		?DockerCleanupReport $report = null
	) :DockerCleanupReport {
		throw new \LogicException( 'Cross-site lane must use cleanupRunResources() for teardown.' );
	}
}

class CrossSiteLaneOrderRecordingPairManager extends CrossSitePairManager {

	/** @var string[] */
	public array $calls = [];

	public function __construct() {
	}

	public function prepare( string $rootDir, string $mode, bool $showSetupOutput = false ) :void {
		$this->calls[] = 'prepare:'.$mode;
	}

	public function runPublicUpgradeScenario( string $rootDir, string $archiveWorkspace ) :void {
		$this->calls[] = 'public-upgrade';
	}

	public function refreshCheckoutRuntimeAfterPublicUpgrade( string $rootDir, bool $showSetupOutput = false ) :void {
		$this->calls[] = 'refresh-checkout';
	}

	public function runImportExportScenario( string $rootDir ) :void {
		$this->calls[] = 'current-scenario';
	}
}

class CrossSiteLaneFailingPublicPairManager extends CrossSiteLaneOrderRecordingPairManager {

	public function runPublicUpgradeScenario( string $rootDir, string $archiveWorkspace ) :void {
		$this->calls[] = 'public-upgrade';
		\mkdir( $archiveWorkspace, 0777, true );
		\file_put_contents( $archiveWorkspace.'/current.zip', 'fixture' );
		throw new \RuntimeException( 'Deliberate public scenario failure.' );
	}
}
