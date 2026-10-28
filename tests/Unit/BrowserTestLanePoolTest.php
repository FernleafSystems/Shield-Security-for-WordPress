<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLanePool;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpProcess;

class BrowserTestLanePoolTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		\putenv( 'SHIELD_BROWSER_LANE_POOL_SIZE' );
		\putenv( 'SHIELD_BROWSER_LANE_WAIT_SECONDS' );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testAcquireUsesFirstAvailableLaneAndReusesItAfterRelease() :void {
		$pool = new BrowserTestLanePool( $this->createTrackedTempDir( 'shield-browser-lane-pool-' ) );

		$lease = $pool->acquire( static function () :void {} );
		$this->assertSame( 1, $lease->laneIndex() );
		$this->assertSame( 'shield_browser_lane_1', $lease->definition()->dbName() );
		$lease->release();

		$nextLease = $pool->acquire( static function () :void {} );
		$this->assertSame( 1, $nextLease->laneIndex() );
		$nextLease->release();
	}

	public function testAcquireWaitsAndFailsWhenPoolIsFull() :void {
		\putenv( 'SHIELD_BROWSER_LANE_POOL_SIZE=1' );
		\putenv( 'SHIELD_BROWSER_LANE_WAIT_SECONDS=1' );
		$pool = new BrowserTestLanePool( $this->createTrackedTempDir( 'shield-browser-lane-pool-full-' ) );
		$lease = $pool->acquire( static function () :void {} );

		try {
			$this->expectExceptionMessage( 'No browser test lane became available within 1 seconds' );
			$pool->acquire( static function () :void {} );
		}
		finally {
			$lease->release();
		}
	}

	public function testAcquireCanSkipUnavailableLaneIndexes() :void {
		$pool = new BrowserTestLanePool( $this->createTrackedTempDir( 'shield-browser-lane-pool-skip-' ) );

		$lease = $pool->acquire( static function () :void {}, [ 1 ] );

		$this->assertSame( 2, $lease->laneIndex() );
		$this->assertSame( 'http://127.0.0.1:8902', $lease->definition()->siteUrl() );
		$lease->release();
	}

	public function testTryAcquireReturnsNullWhenEveryLaneIsLeased() :void {
		\putenv( 'SHIELD_BROWSER_LANE_POOL_SIZE=2' );
		$pool = new BrowserTestLanePool( $this->createTrackedTempDir( 'shield-browser-lane-pool-try-' ) );
		$first = $pool->tryAcquire( [], static function () :void {} );
		$second = $pool->tryAcquire( [ 1 ], static function () :void {} );

		$this->assertNotNull( $first );
		$this->assertNotNull( $second );
		$this->assertNull( $pool->tryAcquire( [ 1, 2 ], static function () :void {} ) );
		$first->release();
		$second->release();
	}

	public function testLeasesAreSharedAcrossProcessesUsingTheSameLockDirectory() :void {
		$lockDir = $this->createTrackedTempDir( 'shield-browser-lane-pool-shared-' );
		$pool = new BrowserTestLanePool( $lockDir );
		$lease = $pool->acquire( static function () :void {} );

		$child = new PhpProcess( \sprintf(
			'<?php require %s; $lease = ( new %s( %s ) )->tryAcquire( [], static function () :void {} ); echo $lease === null ? "none" : $lease->laneIndex();',
			\var_export( \dirname( __DIR__, 2 ).'/vendor/autoload.php', true ),
			'\\'.BrowserTestLanePool::class,
			\var_export( $lockDir, true )
		) );
		$child->mustRun();
		$lease->release();

		$this->assertSame( '2', \trim( $child->getOutput() ) );
	}

	public function testHasOtherActiveLeasesIgnoresHeldLanes() :void {
		$pool = new BrowserTestLanePool( $this->createTrackedTempDir( 'shield-browser-lane-pool-active-' ) );
		$this->assertFalse( $pool->hasOtherActiveLeases( [] ) );

		$lease = $pool->acquire( static function () :void {} );
		$this->assertTrue( $pool->hasOtherActiveLeases( [] ) );
		$this->assertFalse( $pool->hasOtherActiveLeases( [ $lease->laneIndex() ] ) );

		$lease->release();
		$this->assertFalse( $pool->hasOtherActiveLeases( [] ) );
	}

	public function testDefaultLockDirectoryIsMachineScoped() :void {
		$this->assertStringStartsWith(
			\str_replace( '\\', '/', \sys_get_temp_dir() ),
			\str_replace( '\\', '/', ( new BrowserTestLanePool() )->lockDir() )
		);
	}

	public function testPoolSizeFailsFastWhenEnvironmentValueIsInvalid() :void {
		\putenv( 'SHIELD_BROWSER_LANE_POOL_SIZE=two' );

		$this->expectExceptionMessage( 'SHIELD_BROWSER_LANE_POOL_SIZE must be a positive integer.' );

		( new BrowserTestLanePool() )->poolSize();
	}

	public function testPoolSizeIsCapped() :void {
		\putenv( 'SHIELD_BROWSER_LANE_POOL_SIZE=21' );

		$this->expectExceptionMessage( 'SHIELD_BROWSER_LANE_POOL_SIZE must not exceed 20.' );

		( new BrowserTestLanePool() )->poolSize();
	}
}
