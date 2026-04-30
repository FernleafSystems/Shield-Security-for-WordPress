<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {
	if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
		function shield_security_get_plugin() {
			return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Base\Utilities {

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities\ItemIgnoreHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};

class ItemIgnoreHandlerTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_ignore_resets_scan_memoization_after_successful_update() :void {
		$updater = new ItemIgnoreQueryUpdaterSpy( true );
		$resets = $this->installController( $updater );

		$result = ( new ItemIgnoreHandler() )
			->setScanItem( $this->scanItem( 7, 0 ) )
			->ignore();

		$this->assertTrue( $result );
		$this->assertSame( [ [ 7, [ 'ignored_at' => 1700000000 ] ] ], $updater->updates );
		$this->assertSame( 1, $resets->count );
	}

	public function test_ignore_does_not_reset_scan_memoization_when_update_fails() :void {
		$updater = new ItemIgnoreQueryUpdaterSpy( false );
		$resets = $this->installController( $updater );

		$this->expectException( \Exception::class );
		try {
			( new ItemIgnoreHandler() )
				->setScanItem( $this->scanItem( 7, 0 ) )
				->ignore();
		}
		finally {
			$this->assertSame( 0, $resets->count );
		}
	}

	public function test_unignore_resets_scan_memoization_after_successful_update() :void {
		$updater = new ItemIgnoreQueryUpdaterSpy( true );
		$resets = $this->installController( $updater );

		$result = ( new ItemIgnoreHandler() )
			->setScanItem( $this->scanItem( 7, 123 ) )
			->unignore();

		$this->assertTrue( $result );
		$this->assertSame( [ [ 7, [ 'ignored_at' => 0 ] ] ], $updater->updates );
		$this->assertSame( 1, $resets->count );
	}

	public function test_unignore_does_not_reset_scan_memoization_when_item_is_not_ignored() :void {
		$updater = new ItemIgnoreQueryUpdaterSpy( true );
		$resets = $this->installController( $updater );

		$result = ( new ItemIgnoreHandler() )
			->setScanItem( $this->scanItem( 7, 0 ) )
			->unignore();

		$this->assertTrue( $result );
		$this->assertSame( [], $updater->updates );
		$this->assertSame( 0, $resets->count );
	}

	private function installController( ItemIgnoreQueryUpdaterSpy $updater ) :ItemIgnoreResetSpy {
		$resets = new ItemIgnoreResetSpy();

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scan_result_items' => new class( $updater ) {
				public function __construct( private ItemIgnoreQueryUpdaterSpy $updater ) {
				}

				public function getQueryUpdater() :ItemIgnoreQueryUpdaterSpy {
					return $this->updater;
				}
			},
		];
		$controller->comps = (object)[
			'scans' => $resets,
		];
		PluginControllerInstaller::install( $controller );

		return $resets;
	}

	private function scanItem( int $id, int $ignoredAt ) :ResultItem {
		$item = new ResultItem();
		$item->VO = (object)[
			'resultitem_id' => $id,
			'ignored_at'    => $ignoredAt,
		];
		return $item;
	}
}

class ItemIgnoreQueryUpdaterSpy {

	public array $updates = [];

	public function __construct( private bool $succeed ) {
	}

	public function updateById( int $id, array $data ) :bool {
		$this->updates[] = [ $id, $data ];
		return $this->succeed;
	}
}

class ItemIgnoreResetSpy {

	public int $count = 0;

	public function resetScanResultsCountMemoization() :void {
		$this->count++;
	}
}
}
