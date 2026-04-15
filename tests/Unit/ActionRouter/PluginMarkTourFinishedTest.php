<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\PluginMarkTourFinished,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TourManager;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Users;

class PluginMarkTourFinishedTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \preg_replace( '/[^a-z0-9_\-]/', '', $text ) ) : ''
		);
		Functions\when( 'user_can' )->justReturn( true );
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_known_tour_key_is_stored_without_security_admin_session() :void {
		$meta = $this->installEnvironment();

		$action = new PluginMarkTourFinished( $this->actionData( TourManager::TOUR_DASHBOARD ) );
		$action->process();

		$this->assertTrue( (bool)( $action->response()->payload()[ 'success' ] ?? false ) );
		$this->assertTrue( (bool)( $action->response()->payload()[ 'completed' ] ?? false ) );
		$this->assertSame( 1700001234, $meta->tours[ TourManager::TOUR_DASHBOARD ] ?? 0 );
	}

	public function test_unknown_tour_key_is_ignored() :void {
		$meta = $this->installEnvironment( [
			TourManager::TOUR_DASHBOARD => 1700000000,
		] );

		$action = new PluginMarkTourFinished( $this->actionData( 'unknown_tour' ) );
		$action->process();

		$this->assertTrue( (bool)( $action->response()->payload()[ 'success' ] ?? false ) );
		$this->assertFalse( (bool)( $action->response()->payload()[ 'completed' ] ?? true ) );
		$this->assertSame( [
			TourManager::TOUR_DASHBOARD => 1700000000,
		], $meta->tours );
	}

	private function installEnvironment( array $tours = [] ) :object {
		$meta = (object)[
			'tours' => $tours,
		];

		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700001234 ),
			'service_wpusers' => new class extends Users {
				public function isUserLoggedIn() :bool {
					return true;
				}

				public function getCurrentWpUser() {
					return (object)[ 'ID' => 1 ];
				}
			},
		] );
		PluginControllerInstaller::install( new PluginMarkTourFinishedControllerStub( new PluginMarkTourFinishedUserMetasStub( $meta ) ) );
		return $meta;
	}

	private function actionData( string $tourKey ) :array {
		return [
			'tour_key'           => $tourKey,
			'action_overrides'   => [
				Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED => false,
			],
		];
	}
}

class PluginMarkTourFinishedControllerStub extends Controller {

	public function __construct( object $userMetas ) {
		$this->cfg = (object)[
			'properties' => [
				'base_permissions' => 'manage_options',
			],
		];
		$this->this_req = (object)[
			'request_bypasses_all_restrictions' => false,
			'is_ip_blocked'                     => false,
			'wp_is_ajax'                        => true,
			'is_security_admin'                 => false,
		];
		$this->user_metas = $userMetas;
	}
}

class PluginMarkTourFinishedUserMetasStub {

	private object $meta;

	public function __construct( object $meta ) {
		$this->meta = $meta;
	}

	public function current() :object {
		return $this->meta;
	}
}
