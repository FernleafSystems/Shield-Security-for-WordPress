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

	private const DEFAULT_VIDEO_URL = 'https://vimeo.com/986378588/fde2b1c5f7?share=copy&fl=sv&fe=ci';

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
		$this->assertStringNotContainsString( 'dashboard_v'.'1', (string)\json_encode( $tour ) );
	}

	public function test_completed_dashboard_tour_suppresses_launch() :void {
		$this->installRequest( [] );
		$this->installController( true, true, [
			TourManager::TOUR_DASHBOARD => 1700000000,
		] );

		$this->assertFalse( ( new TourManager() )->getTour()[ 'is_available' ] );
	}

	public function test_old_dashboard_completion_does_not_suppress_launch() :void {
		$this->installRequest( [] );
		$this->installController( true, true, [
			'dashboard_v'.'1' => 1700000000,
		] );

		$tour = ( new TourManager() )->getTour();

		$this->assertSame( TourManager::TOUR_DASHBOARD, $tour[ 'key' ] );
		$this->assertTrue( $tour[ 'is_available' ] );
	}

	public function test_force_tour_relaunches_completed_dashboard_tour_on_dashboard_only() :void {
		$this->installRequest( [ 'force_tour' => '1' ] );
		$this->installController( true, true, [
			TourManager::TOUR_DASHBOARD => 1700000000,
		] );
		$this->assertTrue( ( new TourManager() )->getTour()[ 'is_available' ] );

		$this->installRequest( [ 'force_tour' => TourManager::TOUR_DASHBOARD ] );
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

	public function test_default_dashboard_video_url_enables_video_modal_with_normalized_embed_url() :void {
		$this->installRequest( [] );
		$this->installController();

		$videoModal = ( new TourManager() )->getTour()[ 'video_modal' ];
		$urlParts = \parse_url( $videoModal[ 'embed_url' ] );
		$query = [];
		\parse_str( (string)( $urlParts[ 'query' ] ?? '' ), $query );

		$this->assertTrue( $videoModal[ 'is_enabled' ] );
		$this->assertSame( 'player.vimeo.com', $urlParts[ 'host' ] ?? '' );
		$this->assertSame( '/video/986378588', $urlParts[ 'path' ] ?? '' );
		$this->assertSame( 'fde2b1c5f7', $query[ 'h' ] ?? '' );
		$this->assertArrayNotHasKey( 'share', $query );
		$this->assertArrayNotHasKey( 'fl', $query );
		$this->assertArrayNotHasKey( 'fe', $query );
	}

	public function test_invalid_dashboard_video_url_disables_video_modal_only() :void {
		$this->installRequest( [] );
		$this->installController( true, true, [], 'https://example.com/video' );

		$tour = ( new TourManager() )->getTour();

		$this->assertTrue( $tour[ 'is_available' ] );
		$this->assertFalse( $tour[ 'video_modal' ][ 'is_enabled' ] );
		$this->assertSame( '', $tour[ 'video_modal' ][ 'embed_url' ] );
	}

	public function test_blank_dashboard_video_url_disables_video_modal_only() :void {
		$this->installRequest( [] );
		$this->installController( true, true, [], '' );

		$tour = ( new TourManager() )->getTour();

		$this->assertTrue( $tour[ 'is_available' ] );
		$this->assertFalse( $tour[ 'video_modal' ][ 'is_enabled' ] );
	}

	public function test_supported_vimeo_url_forms_are_normalized() :void {
		$this->installRequest( [] );

		foreach ( [
			[ 'https://vimeo.com/123456789', '/video/123456789', '' ],
			[ 'https://vimeo.com/123456789?h=abc123', '/video/123456789', 'abc123' ],
			[ 'https://player.vimeo.com/video/123456789?h=abc123', '/video/123456789', 'abc123' ],
		] as $case ) {
			$this->installController( true, true, [], $case[ 0 ] );

			$videoModal = ( new TourManager() )->getTour()[ 'video_modal' ];
			$urlParts = \parse_url( $videoModal[ 'embed_url' ] );
			$query = [];
			\parse_str( (string)( $urlParts[ 'query' ] ?? '' ), $query );

			$this->assertTrue( $videoModal[ 'is_enabled' ] );
			$this->assertSame( 'player.vimeo.com', $urlParts[ 'host' ] ?? '' );
			$this->assertSame( $case[ 1 ], $urlParts[ 'path' ] ?? '' );
			$this->assertSame( $case[ 2 ], $query[ 'h' ] ?? '' );
		}
	}

	private function installRequest( array $query ) :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( $query ),
		] );
	}

	private function installController(
		bool $isPluginAdminPage = true,
		bool $isPluginAdmin = true,
		array $tours = [],
		?string $dashboardVideoURL = null
	) :void {
		$meta = (object)[
			'tours' => $tours,
		];
		PluginControllerInstaller::install(
			new TourManagerControllerStub(
				$isPluginAdminPage,
				$isPluginAdmin,
				new TourManagerUserMetasStub( $meta ),
				$dashboardVideoURL ?? self::DEFAULT_VIDEO_URL
			)
		);
	}
}

class TourManagerControllerStub extends Controller {

	private bool $pluginAdminPage;
	private bool $pluginAdmin;

	public function __construct( bool $pluginAdminPage, bool $pluginAdmin, object $userMetas, string $dashboardVideoURL ) {
		$this->pluginAdminPage = $pluginAdminPage;
		$this->pluginAdmin = $pluginAdmin;
		$this->user_metas = $userMetas;
		$this->opts = new TourManagerOptsStub( [
			'dashboard_intro_video_url' => $dashboardVideoURL,
		] );
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

class TourManagerOptsStub {

	public function __construct( private array $opts ) {
	}

	public function optGet( string $key ) {
		return $this->opts[ $key ] ?? '';
	}
}
