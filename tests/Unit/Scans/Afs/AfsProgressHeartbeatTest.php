<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\{
	ResultsSet,
	Scan,
	ScanActionVO,
	ScanFromFileMap
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans\MalwareFile;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Fs;

class AfsProgressHeartbeatTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( string $path ) :string => \str_replace( '\\', '/', $path )
		);
		Functions\when( 'path_join' )->alias(
			static fn( string $base, string $path ) :string => \rtrim( $base, '/\\' ).\DIRECTORY_SEPARATOR.\ltrim( $path, '/\\' )
		);
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_prescan_filter_ticks_once_after_partial_batch() :void {
		$ticks = 0;
		$items = $this->invalidBase64Items( 999 );
		$action = $this->newPreScanAction( $items, $ticks );

		( new AfsPreScanHeartbeatTestDouble() )->exposeFilterKnownValidItems( $action );

		$this->assertSame( 1, $ticks );
		$this->assertSame( $items, $action->items );
	}

	public function test_prescan_filter_ticks_at_interval_without_completion_duplicate() :void {
		$ticks = 0;
		$items = $this->invalidBase64Items( 1000 );
		$action = $this->newPreScanAction( $items, $ticks );

		( new AfsPreScanHeartbeatTestDouble() )->exposeFilterKnownValidItems( $action );

		$this->assertSame( 1, $ticks );
		$this->assertSame( $items, $action->items );
	}

	public function test_prescan_filter_ticks_at_intervals_and_completion() :void {
		$ticks = 0;
		$items = $this->invalidBase64Items( 2500 );
		$action = $this->newPreScanAction( $items, $ticks );

		( new AfsPreScanHeartbeatTestDouble() )->exposeFilterKnownValidItems( $action );

		$this->assertSame( 3, $ticks );
		$this->assertSame( $items, $action->items );
	}

	public function test_file_map_ticks_progress_at_file_boundaries_without_scanning_empty_paths() :void {
		$ticks = 0;
		$this->installController();

		$action = new ScanActionVO();
		$action->items = [
			\base64_encode( '' ),
			\base64_encode( '' ),
			\base64_encode( '' ),
		];
		$action->max_file_size = 1024;
		$action->progress_callback = static function () use ( &$ticks ) :void {
			$ticks++;
		};

		( new ScanFromFileMap() )->setScanActionVO( $action )->run();

		$this->assertSame( 4, $ticks );
	}

	public function test_malware_signature_loop_ticks_progress_through_bounded_callback() :void {
		$ticks = 0;
		$phpFile = $this->createTrackedTempFile( 'shield-afs-', '.php', '<?php echo "clean";' );

		ServicesState::installItems( [
			'service_wpfs' => new class extends Fs {
				public function isAccessibleFile( string $path ) :bool {
					return \is_file( $path );
				}

				public function getFileContent( $path, $uncompress = false ) {
					unset( $uncompress );
					return (string)\file_get_contents( $path );
				}
			},
		] );

		$action = new ScanActionVO();
		$action->patterns_raw = \array_fill( 0, 60, 'not-present-signature' );
		$action->patterns_iraw = [];
		$action->patterns_regex = [];
		$action->patterns_keywords = [];
		$action->patterns_functions = [];
		$action->progress_callback = static function () use ( &$ticks ) :void {
			$ticks++;
		};

		$this->assertTrue( ( new MalwareFile( $phpFile ) )->setScanActionVO( $action )->isFileValid() );

		$this->assertGreaterThanOrEqual( 3, $ticks );
		$this->assertLessThan( 60, $ticks );
	}

	private function newPreScanAction( array $items, int &$ticks ) :ScanActionVO {
		$action = new ScanActionVO();
		$action->items = $items;
		$action->progress_callback = static function () use ( &$ticks ) :void {
			$ticks++;
		};
		return $action;
	}

	private function invalidBase64Items( int $count ) :array {
		$items = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$items[] = 'invalid-base64-'.$i.'*';
		}
		return $items;
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'opts_lookup' => new class {
				public function isScanAutoFilterResults() :bool {
					return false;
				}
			},
			'scans' => new class {
				public function AFS() :object {
					return new class {
						public function getNewResultsSet() :ResultsSet {
							return new ResultsSet();
						}
					};
				}
			},
		];
		PluginControllerInstaller::install( $controller );
	}
}

class AfsPreScanHeartbeatTestDouble extends Scan {

	public function exposeFilterKnownValidItems( ScanActionVO $action ) :void {
		$this->filterKnownValidItems( $action );
	}

	protected function scanSlice() {
	}
}
