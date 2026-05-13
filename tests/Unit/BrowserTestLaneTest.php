<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLanePool;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingLocalSiteRuntimeHostManifestProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BrowserTestLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		foreach ( [
			'CI',
			'SHIELD_BROWSER_LANE_COUNT',
			'SHIELD_BROWSER_LANE_WAIT_SECONDS',
			'SHIELD_BROWSER_MODE',
			'SHIELD_BROWSER_WORKERS',
		] as $name ) {
			\putenv( $name );
		}
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRunUsesWarmTwoWorkerLocalDefaultAndPassesLaneMap() :void {
		$this->clearBrowserEnvironment();
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-default-' );
		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$siteManager = $this->buildSiteManagerMock( 2, 'warm' );
		$hostManifestProvider = new RecordingLocalSiteRuntimeHostManifestProvider();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new BrowserTestLane( $playwrightRunner, $siteManager, null, $hostManifestProvider ) )->run( $projectRoot )
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $hostManifestProvider->calls );
		$this->assertSame( 'full', $hostManifestProvider->calls[ 0 ][ 'mode' ] );
		$this->assertSame(
			[
				\PHP_BINARY,
				'./bin/run-node-tool.php',
				'playwright',
				'test',
				'--workers=2',
			],
			$playwrightRunner->calls[ 0 ][ 'command' ]
		);
		$this->assertSame( [ 'SHIELD_BROWSER_LANE_MAP' ], \array_keys( $playwrightRunner->calls[ 0 ][ 'env_overrides' ] ) );
		$this->assertArrayNotHasKey( 'SHIELD_BROWSER_BASE_URL', $playwrightRunner->calls[ 0 ][ 'env_overrides' ] );
		$this->assertLaneMapIsJsonObject( $playwrightRunner );

		$laneMap = $this->decodeLaneMap( $playwrightRunner );
		$this->assertSame( [ 0, 1 ], \array_keys( $laneMap ) );
		$this->assertSame( 1, $laneMap[ 0 ][ 'laneIndex' ] );
		$this->assertSame( 'http://127.0.0.1:8890', $laneMap[ 0 ][ 'baseUrl' ] );
		$this->assertSame( './test-results/playwright/lane-1/.auth/admin.json', $laneMap[ 0 ][ 'authStatePath' ] );
		$this->assertSame( './test-results/playwright/lane-1', $laneMap[ 0 ][ 'outputDir' ] );
		$this->assertSame( 48, \strlen( (string)$laneMap[ 0 ][ 'fixtureToken' ] ) );
		$this->assertSame( 2, $laneMap[ 1 ][ 'laneIndex' ] );
		$this->assertSame( 'http://127.0.0.1:8891', $laneMap[ 1 ][ 'baseUrl' ] );
		$this->assertSame( './test-results/playwright/lane-2/.auth/admin.json', $laneMap[ 1 ][ 'authStatePath' ] );
	}

	public function testRunUsesCleanSingleWorkerCiDefault() :void {
		$this->clearBrowserEnvironment();
		\putenv( 'CI=true' );
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-ci-' );
		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$siteManager = $this->buildSiteManagerMock( 1, 'clean' );
		$hostManifestProvider = new RecordingLocalSiteRuntimeHostManifestProvider();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new BrowserTestLane( $playwrightRunner, $siteManager, null, $hostManifestProvider ) )->run( $projectRoot )
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $hostManifestProvider->calls );
		$this->assertSame( 'full', $hostManifestProvider->calls[ 0 ][ 'mode' ] );
		$this->assertSame( '--workers=1', $playwrightRunner->calls[ 0 ][ 'command' ][ 4 ] );
		$this->assertSame( [ 'SHIELD_BROWSER_LANE_MAP' ], \array_keys( $playwrightRunner->calls[ 0 ][ 'env_overrides' ] ) );
		$this->assertLaneMapIsJsonObject( $playwrightRunner );
		$this->assertCount( 1, $this->decodeLaneMap( $playwrightRunner ) );
	}

	public function testExplicitOptionsAndPlaywrightWorkersBeatEnvironment() :void {
		$this->clearBrowserEnvironment();
		\putenv( 'SHIELD_BROWSER_MODE=clean' );
		\putenv( 'SHIELD_BROWSER_LANE_COUNT=3' );
		\putenv( 'SHIELD_BROWSER_WORKERS=1' );
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-precedence-' );
		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$siteManager = $this->buildSiteManagerMock( 2, 'warm' );
		$hostManifestProvider = new RecordingLocalSiteRuntimeHostManifestProvider();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new BrowserTestLane( $playwrightRunner, $siteManager, null, $hostManifestProvider ) )->run(
				$projectRoot,
				[ '--workers=2', '-g', 'flow' ],
				[
					'mode'            => 'warm',
					'lanes'           => '2',
					'runtime_refresh' => 'auto',
				]
			)
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $hostManifestProvider->calls );
		$this->assertSame( 'auto', $hostManifestProvider->calls[ 0 ][ 'mode' ] );
		$this->assertSame(
			[
				\PHP_BINARY,
				'./bin/run-node-tool.php',
				'playwright',
				'test',
				'--workers=2',
				'-g',
				'flow',
			],
			$playwrightRunner->calls[ 0 ][ 'command' ]
		);
		$this->assertSame( [ 'SHIELD_BROWSER_LANE_MAP' ], \array_keys( $playwrightRunner->calls[ 0 ][ 'env_overrides' ] ) );
		$this->assertLaneMapIsJsonObject( $playwrightRunner );
		$this->assertCount( 2, $this->decodeLaneMap( $playwrightRunner ) );
	}

	public function testRunFailsWhenRequestedWorkersExceedLanes() :void {
		$this->clearBrowserEnvironment();
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-oversubscribe-' );
		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$siteManager = $this->buildSiteManagerMock( 0, 'warm' );
		$hostManifestProvider = new RecordingLocalSiteRuntimeHostManifestProvider();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new BrowserTestLane( $playwrightRunner, $siteManager, null, $hostManifestProvider ) )->run(
				$projectRoot,
				[ '--workers=3' ],
				[ 'lanes' => '2' ]
			)
		);

		$this->assertSame( 1, $exitCode );
		$this->assertCount( 0, $hostManifestProvider->calls );
		$this->assertCount( 0, $playwrightRunner->calls );
	}

	public function testListRunSkipsLanePreparationAndWorkerLaneValidation() :void {
		$this->clearBrowserEnvironment();
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-list-' );
		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$siteManager = $this->buildSiteManagerMock( 0, 'warm' );
		$hostManifestProvider = new RecordingLocalSiteRuntimeHostManifestProvider();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new BrowserTestLane( $playwrightRunner, $siteManager, null, $hostManifestProvider ) )->run(
				$projectRoot,
				[ '--workers=3', '--list' ],
				[ 'lanes' => '1' ]
			)
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 0, $hostManifestProvider->calls );
		$this->assertSame(
			[
				\PHP_BINARY,
				'./bin/run-node-tool.php',
				'playwright',
				'test',
				'--workers=3',
				'--list',
			],
			$playwrightRunner->calls[ 0 ][ 'command' ]
		);
		$laneMap = $this->decodeLaneMap( $playwrightRunner );
		$this->assertCount( 1, $laneMap );
		$this->assertSame( 0, $laneMap[ 0 ][ 'laneIndex' ] );
		$this->assertSame( 'http://127.0.0.1:0', $laneMap[ 0 ][ 'baseUrl' ] );
	}

	public function testCleanModeForcesFullRuntimeRefresh() :void {
		$this->clearBrowserEnvironment();
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-clean-full-' );
		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$siteManager = $this->buildSiteManagerMock( 1, 'clean' );
		$hostManifestProvider = new RecordingLocalSiteRuntimeHostManifestProvider();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new BrowserTestLane( $playwrightRunner, $siteManager, null, $hostManifestProvider ) )->run(
				$projectRoot,
				[],
				[
					'mode'            => 'clean',
					'lanes'           => '1',
					'runtime_refresh' => 'auto',
				]
			)
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $hostManifestProvider->calls );
		$this->assertSame( 'full', $hostManifestProvider->calls[ 0 ][ 'mode' ] );
	}

	public function testInvalidRuntimeRefreshModeFailsClearly() :void {
		$this->clearBrowserEnvironment();
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-invalid-refresh-' );
		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$siteManager = $this->buildSiteManagerMock( 0, 'warm' );
		$hostManifestProvider = new RecordingLocalSiteRuntimeHostManifestProvider();

		$exitCode = $this->runQuietly(
			static fn() :int => ( new BrowserTestLane( $playwrightRunner, $siteManager, null, $hostManifestProvider ) )->run(
				$projectRoot,
				[],
				[ 'runtime_refresh' => 'metadata' ]
			)
		);

		$this->assertSame( 1, $exitCode );
		$this->assertCount( 0, $hostManifestProvider->calls );
		$this->assertCount( 0, $playwrightRunner->calls );
	}

	public function testRunReleasesEveryLeaseWhenLanePreparationFails() :void {
		$this->clearBrowserEnvironment();
		\putenv( 'SHIELD_BROWSER_LANE_WAIT_SECONDS=1' );
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-release-' );
		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$lanePool = new BrowserTestLanePool();
		$siteManager = $this->getMockBuilder( LocalSiteManager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'prepareBrowserLane' ] )
			->getMock();
		$siteManager->expects( $this->once() )
			->method( 'prepareBrowserLane' )
			->willThrowException( new \RuntimeException( 'prepare failed' ) );

		$exitCode = $this->runQuietly(
			static fn() :int => ( new BrowserTestLane( $playwrightRunner, $siteManager, $lanePool, new RecordingLocalSiteRuntimeHostManifestProvider() ) )->run(
				$projectRoot,
				[],
				[ 'lanes' => '2' ]
			)
		);

		$this->assertSame( 1, $exitCode );
		$this->assertCount( 0, $playwrightRunner->calls );
		$lease = $lanePool->acquire( $projectRoot, static function () :void {}, [], 2 );
		$this->assertSame( 1, $lease->laneIndex() );
		$lease->release();
	}

	public function testRunFailsBeforeLanePreparationWhenPlaywrightIsMissing() :void {
		$this->clearBrowserEnvironment();
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-missing-playwright-' );
		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$siteManager = $this->getMockBuilder( LocalSiteManager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'prepareBrowserLane' ] )
			->getMock();
		$siteManager->expects( $this->once() )
			->method( 'prepareBrowserLane' )
			->willThrowException( new \RuntimeException( 'Playwright is not installed' ) );

		$exitCode = $this->runQuietly(
			static fn() :int => ( new BrowserTestLane( $playwrightRunner, $siteManager, null, new RecordingLocalSiteRuntimeHostManifestProvider() ) )->run(
				$projectRoot,
				[],
				[ 'lanes' => '1' ]
			)
		);

		$this->assertSame( 1, $exitCode );
		$this->assertCount( 0, $playwrightRunner->calls );
	}

	/**
	 * @return LocalSiteManager&MockObject
	 */
	private function buildSiteManagerMock( int $prepareCalls, string $expectedMode ) :LocalSiteManager {
		$siteManager = $this->getMockBuilder( LocalSiteManager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'prepareBrowserLane' ] )
			->getMock();
		$siteManager->expects( $this->exactly( $prepareCalls ) )
			->method( 'prepareBrowserLane' )
			->with(
				$this->isType( 'string' ),
				$expectedMode,
				true,
				$this->callback( static fn( string $token ) :bool => \preg_match( '/^[a-f0-9]{48}$/', $token ) === 1 ),
				$this->isType( 'callable' ),
				$this->isType( 'array' )
			)
			->willReturn( 0 );

		return $siteManager;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function decodeLaneMap( RecordingProcessRunner $playwrightRunner ) :array {
		$decoded = \json_decode( $this->laneMapJson( $playwrightRunner ), true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}

	private function assertLaneMapIsJsonObject( RecordingProcessRunner $playwrightRunner ) :void {
		$this->assertInstanceOf( \stdClass::class, \json_decode( $this->laneMapJson( $playwrightRunner ) ) );
	}

	private function laneMapJson( RecordingProcessRunner $playwrightRunner ) :string {
		$encoded = $playwrightRunner->calls[ 0 ][ 'env_overrides' ][ 'SHIELD_BROWSER_LANE_MAP' ] ?? '';
		$this->assertIsString( $encoded );
		return $encoded;
	}

	private function clearBrowserEnvironment() :void {
		foreach ( [
			'CI',
			'SHIELD_BROWSER_LANE_COUNT',
			'SHIELD_BROWSER_MODE',
			'SHIELD_BROWSER_WORKERS',
		] as $name ) {
			\putenv( $name );
		}
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
