<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\RepairItem;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	CoreFileHashes,
	General
};

class RepairItemTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_can_repair_core_item_on_stable_build() :void {
		$this->installServices( '6.8.1', true );

		$repairItem = ( new RepairItem() )->setScanItem( $this->newCoreResultItem() );

		$this->assertTrue( $repairItem->canRepair() );
	}

	public function test_cannot_repair_core_item_on_development_build() :void {
		$this->installServices( '6.9-beta1', true );

		$repairItem = ( new RepairItem() )->setScanItem( $this->newCoreResultItem() );

		$this->assertFalse( $repairItem->canRepair() );
	}

	private function installServices( string $version, bool $isCoreFile ) :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new class( $version ) extends General {
				private string $version;

				public function __construct( string $version ) {
					$this->version = $version;
				}

				public function getVersion( $ignoreClassicpress = false ) {
					return $this->version;
				}
			},
			'service_corefilehashes' => new class( $isCoreFile ) extends CoreFileHashes {
				private bool $isCoreFile;

				public function __construct( bool $isCoreFile ) {
					$this->isCoreFile = $isCoreFile;
				}

				public function isCoreFile( $file ) :bool {
					return $this->isCoreFile;
				}
			},
		] );
	}

	private function newCoreResultItem() :ResultItem {
		$item = new ResultItem();
		$item->is_in_core = true;
		$item->is_checksumfail = true;
		$item->path_fragment = 'wp-admin/admin.php';

		return $item;
	}
}
