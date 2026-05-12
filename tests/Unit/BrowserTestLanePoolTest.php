<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLanePool;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;

class BrowserTestLanePoolTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		\putenv( 'SHIELD_BROWSER_LANE_COUNT' );
		\putenv( 'SHIELD_BROWSER_LANE_WAIT_SECONDS' );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testAcquireUsesFirstAvailableLaneAndReusesItAfterRelease() :void {
		\putenv( 'SHIELD_BROWSER_LANE_COUNT=2' );
		$rootDir = $this->createTrackedTempDir( 'shield-browser-lane-pool-' );
		$pool = new BrowserTestLanePool();

		$lease = $pool->acquire( $rootDir, static function () :void {} );
		$this->assertSame( 1, $lease->laneIndex() );
		$this->assertSame( 'shield_test_site_lane_1', $lease->definition()->dbName() );
		$lease->release();

		$nextLease = $pool->acquire( $rootDir, static function () :void {} );
		$this->assertSame( 1, $nextLease->laneIndex() );
		$nextLease->release();
	}

	public function testAcquireWaitsAndFailsWhenPoolIsFull() :void {
		\putenv( 'SHIELD_BROWSER_LANE_COUNT=1' );
		\putenv( 'SHIELD_BROWSER_LANE_WAIT_SECONDS=1' );
		$rootDir = $this->createTrackedTempDir( 'shield-browser-lane-pool-full-' );
		$pool = new BrowserTestLanePool();
		$lease = $pool->acquire( $rootDir, static function () :void {} );

		try {
			$this->expectExceptionMessage( 'No browser test lane became available within 1 seconds' );
			$pool->acquire( $rootDir, static function () :void {} );
		}
		finally {
			$lease->release();
		}
	}

	public function testAcquireCanSkipUnavailableLaneIndexes() :void {
		\putenv( 'SHIELD_BROWSER_LANE_COUNT=2' );
		$rootDir = $this->createTrackedTempDir( 'shield-browser-lane-pool-skip-' );
		$pool = new BrowserTestLanePool();

		$lease = $pool->acquire( $rootDir, static function () :void {}, [ 1 ] );

		$this->assertSame( 2, $lease->laneIndex() );
		$this->assertSame( 'http://127.0.0.1:8891', $lease->definition()->siteUrl() );
		$lease->release();
	}

	public function testLaneCountFailsFastWhenEnvironmentValueIsInvalid() :void {
		\putenv( 'SHIELD_BROWSER_LANE_COUNT=two' );

		$this->expectExceptionMessage( 'SHIELD_BROWSER_LANE_COUNT must be a positive integer.' );

		( new BrowserTestLanePool() )->laneCount();
	}

	public function testLaneCountOverrideBeatsEnvironmentValue() :void {
		\putenv( 'SHIELD_BROWSER_LANE_COUNT=3' );

		$this->assertSame( 1, ( new BrowserTestLanePool() )->laneCount( 1 ) );
	}
}
