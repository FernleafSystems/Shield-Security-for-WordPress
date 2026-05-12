<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\InvestigateCountCache;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class InvestigateCountCacheTest extends BaseUnitTest {

	public function test_transient_hit_is_memoized_for_same_request() :void {
		$getTransientCalls = 0;
		Functions\when( 'get_transient' )->alias( function ( string $key ) use ( &$getTransientCalls ) {
			$getTransientCalls++;
			return '9';
		} );
		Functions\expect( 'set_transient' )->never();

		$harness = new InvestigateCountCacheTestDouble();
		$producerCalls = 0;
		$producer = function () use ( &$producerCalls ) :int {
			$producerCalls++;
			return 111;
		};

		$first = $harness->runCachedCount( 'activity', 'ip', '203.0.113.88', $producer );
		$second = $harness->runCachedCount( 'activity', 'ip', '203.0.113.88', $producer );

		$this->assertSame( 9, $first );
		$this->assertSame( 9, $second );
		$this->assertSame( 1, $getTransientCalls );
		$this->assertSame( 0, $producerCalls );
	}

	public function test_transient_miss_runs_producer_once_for_same_request() :void {
		$getTransientCalls = 0;
		Functions\when( 'get_transient' )->alias( function () use ( &$getTransientCalls ) {
			$getTransientCalls++;
			return false;
		} );
		Functions\expect( 'set_transient' )
			->once()
			->with( 'investigate_count_offenses_ip_203.0.113.90', 7, 30 )
			->andReturn( true );

		$harness = new InvestigateCountCacheTestDouble();
		$producerCalls = 0;
		$producer = function () use ( &$producerCalls ) :int {
			$producerCalls++;
			return 7;
		};

		$first = $harness->runCachedCount( 'offenses', 'ip', '203.0.113.90', $producer );
		$second = $harness->runCachedCount( 'offenses', 'ip', '203.0.113.90', $producer );

		$this->assertSame( 7, $first );
		$this->assertSame( 7, $second );
		$this->assertSame( 1, $getTransientCalls );
		$this->assertSame( 1, $producerCalls );
	}

	public function test_different_keys_have_independent_request_cache_entries() :void {
		$getTransientCalls = 0;
		Functions\when( 'get_transient' )->alias( function () use ( &$getTransientCalls ) {
			$getTransientCalls++;
			return false;
		} );
		Functions\when( 'set_transient' )->justReturn( true );

		$harness = new InvestigateCountCacheTestDouble();
		$producerCalls = 0;
		$producer = function () use ( &$producerCalls ) :int {
			$producerCalls++;
			return $producerCalls;
		};

		$firstKeyFirstCall = $harness->runCachedCount( 'activity', 'ip', '203.0.113.91', $producer );
		$secondKeyFirstCall = $harness->runCachedCount( 'activity', 'ip', '203.0.113.92', $producer );
		$firstKeySecondCall = $harness->runCachedCount( 'activity', 'ip', '203.0.113.91', $producer );

		$this->assertSame( 1, $firstKeyFirstCall );
		$this->assertSame( 2, $secondKeyFirstCall );
		$this->assertSame( 1, $firstKeySecondCall );
		$this->assertSame( 2, $producerCalls );
		$this->assertSame( 2, $getTransientCalls );
	}

	public function test_empty_cache_key_keeps_no_cache_behavior() :void {
		Functions\expect( 'get_transient' )->never();
		Functions\expect( 'set_transient' )->never();

		$harness = new InvestigateCountCacheTestDouble();
		$producerCalls = 0;
		$producer = function () use ( &$producerCalls ) :int {
			$producerCalls++;
			return 3;
		};

		$first = $harness->runCachedCount( '', 'ip', '203.0.113.99', $producer );
		$second = $harness->runCachedCount( '', 'ip', '203.0.113.99', $producer );

		$this->assertSame( 3, $first );
		$this->assertSame( 3, $second );
		$this->assertSame( 2, $producerCalls );
	}
}

class InvestigateCountCacheTestDouble {

	use InvestigateCountCache;

	public function runCachedCount( string $kind, string $subjectType, string $subjectId, callable $producer ) :int {
		return $this->cachedCount( $kind, $subjectType, $subjectId, $producer );
	}

	protected function buildInvestigateCountCacheKey( string $kind, string $subjectType, string $subjectId ) :string {
		if ( empty( $kind ) || empty( $subjectType ) || empty( $subjectId ) ) {
			return '';
		}
		return \sprintf(
			'investigate_count_%s_%s_%s',
			$kind,
			$subjectType,
			$subjectId
		);
	}

	protected function getInvestigateCountCacheTtl() :int {
		return 30;
	}
}

