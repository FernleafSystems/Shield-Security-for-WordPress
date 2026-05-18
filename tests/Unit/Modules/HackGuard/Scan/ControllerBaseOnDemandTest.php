<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class ControllerBaseOnDemandTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_on_demand_scan_hook_delegates_single_slug_to_central_start() :void {
		$actions = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback ) use ( &$actions ) :bool {
				$actions[ $hook ] = $callback;
				return true;
			}
		);
		$state = $this->installController();

		( new OnDemandScanControllerTestDouble() )->execute();
		$actions[ 'icwp-wpsf-ondemand_scan_afs' ]();

		$this->assertSame( [ [ 'afs' ] ], $state->scans->startCalls );
	}

	private function installController() :object {
		$scans = new class {
			public array $startCalls = [];

			public function startNewScans( array $scans ) :StartScansResult {
				$this->startCalls[] = $scans;
				return StartScansResult::fromRequested( $scans )->addStarted( $scans[ 0 ], 31 );
			}
		};

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'properties' => [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
			],
		];
		$controller->comps = (object)[
			'scans' => $scans,
		];

		PluginControllerInstaller::install( $controller );
		return (object)[
			'scans' => $scans,
		];
	}
}

class OnDemandScanControllerTestDouble extends Base {

	public function getSlug() :string {
		return 'afs';
	}

	public function isReady() :bool {
		return true;
	}

	protected function newItemActionHandler() {
		return null;
	}

	public function buildScanAction( ?BaseScanActionVO $scanAction = null ) {
		return $scanAction;
	}

	public function buildScanResult( array $rawResult ) :Record {
		unset( $rawResult );
		return new Record();
	}
}
