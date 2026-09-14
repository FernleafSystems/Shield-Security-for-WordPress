<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc\{
	PluginScanner,
	Scan,
	ScanActionVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ApcProgressHeartbeatTest extends BaseUnitTest {

	public function test_scan_slice_ticks_once_per_item_before_scanner_work_and_filters_results() :void {
		$ticks = 0;
		$alphaResult = [
			'slug'            => 'alpha/alpha.php',
			'is_abandoned'    => true,
			'last_updated_at' => 1700000000,
		];
		$gammaResult = [
			'slug'            => 'gamma/gamma.php',
			'is_abandoned'    => true,
			'last_updated_at' => 1700000100,
		];
		$scanner = new ApcHeartbeatPluginScanner( [
			'alpha/alpha.php' => $alphaResult,
			'beta/beta.php'   => [],
			'gamma/gamma.php' => $gammaResult,
		], static function () use ( &$ticks ) :int {
			return $ticks;
		} );
		$action = $this->newAction( [
			'alpha/alpha.php',
			'beta/beta.php',
			'gamma/gamma.php',
		], $ticks );

		( new ApcScanHeartbeatTestDouble( $scanner ) )->exposeScanSlice( $action );

		$this->assertSame( 3, $ticks );
		$this->assertSame( [ 1, 2, 3 ], $scanner->tickCountsAtScan );
		$this->assertSame( [
			'alpha/alpha.php',
			'beta/beta.php',
			'gamma/gamma.php',
		], $scanner->scanCalls );
		$this->assertSame( [ $alphaResult, $gammaResult ], $action->results );
	}

	public function test_scan_slice_does_not_tick_empty_items() :void {
		$ticks = 0;
		$scanner = new ApcHeartbeatPluginScanner( [], static function () use ( &$ticks ) :int {
			return $ticks;
		} );
		$action = $this->newAction( [], $ticks );

		( new ApcScanHeartbeatTestDouble( $scanner ) )->exposeScanSlice( $action );

		$this->assertSame( 0, $ticks );
		$this->assertSame( [], $scanner->scanCalls );
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

class ApcScanHeartbeatTestDouble extends Scan {

	private PluginScanner $scanner;

	public function __construct( PluginScanner $scanner ) {
		$this->scanner = $scanner;
	}

	public function exposeScanSlice( ScanActionVO $action ) :void {
		$this->setScanActionVO( $action );
		$this->scanSlice();
	}

	protected function getItemScanner() :PluginScanner {
		return $this->scanner->setScanActionVO( $this->getScanActionVO() );
	}
}

class ApcHeartbeatPluginScanner extends PluginScanner {

	public array $scanCalls = [];

	public array $tickCountsAtScan = [];

	private array $resultsByFile;

	private $tickReader;

	public function __construct( array $resultsByFile, callable $tickReader ) {
		$this->resultsByFile = $resultsByFile;
		$this->tickReader = $tickReader;
	}

	public function scan( string $pluginFile ) :array {
		$this->scanCalls[] = $pluginFile;
		$this->tickCountsAtScan[] = ( $this->tickReader )();
		return $this->resultsByFile[ $pluginFile ] ?? [];
	}
}
