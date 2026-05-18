<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

if ( !\function_exists( __NAMESPACE__.'\\error_log' ) ) {
	function error_log( string $message ) :bool {
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\ScansCronStartLogSpy::record( $message );
		return true;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\General;

class ScansControllerCronStartTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ScansCronStartLogSpy::reset();
		Functions\when( '__' )->returnArg();
		ServicesState::installItems( [
			'service_wpgeneral' => new class extends General {
				public function getIfAutoUpdatesInstalled() :bool {
					return false;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_run_cron_delegates_all_scan_controllers_to_central_start() :void {
		$state = $this->installController();
		$scans = new CronScansControllerTestDouble();

		$scans->runCron();

		$this->assertSame( [ $state->scanCons ], $scans->startCalls );
		$this->assertSame( [ [ 'key' => 'is_scan_cron', 'value' => true ] ], $state->opts->sets );
		$this->assertSame( 1, $state->opts->stores );
		$this->assertSame( [], ScansCronStartLogSpy::$messages );
	}

	private function installController() :object {
		$opts = new CronStartTestOptions();
		$scanCons = [
			new CronStartTestScanCon( 'afs' ),
			new CronStartTestScanCon( 'apc' ),
			new CronStartTestScanCon( 'wpv' ),
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->opts = $opts;
		$controller->labels = (object)[
			'Name' => 'Shield',
		];

		PluginControllerInstaller::install( $controller );
		CronScansControllerTestDouble::$scanCons = $scanCons;
		return (object)[
			'opts'     => $opts,
			'scanCons' => $scanCons,
		];
	}
}

class ScansCronStartLogSpy {

	public static array $messages = [];

	public static function reset() :void {
		self::$messages = [];
	}

	public static function record( string $message ) :void {
		self::$messages[] = $message;
	}
}

class CronScansControllerTestDouble extends ScansController {

	public static array $scanCons = [];

	public array $startCalls = [];

	public function getAllScanCons() :array {
		return self::$scanCons;
	}

	public function getCanScansExecute() :bool {
		return true;
	}

	public function startNewScans( array $scans, bool $resetIgnored = false ) :StartScansResult {
		unset( $resetIgnored );
		$this->startCalls[] = $scans;
		return StartScansResult::fromRequested( [ 'afs', 'apc', 'wpv' ] )
							   ->addStarted( 'afs', 31 )
							   ->addStarted( 'apc', 32 )
							   ->addStarted( 'wpv', 33 );
	}
}

class CronStartTestOptions {

	public array $sets = [];

	public int $stores = 0;

	public function optSet( string $key, $value ) :self {
		$this->sets[] = [
			'key'   => $key,
			'value' => $value,
		];
		return $this;
	}

	public function store() :self {
		$this->stores++;
		return $this;
	}
}

class CronStartTestScanCon {

	private string $slug;

	public function __construct( string $slug ) {
		$this->slug = $slug;
	}

	public function getSlug() :string {
		return $this->slug;
	}
}
