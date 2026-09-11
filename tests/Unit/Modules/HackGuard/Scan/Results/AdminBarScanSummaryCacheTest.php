<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	AdminBarScanSummaryCache,
	Counts
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class AdminBarScanSummaryCacheTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'properties' => [
				'slug_parent' => 'shield',
				'slug_plugin' => 'security',
			],
		];
		PluginControllerInstaller::install( $controller );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_read_returns_valid_exact_summary_and_memoizes_request() :void {
		$getTransientCalls = 0;
		Functions\when( 'get_transient' )->alias( function ( string $key ) use ( &$getTransientCalls ) {
			$getTransientCalls++;
			$this->assertSame( 'shield_security_admin_bar_scan_summary', $key );
			return $this->summary( [
				'malware'           => 2,
				'wp_files'          => 1,
				'plugin_files'      => 0,
				'theme_files'       => 0,
				'abandoned'         => 0,
				'vulnerable_assets' => 3,
			] );
		} );
		Functions\expect( 'delete_transient' )->never();

		$cache = new AdminBarScanSummaryCache();

		$this->assertSame( 6, $cache->read()[ 'total' ] );
		$this->assertSame( 6, $cache->read()[ 'total' ] );
		$this->assertSame( 1, $getTransientCalls );
	}

	public function test_read_rejects_malformed_cache_and_deletes_transient() :void {
		Functions\when( 'get_transient' )->justReturn( [
			'counts'    => [ 'malware' => 1 ],
			'total'     => 1,
			'is_capped' => false,
		] );
		Functions\expect( 'delete_transient' )
			->once()
			->with( 'shield_security_admin_bar_scan_summary' )
			->andReturn( true );

		$this->assertNull( ( new AdminBarScanSummaryCache() )->read() );
	}

	public function test_refresh_stores_normalized_exact_summary_with_ttl() :void {
		Functions\expect( 'set_transient' )
			->once()
			->with( 'shield_security_admin_bar_scan_summary', $this->summary( [
				'malware'           => 2,
				'wp_files'          => 0,
				'plugin_files'      => 0,
				'theme_files'       => 0,
				'abandoned'         => 0,
				'vulnerable_assets' => 1,
			], 3 ), 600 )
			->andReturn( true );

		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'adminBarScanSummary' ] )
					   ->getMock();
		$counts->expects( $this->once() )
			   ->method( 'adminBarScanSummary' )
			   ->with( true )
			   ->willReturn( $this->summary( [
				   'malware'           => 2,
				   'wp_files'          => 0,
				   'plugin_files'      => 0,
				   'theme_files'       => 0,
				   'abandoned'         => 0,
				   'vulnerable_assets' => 1,
			   ], 999 ) );

		$summary = ( new AdminBarScanSummaryCache() )->refresh( $counts );

		$this->assertSame( 3, $summary[ 'total' ] );
	}

	public function test_refresh_deletes_cache_when_exact_summary_is_invalid() :void {
		Functions\expect( 'delete_transient' )
			->once()
			->with( 'shield_security_admin_bar_scan_summary' )
			->andReturn( true );

		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'adminBarScanSummary' ] )
					   ->getMock();
		$counts->method( 'adminBarScanSummary' )->willReturn( [
			'counts'    => [],
			'total'     => 0,
			'is_capped' => true,
		] );

		$this->assertNull( ( new AdminBarScanSummaryCache() )->refresh( $counts ) );
	}

	public function test_read_bounded_returns_normalized_summary_and_memoizes_request() :void {
		$getTransientCalls = 0;
		Functions\when( 'get_transient' )->alias( function ( string $key ) use ( &$getTransientCalls ) {
			$getTransientCalls++;
			$this->assertSame( 'shield_security_admin_bar_scan_summary_bounded', $key );
			return [
				'counts'    => [],
				'total'     => '7',
				'is_capped' => 1,
				'ignored'   => true,
			];
		} );
		Functions\expect( 'delete_transient' )->never();

		$cache = new AdminBarScanSummaryCache();
		$expected = $this->boundedSummary( 7, true );

		$this->assertSame( $expected, $cache->readBounded() );
		$this->assertSame( $expected, $cache->readBounded() );
		$this->assertSame( 1, $getTransientCalls );
	}

	public function test_store_bounded_writes_distinct_key_with_ttl_and_memoizes() :void {
		$expected = $this->boundedSummary( 9, false );
		Functions\expect( 'set_transient' )
			->once()
			->with( 'shield_security_admin_bar_scan_summary_bounded', $expected, 60 )
			->andReturn( true );
		Functions\expect( 'get_transient' )->never();

		$cache = new AdminBarScanSummaryCache();

		$this->assertSame( $expected, $cache->storeBounded( [
			'counts'    => [],
			'total'     => '9',
			'is_capped' => 0,
		] ) );
		$this->assertSame( $expected, $cache->readBounded() );
	}

	/**
	 * @dataProvider invalidBoundedSummaryProvider
	 */
	public function test_store_bounded_rejects_invalid_shape_without_writing( array $summary ) :void {
		Functions\expect( 'set_transient' )->never();

		$this->assertNull( ( new AdminBarScanSummaryCache() )->storeBounded( $summary ) );
	}

	public static function invalidBoundedSummaryProvider() :array {
		return [
			'missing counts'   => [ [ 'total' => 1, 'is_capped' => false ] ],
			'non-array counts' => [ [ 'counts' => false, 'total' => 1, 'is_capped' => false ] ],
			'non-empty counts' => [ [ 'counts' => [ 'malware' => 1 ], 'total' => 1, 'is_capped' => false ] ],
			'non-numeric total' => [ [ 'counts' => [], 'total' => 'bad', 'is_capped' => false ] ],
			'missing capped'   => [ [ 'counts' => [], 'total' => 1 ] ],
		];
	}

	public function test_store_bounded_normalizes_negative_total_to_zero() :void {
		$expected = $this->boundedSummary( 0, false );
		Functions\expect( 'set_transient' )
			->once()
			->with( 'shield_security_admin_bar_scan_summary_bounded', $expected, 60 )
			->andReturn( true );

		$this->assertSame( $expected, ( new AdminBarScanSummaryCache() )->storeBounded( [
			'counts'    => [],
			'total'     => -4,
			'is_capped' => false,
		] ) );
	}

	public function test_read_bounded_rejects_malformed_cache_and_deletes_only_bounded_transient() :void {
		Functions\when( 'get_transient' )->justReturn( [
			'counts'    => [ 'malware' => 1 ],
			'total'     => 1,
			'is_capped' => false,
		] );
		Functions\expect( 'delete_transient' )
			->once()
			->with( 'shield_security_admin_bar_scan_summary_bounded' )
			->andReturn( true );

		$this->assertNull( ( new AdminBarScanSummaryCache() )->readBounded() );
	}

	public function test_invalidate_deletes_both_transients_and_blocks_same_request_reread() :void {
		$getCalls = [];
		Functions\when( 'get_transient' )->alias( function ( string $key ) use ( &$getCalls ) {
			$getCalls[] = $key;
			return $key === 'shield_security_admin_bar_scan_summary'
				? $this->summary( [
					'malware'           => 1,
					'wp_files'          => 0,
					'plugin_files'      => 0,
					'theme_files'       => 0,
					'abandoned'         => 0,
					'vulnerable_assets' => 0,
				] )
				: $this->boundedSummary( 4, false );
		} );
		$deleted = [];
		Functions\when( 'delete_transient' )->alias( function ( string $key ) use ( &$deleted ) {
			$deleted[] = $key;
			return true;
		} );

		$cache = new AdminBarScanSummaryCache();
		$this->assertNotNull( $cache->read() );
		$this->assertNotNull( $cache->readBounded() );

		$cache->invalidate();

		$this->assertNull( $cache->read() );
		$this->assertNull( $cache->readBounded() );
		$this->assertSame( [
			'shield_security_admin_bar_scan_summary',
			'shield_security_admin_bar_scan_summary_bounded',
		], $getCalls );
		$this->assertSame( [
			'shield_security_admin_bar_scan_summary',
			'shield_security_admin_bar_scan_summary_bounded',
		], $deleted );
	}

	public function test_invalidate_attempts_bounded_delete_when_exact_delete_throws() :void {
		$deleted = [];
		Functions\when( 'delete_transient' )->alias( function ( string $key ) use ( &$deleted ) {
			$deleted[] = $key;
			if ( $key === 'shield_security_admin_bar_scan_summary' ) {
				throw new \RuntimeException( 'exact delete failed' );
			}
			return true;
		} );

		( new AdminBarScanSummaryCache() )->invalidate();

		$this->assertSame( [
			'shield_security_admin_bar_scan_summary',
			'shield_security_admin_bar_scan_summary_bounded',
		], $deleted );
	}

	/**
	 * @dataProvider boundedSetFailureProvider
	 */
	public function test_store_bounded_returns_and_memoizes_valid_summary_when_set_fails( bool $throws ) :void {
		Functions\when( 'set_transient' )->alias( static function () use ( $throws ) {
			if ( $throws ) {
				throw new \RuntimeException( 'bounded set failed' );
			}
			return false;
		} );
		Functions\expect( 'get_transient' )->never();

		$cache = new AdminBarScanSummaryCache();
		$expected = $this->boundedSummary( 5, true );

		$this->assertSame( $expected, $cache->storeBounded( $expected ) );
		$this->assertSame( $expected, $cache->readBounded() );
	}

	public static function boundedSetFailureProvider() :array {
		return [
			'false return' => [ false ],
			'exception'    => [ true ],
		];
	}

	public function test_bounded_transient_is_reused_by_fresh_cache_instance() :void {
		$transients = [];
		Functions\when( 'set_transient' )->alias( static function (
			string $key,
			array $value,
			int $ttl
		) use ( &$transients ) :bool {
			$transients[ $key ] = [ 'value' => $value, 'ttl' => $ttl ];
			return true;
		} );
		Functions\when( 'get_transient' )->alias( static function ( string $key ) use ( &$transients ) {
			return $transients[ $key ][ 'value' ] ?? false;
		} );

		$expected = $this->boundedSummary( 8, false );
		$this->assertSame( $expected, ( new AdminBarScanSummaryCache() )->storeBounded( $expected ) );
		$this->assertSame( 60, $transients[ 'shield_security_admin_bar_scan_summary_bounded' ][ 'ttl' ] );
		$this->assertSame( $expected, ( new AdminBarScanSummaryCache() )->readBounded() );
	}

	public function test_read_bounded_get_exception_returns_memoized_miss() :void {
		$getCalls = 0;
		Functions\when( 'get_transient' )->alias( static function () use ( &$getCalls ) {
			$getCalls++;
			throw new \RuntimeException( 'bounded get failed' );
		} );

		$cache = new AdminBarScanSummaryCache();
		$this->assertNull( $cache->readBounded() );
		$this->assertNull( $cache->readBounded() );
		$this->assertSame( 1, $getCalls );
	}

	private function summary( array $counts, ?int $total = null ) :array {
		return [
			'counts'    => $counts,
			'total'     => $total ?? (int)\array_sum( $counts ),
			'is_capped' => false,
		];
	}

	private function boundedSummary( int $total, bool $isCapped ) :array {
		return [
			'counts'    => [],
			'total'     => $total,
			'is_capped' => $isCapped,
		];
	}
}
