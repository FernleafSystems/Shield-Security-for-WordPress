<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TourManager;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};

class TourManagerTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \preg_replace( '/[^a-z0-9_\-]/', '', $text ) ) : ''
		);
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_default_dashboard_route_launches_for_unseen_admin() :void {
		$this->installRequest( [] );
		$this->installController();

		$tour = ( new TourManager() )->getTour();

		$this->assertSame( TourManager::TOUR_DASHBOARD, $tour[ 'key' ] );
		$this->assertTrue( $tour[ 'is_available' ] );
		$this->assertContains( '[data-shield-tour="sidebar-menu"]', \array_column( $tour[ 'steps' ], 'selector' ) );
		$this->assertContains( '[data-shield-tour="dashboard-live-monitor"]', \array_column( $tour[ 'steps' ], 'selector' ) );
		$this->assertSame( 0.7, $tour[ 'options' ][ 'overlayOpacity' ] );
	}

	public function test_completed_dashboard_tour_suppresses_launch() :void {
		$this->installRequest( [] );
		$this->installController( true, true, [
			TourManager::TOUR_DASHBOARD => 1700000000,
		] );

		$this->assertFalse( ( new TourManager() )->getTour()[ 'is_available' ] );
	}

	public function test_force_tour_relaunches_completed_dashboard_tour_on_dashboard_only() :void {
		$this->installRequest( [ 'force_tour' => '1' ] );
		$this->installController( true, true, [
			TourManager::TOUR_DASHBOARD => 1700000000,
		] );
		$this->assertTrue( ( new TourManager() )->getTour()[ 'is_available' ] );

		$this->installRequest( [
			'force_tour'             => '1',
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_WIZARD,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_WIZARD_WELCOME,
		] );
		$this->installController( true, true, [
			TourManager::TOUR_DASHBOARD => 1700000000,
		] );
		$this->assertFalse( ( new TourManager() )->getTour()[ 'is_available' ] );
	}

	public function test_non_dashboard_and_restricted_routes_do_not_launch() :void {
		$this->installRequest( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_REPORTS,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_REPORTS_OVERVIEW,
		] );
		$this->installController();
		$this->assertFalse( ( new TourManager() )->getTour()[ 'is_available' ] );

		$this->installRequest( [] );
		$this->installController( true, false );
		$this->assertFalse( ( new TourManager() )->getTour()[ 'is_available' ] );
	}

	private function installRequest( array $query ) :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( $query ),
		] );
	}

	private function installController( bool $isPluginAdminPage = true, bool $isPluginAdmin = true, array $tours = [] ) :void {
		$meta = (object)[
			'tours' => $tours,
		];
		PluginControllerInstaller::install(
			new TourManagerControllerStub( $isPluginAdminPage, $isPluginAdmin, new TourManagerUserMetasStub( $meta ) )
		);
	}
}

class TourManagerControllerStub extends Controller {

	private bool $pluginAdminPage;
	private bool $pluginAdmin;

	public function __construct( bool $pluginAdminPage, bool $pluginAdmin, object $userMetas ) {
		$this->pluginAdminPage = $pluginAdminPage;
		$this->pluginAdmin = $pluginAdmin;
		$this->user_metas = $userMetas;
	}

	public function isPluginAdminPageRequest() :bool {
		return $this->pluginAdminPage;
	}

	public function isPluginAdmin() :bool {
		return $this->pluginAdmin;
	}
}

class TourManagerUserMetasStub {

	private object $meta;

	public function __construct( object $meta ) {
		$this->meta = $meta;
	}

	public function current() :object {
		return $this->meta;
	}
}
