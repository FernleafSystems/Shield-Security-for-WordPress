<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxRender,
	Actions\Render\Components\Widgets\WpDashboardSummary,
	Actions\ScansCheck,
	Actions\ScansStart
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\AssetsCustomizer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TourManager;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest,
	UnitTestUsers
};
use FernleafSystems\Wordpress\Services\Core\General;

class AssetsCustomizerTest extends BaseUnitTest {

	private const VALID_VIDEO_URL = 'https://vimeo.com/123456789';

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \preg_replace( '/[^a-z0-9_\-]/', '', $text ) ) : ''
		);
		Functions\when( 'wp_hash' )->justReturn( '1234567890abcdef' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'rest-nonce' );
		Functions\when( 'get_rest_url' )->alias(
			static fn( $siteID, string $path ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				return \is_array( $value ) ? \array_map( 'rawurlencode_deep', $value ) : \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static fn( array $data, string $url ) :string => $url.'?'.\http_build_query( $data )
		);
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_plugin_onboarding_asset_is_enqueued_when_dashboard_tour_available() :void {
		$this->installEnvironment();

		$assets = $this->buildCustomEnqueueAssets();

		$this->assertContains( 'plugin_onboarding', $assets );
		$this->assertNotContains( 'shield/tp/vimeo_player', $assets );
	}

	public function test_plugin_onboarding_asset_is_not_enqueued_when_dashboard_tour_completed() :void {
		$this->installEnvironment( [], [
			TourManager::TOUR_DASHBOARD => 1700000000,
		] );

		$assets = $this->buildCustomEnqueueAssets();

		$this->assertNotContains( 'plugin_onboarding', $assets );
	}

	public function test_plugin_onboarding_asset_is_not_enqueued_outside_dashboard_route() :void {
		$this->installEnvironment( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_REPORTS,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_REPORTS_OVERVIEW,
		] );

		$assets = $this->buildCustomEnqueueAssets();

		$this->assertNotContains( 'plugin_onboarding', $assets );
	}

	public function test_plugin_onboarding_data_is_localized_for_plugin_onboarding_handle() :void {
		$this->installEnvironment();

		$onboardingComps = $this->getLocalisedCompsForHandle( 'plugin_onboarding' );

		$this->assertArrayHasKey( 'plugin_onboarding', $onboardingComps );
		$this->assertSame(
			TourManager::TOUR_DASHBOARD,
			$onboardingComps[ 'plugin_onboarding' ][ 'vars' ][ 'tour' ][ 'key' ] ?? ''
		);
		$this->assertArrayHasKey( 'finished', $onboardingComps[ 'plugin_onboarding' ][ 'ajax' ] ?? [] );
	}

	public function test_plugin_onboarding_data_is_not_localized_when_tour_unavailable() :void {
		$this->installEnvironment( [], [
			TourManager::TOUR_DASHBOARD => 1700000000,
		] );

		$this->assertSame( [], $this->getLocalisedCompsForHandle( 'plugin_onboarding' ) );
	}

	public function test_main_handle_localizes_navigation_component_without_dead_dynamic_load_payload() :void {
		$this->installEnvironment();

		$naviComp = $this->getComponentDefinition( 'navi' );

		$this->assertSame( 'navi', $naviComp[ 'key' ] ?? '' );
		$this->assertContains( 'main', $naviComp[ 'handles' ] ?? [] );
		$this->assertArrayNotHasKey(
			'dynamic_load',
			\is_callable( $naviComp[ 'data' ] ?? null ) ? \call_user_func( $naviComp[ 'data' ] )[ 'ajax' ] ?? [] : $naviComp[ 'data' ][ 'ajax' ] ?? []
		);
	}

	public function test_dashboard_widget_component_defines_wpadmin_ajax_render_contract() :void {
		$this->installEnvironment();

		$dashboardWidgetComp = $this->getComponentDefinition( 'dashboard_widget' );

		$this->assertContains( 'wpadmin', $dashboardWidgetComp[ 'handles' ] ?? [] );
		$renderAjax = $dashboardWidgetComp[ 'data' ][ 'ajax' ][ 'render' ] ?? [];

		$this->assertSame( AjaxRender::SLUG, $renderAjax[ ActionData::FIELD_EXECUTE ] ?? '' );
		$this->assertSame( WpDashboardSummary::SLUG, $renderAjax[ 'render_slug' ] ?? '' );
		$this->assertIsString( $dashboardWidgetComp[ 'data' ][ 'strings' ][ 'load_failed' ] ?? null );
		$this->assertNotSame( '', $dashboardWidgetComp[ 'data' ][ 'strings' ][ 'load_failed' ] ?? '' );
	}

	public function test_scans_component_localizes_only_scan_page_ajax_actions() :void {
		$this->installEnvironment();

		$scansComp = $this->getComponentDefinition( 'scans' );
		$scansData = \is_callable( $scansComp[ 'data' ] ?? null ) ? \call_user_func( $scansComp[ 'data' ] ) : [];
		$ajax = \is_array( $scansData[ 'ajax' ] ?? null ) ? $scansData[ 'ajax' ] : [];

		$this->assertEqualsCanonicalizing( [ 'check', 'start' ], \array_keys( $ajax ) );
		$this->assertSame( ScansCheck::SLUG, $ajax[ 'check' ][ ActionData::FIELD_EXECUTE ] ?? null );
		$this->assertSame( ScansStart::SLUG, $ajax[ 'start' ][ ActionData::FIELD_EXECUTE ] ?? null );
	}

	private function installEnvironment( array $query = [], array $completedTours = [] ) :void {
		$query = \array_merge( [
			'page'                  => 'icwp-wpsf-plugin',
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_DASHBOARD,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
		], $query );

		ServicesState::installItems( [
			'service_request'  => new UnitTestRequest( $query ),
			'service_wpgeneral'=> new class extends General {
				public function isAjax() :bool {
					return false;
				}

				public function ajaxURL() :string {
					return '/wp-admin/admin-ajax.php';
				}
			},
			'service_wpusers'  => new UnitTestUsers( 1 ),
		] );

		PluginControllerInstaller::install(
			new AssetsCustomizerControllerStub(
				true,
				true,
				new AssetsCustomizerUserMetasStub( (object)[ 'tours' => $completedTours ] ),
				self::VALID_VIDEO_URL
			)
		);

		( new AssetsCustomizer() )->execute();
	}

	private function buildCustomEnqueueAssets() :array {
		$method = new \ReflectionMethod( AssetsCustomizer::class, 'buildCustomEnqueueAssets' );
		$method->setAccessible( true );
		$assets = $method->invoke( new AssetsCustomizer(), [] );
		return \is_array( $assets ) ? $assets : [];
	}

	private function getLocalisedCompsForHandle( string $handle ) :array {
		$method = new \ReflectionMethod( AssetsCustomizer::class, 'buildCustomLocalisations' );
		$method->setAccessible( true );
		$locals = $method->invoke( new AssetsCustomizer(), [], Enqueue::PLUGIN_ADMIN_HOOK_SUFFIX, [ $handle ] );
		foreach ( \is_array( $locals ) ? $locals : [] as $local ) {
			if ( \is_array( $local ) && ( $local[ 0 ] ?? '' ) === $handle ) {
				return \is_array( $local[ 2 ][ 'comps' ] ?? null ) ? $local[ 2 ][ 'comps' ] : [];
			}
		}
		return [];
	}

	private function getComponentDefinition( string $key ) :array {
		$method = new \ReflectionMethod( AssetsCustomizer::class, 'components' );
		$method->setAccessible( true );
		$components = $method->invoke( new AssetsCustomizer() );
		return \is_array( $components[ $key ] ?? null ) ? $components[ $key ] : [];
	}
}

