<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansMalaiFileQuery;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestUsers
};
use FernleafSystems\Wordpress\Services\Core\Db;

class ScansMalaiFileQueryTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpdb'    => new ScansMalaiUnexpectedDbAccess(),
			'service_wpusers' => new UnitTestUsers( 1 ),
		] );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_missing_confirm_fails_before_result_lookup() :void {
		$action = new ScansMalaiFileQueryConsentTestDouble( [] );
		$exec = new \ReflectionMethod( $action, 'exec' );
		$exec->setAccessible( true );

		$exec->invoke( $action );

		$payload = $action->response()->payload();
		$this->assertFalse( (bool)( $payload[ 'success' ] ?? true ) );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->this_req = (object)[
			'request_bypasses_all_restrictions' => false,
			'is_ip_blocked'                     => false,
			'wp_is_ajax'                        => false,
			'is_security_admin'                 => false,
		];
		$controller->caps = new class {
			public function canScanMalwareMalai() :bool {
				return true;
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}

class ScansMalaiFileQueryConsentTestDouble extends ScansMalaiFileQuery {

	protected function getMinimumUserAuthCapability() :string {
		return '';
	}
}

class ScansMalaiUnexpectedDbAccess extends Db {

	public function getVar( $sql ) {
		unset( $sql );
		throw new \Error( 'MAL{ai} consent failure must not look up scan results.' );
	}
}
