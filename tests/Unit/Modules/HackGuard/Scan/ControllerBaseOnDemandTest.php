<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

if ( !\function_exists( __NAMESPACE__.'\\error_log' ) ) {
	function error_log( string $message ) :bool {
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\ControllerBaseOnDemandLogSpy::record( $message );
		return true;
	}
}

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
		ControllerBaseOnDemandLogSpy::reset();
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

	public function test_on_demand_scan_hook_logs_nothing_for_existing_active_scan_resumed_by_central_start() :void {
		$actions = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback ) use ( &$actions ) :bool {
				$actions[ $hook ] = $callback;
				return true;
			}
		);
		$this->installController(
			StartScansResult::fromRequested( [ 'afs' ] )->addResumed( 'afs', 501 )
		);

		( new OnDemandScanControllerTestDouble() )->execute();
		$actions[ 'icwp-wpsf-ondemand_scan_afs' ]();

		$this->assertSame( [], ControllerBaseOnDemandLogSpy::$messages );
	}

	public function test_on_demand_scan_hook_still_logs_real_start_failures() :void {
		$actions = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback ) use ( &$actions ) :bool {
				$actions[ $hook ] = $callback;
				return true;
			}
		);
		$this->installController(
			StartScansResult::fromRequested( [ 'afs' ] )->addFailure( 'afs', StartScansResult::REASON_CREATE_FAILED )
		);

		( new OnDemandScanControllerTestDouble() )->execute();
		$actions[ 'icwp-wpsf-ondemand_scan_afs' ]();

		$this->assertSame( [ 'Shield scan start failures: afs:create_failed' ], ControllerBaseOnDemandLogSpy::$messages );
	}

	private function installController( ?StartScansResult $startResult = null ) :object {
		$scans = new class( $startResult ) {
			public array $startCalls = [];

			private ?StartScansResult $startResult;

			public function __construct( ?StartScansResult $startResult ) {
				$this->startResult = $startResult;
			}

			public function startNewScans( array $scans ) :StartScansResult {
				$this->startCalls[] = $scans;
				return $this->startResult ?? StartScansResult::fromRequested( $scans )->addStarted( $scans[ 0 ], 31 );
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

class ControllerBaseOnDemandLogSpy {

	public static array $messages = [];

	public static function reset() :void {
		self::$messages = [];
	}

	public static function record( string $message ) :void {
		self::$messages[] = $message;
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