class AssetsCustomizerControllerStub extends Controller {

	private bool $pluginAdminPage;
	private bool $pluginAdmin;

	public function __construct( bool $pluginAdminPage, bool $pluginAdmin, object $userMetas, string $dashboardVideoURL ) {
		$this->pluginAdminPage = $pluginAdminPage;
		$this->pluginAdmin = $pluginAdmin;
		$this->user_metas = $userMetas;
		$this->cfg = (object)[
			'configuration' => new AssetsCustomizerConfigStub( [
				'dashboard_intro_video_url_v22' => $dashboardVideoURL,
			] ),
		];
		$this->comps = (object)[
			'scans_queue' => new class {
				public function hasRunningScans() :bool {
					return false;
				}
			},
		];
		$this->plugin_urls = new class {
			public function actionsQueueScans() :string {
				return '/wp-admin/admin.php?page=icwp-wpsf-plugin&nav=scans&nav_sub=overview';
			}
		};
	}

	public function isPluginAdminPageRequest() :bool {
		return $this->pluginAdminPage;
	}

	public function isPluginAdmin() :bool {
		return $this->pluginAdmin;
	}

	public function isPremiumActive() :bool {
		return false;
	}
}

class AssetsCustomizerUserMetasStub {

	private object $meta;

	public function __construct( object $meta ) {
		$this->meta = $meta;
	}

	public function current() :object {
		return $this->meta;
	}
}

class AssetsCustomizerConfigStub {

	private array $defs;

	public function __construct( array $defs ) {
		$this->defs = $defs;
	}

	public function def( string $key ) {
		return $this->defs[ $key ] ?? null;
	}
}
