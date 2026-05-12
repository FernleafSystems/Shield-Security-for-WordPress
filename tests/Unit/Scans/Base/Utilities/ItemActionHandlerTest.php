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
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities\{
	ItemActionHandler,
	ItemRepairHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Fs;

class ItemActionHandlerTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			'service_wpfs'    => new ItemActionFsStub(),
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_repair_resets_scan_memoization_after_successful_repair() :void {
		$updater = new ItemActionQueryUpdaterSpy();
		$resets = $this->installController( $updater );
		$handler = new ItemActionHandlerRepairTestSubject( true );

		$result = $handler
			->setScanItem( $this->scanItem( 7 ) )
			->repair();

		$this->assertTrue( $result );
		$this->assertSame( 1, $resets->count );
		$this->assertSame( 7, $updater->updates[ 0 ][ 0 ] );
		$this->assertSame( 'repaired', $updater->updates[ 0 ][ 1 ][ 'resolution_reason' ] );
	}

	public function test_repair_does_not_reset_scan_memoization_after_failed_repair() :void {
		$updater = new ItemActionQueryUpdaterSpy();
		$resets = $this->installController( $updater );
		$handler = new ItemActionHandlerRepairTestSubject( false );

		$result = $handler
			->setScanItem( $this->scanItem( 7 ) )
			->repair();

		$this->assertFalse( $result );
		$this->assertSame( 0, $resets->count );
		$this->assertSame( 7, $updater->updates[ 0 ][ 0 ] );
		$this->assertArrayNotHasKey( 'resolution_reason', $updater->updates[ 0 ][ 1 ] );
	}

	public function test_delete_resets_scan_memoization_after_successful_delete() :void {
		$updater = new ItemActionQueryUpdaterSpy();
		$resets = $this->installController( $updater );
		$path = \tempnam( \sys_get_temp_dir(), 'shield-delete-test-' );
		$this->assertIsString( $path );

		try {
			$item = $this->scanItem( 7 );
			$item->VO->scan = 'afs';
			$item->path_full = $path;
			$item->path_fragment = '';
			$item->is_unrecognised = true;

			$result = ( new ItemActionHandlerRepairTestSubject( false ) )
				->setScanItem( $item )
				->delete();
		}
		finally {
			if ( \is_file( $path ) ) {
				@\unlink( $path );
			}
		}

		$this->assertTrue( $result );
		$this->assertSame( [ 7 ], $updater->deletedIds );
		$this->assertSame( 1, $resets->count );
	}

	public function test_delete_does_not_reset_scan_memoization_when_delete_fails() :void {
		$updater = new ItemActionQueryUpdaterSpy();
		$resets = $this->installController( $updater );
		$item = $this->scanItem( 7 );
		$item->VO->scan = 'wpv';
		$item->path_full = 'not-deletable';
		$item->is_unrecognised = true;

		$this->expectException( \Exception::class );
		try {
			( new ItemActionHandlerRepairTestSubject( false ) )
				->setScanItem( $item )
				->delete();
		}
		finally {
			$this->assertSame( [], $updater->deletedIds );
			$this->assertSame( 0, $resets->count );
		}
	}

	private function installController( ItemActionQueryUpdaterSpy $updater ) :ItemActionResetSpy {
		$resets = new ItemActionResetSpy();

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scan_result_items' => new class( $updater ) {
				private ItemActionQueryUpdaterSpy $updater;

				public function __construct( ItemActionQueryUpdaterSpy $updater ) {
					$this->updater = $updater;
				}

				public function getQueryUpdater() :ItemActionQueryUpdaterSpy {
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

	private function scanItem( int $id ) :ResultItem {
		$item = new ResultItem();
		$item->VO = (object)[
			'resultitem_id' => $id,
		];
		return $item;
	}
}

class ItemActionHandlerRepairTestSubject extends ItemActionHandler {

	private bool $repairSucceeds;

	public function __construct( bool $repairSucceeds ) {
		$this->repairSucceeds = $repairSucceeds;
	}

	public function getRepairHandler() :ItemRepairHandler {
		return new class( $this->repairSucceeds ) extends ItemRepairHandler {
			private bool $repairSucceeds;

			public function __construct( bool $repairSucceeds ) {
				$this->repairSucceeds = $repairSucceeds;
			}

			public function canRepairItem() :bool {
				return true;
			}

			public function repairItem() :bool {
				return $this->repairSucceeds;
			}
		};
	}
}

class ItemActionQueryUpdaterSpy {

	public array $updates = [];

	public array $deletedIds = [];

	public function updateById( int $id, array $data ) :bool {
		$this->updates[] = [ $id, $data ];
		return true;
	}

	public function setItemDeleted( int $id ) :bool {
		$this->deletedIds[] = $id;
		return true;
	}
}

class ItemActionResetSpy {

	public int $count = 0;

	public function resetScanResultsCountMemoization() :void {
		$this->count++;
	}
}

class ItemActionFsStub extends Fs {

	private array $deleted = [];

	public function deleteFile( $path ) {
		$this->deleted[ $path ] = true;
		return true;
	}

	public function isAccessibleFile( string $path ) :bool {
		return empty( $this->deleted[ $path ] );
	}
}
}
