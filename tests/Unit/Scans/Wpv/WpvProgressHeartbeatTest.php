<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\{
	Scan,
	ScanActionVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	AssetSnapshots\SnapshotPluginVo,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Plugins;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class WpvProgressHeartbeatTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_scan_slice_ticks_once_per_plugin_item_before_plugin_lookup_and_filters_non_vulnerable_results() :void {
		$ticks = 0;
		$plugins = new WpvHeartbeatPlugins( [
			'alpha/alpha.php' => new SnapshotPluginVo( 'alpha/alpha.php', '' ),
			'beta/beta.php'   => new SnapshotPluginVo( 'beta/beta.php', '' ),
		], static function () use ( &$ticks ) :int {
			return $ticks;
		} );
		ServicesState::installItems( [
			'service_wpplugins' => $plugins,
		] );
		$action = $this->newAction( [
			'alpha/alpha.php',
			'beta/beta.php',
		], $ticks );

		( new WpvScanHeartbeatTestDouble() )->exposeScanSlice( $action );

		$this->assertSame( 2, $ticks );
		$this->assertSame( [ 1, 2 ], $plugins->tickCountsAtSlugLookup );
		$this->assertSame( [
			'alpha/alpha.php',
			'beta/beta.php',
		], $plugins->slugLookups );
		$this->assertSame( [], $action->results );
	}

	public function test_scan_slice_does_not_tick_empty_items() :void {
		$ticks = 0;
		$plugins = new WpvHeartbeatPlugins( [], static function () use ( &$ticks ) :int {
			return $ticks;
		} );
		ServicesState::installItems( [
			'service_wpplugins' => $plugins,
		] );
		$action = $this->newAction( [], $ticks );

		( new WpvScanHeartbeatTestDouble() )->exposeScanSlice( $action );

		$this->assertSame( 0, $ticks );
		$this->assertSame( [], $plugins->slugLookups );
		$this->assertSame( [], $action->results );
	}

	private function newAction( array $items, int &$ticks ) :ScanActionVO {
		$action = new ScanActionVO();
		$action->items = $items;
		$action->progress_callback = static function () use ( &$ticks ) :void {
			$ticks++;
		};
		return $action;
	}
}

class WpvScanHeartbeatTestDouble extends Scan {

	public function exposeScanSlice( ScanActionVO $action ) :void {
		$this->setScanActionVO( $action );
		$this->scanSlice();
	}
}

class WpvHeartbeatPlugins extends Plugins {

	public array $slugLookups = [];

	public array $tickCountsAtSlugLookup = [];

	private array $pluginsByFile;

	private $tickReader;

	public function __construct( array $pluginsByFile, callable $tickReader ) {
		$this->pluginsByFile = $pluginsByFile;
		$this->tickReader = $tickReader;
	}

	public function getSlug( $baseName ) {
		$this->slugLookups[] = $baseName;
		$this->tickCountsAtSlugLookup[] = ( $this->tickReader )();
		return '';
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		unset( $reload );
		return $this->pluginsByFile[ $file ] ?? null;
	}
}
