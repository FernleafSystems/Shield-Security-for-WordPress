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

	public function test_invalidate_deletes_transient() :void {
		$deleteCalls = 0;
		Functions\when( 'delete_transient' )->alias( function ( string $key ) use ( &$deleteCalls ) {
			$deleteCalls++;
			$this->assertSame( 'shield_security_admin_bar_scan_summary', $key );
			return true;
		} );

		( new AdminBarScanSummaryCache() )->invalidate();

		$this->assertSame( 1, $deleteCalls );
	}

	private function summary( array $counts, ?int $total = null ) :array {
		return [
			'counts'    => $counts,
			'total'     => $total ?? (int)\array_sum( $counts ),
			'is_capped' => false,
		];
	}
}
