<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxBatchRequests,
	Actions\PluginBadgeClose,
	Actions\Render\Components\Traffic\TrafficLiveLogs,
	Actions\Render\Components\Widgets\DashboardLiveMonitorTicker
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogger;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class RequestLoggerSuppressionIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );

		$this->requestSnapshot = $this->snapshotCurrentRequestState();

		$this->requireController()->opts
			 ->optSet( 'enable_logger', 'Y' )
			 ->optSet( 'enable_live_log', 'N' )
			 ->optSet( 'live_log_started_at', 0 );
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );

		parent::tear_down();
	}

	public function test_rest_route_normalisation_supports_permalink_and_query_forms() :void {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/wp-json/wp/v2/users/me?context=edit&_locale=user',
			],
			[],
			[],
			[
				'wp_is_permalinks_enabled' => true,
				'rest_api_root'            => $this->permalinkRestRoot(),
			]
		);
		$this->assertSame( 'wp/v2/users/me', $this->requireController()->this_req->getRestRoute() );

		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/?rest_route=/wp/v2/users/me',
			],
			[
				'rest_route' => '/wp/v2/users/me',
				'context'    => 'edit',
				'_locale'    => 'user',
			],
			[],
			[
				'wp_is_permalinks_enabled' => false,
			]
		);
		$this->assertSame( 'wp/v2/users/me', $this->requireController()->this_req->getRestRoute() );
	}

	public function test_logged_in_users_me_rest_requests_are_suppressed() :void {
		$this->loginAsAdministrator();
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/wp-json/wp/v2/users/me?context=edit&_locale=user',
			],
			[],
			[],
			[
				'wp_is_permalinks_enabled' => true,
				'rest_api_root'            => $this->permalinkRestRoot(),
			]
		);

		$this->assertFalse( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_logged_out_users_me_rest_requests_remain_loggable() :void {
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/?rest_route=/wp/v2/users/me',
			],
			[
				'rest_route' => '/wp/v2/users/me',
			]
		);

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_live_monitor_ajax_render_requests_are_suppressed() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldAjaxRequest(
			ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
			true
		);

		$this->assertFalse( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_logged_in_admin_heartbeat_requests_are_suppressed() :void {
		$this->loginAsAdministrator();
		$this->applyCurrentHeartbeatRequest( [
			'screen_id' => 'toplevel_page_icwp-wpsf-plugin',
		] );

		$this->assertFalse( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_logged_in_front_heartbeat_requests_remain_loggable() :void {
		$this->loginAsAdministrator();
		$this->applyCurrentHeartbeatRequest( [
			'screen_id' => 'front',
		] );

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_logged_in_heartbeat_requests_without_screen_id_remain_loggable() :void {
		$this->loginAsAdministrator();
		$this->applyCurrentHeartbeatRequest();

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_logged_out_admin_heartbeat_requests_remain_loggable() :void {
		\wp_set_current_user( 0 );
		$this->applyCurrentHeartbeatRequest( [
			'screen_id' => 'toplevel_page_icwp-wpsf-plugin',
		] );

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_logged_in_get_heartbeat_requests_remain_loggable() :void {
		$this->loginAsAdministrator();
		$this->applyCurrentHeartbeatRequest( [
			'screen_id' => 'toplevel_page_icwp-wpsf-plugin',
		], 'GET' );

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_simple_security_admin_dashboard_get_requests_are_suppressed() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldPluginPageRequest(
			PluginNavs::NAV_DASHBOARD,
			PluginNavs::SUBNAV_DASHBOARD_OVERVIEW
		);

		$this->assertFalse( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_simple_security_admin_non_dashboard_shield_get_requests_are_suppressed() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldPluginPageRequest(
			PluginNavs::NAV_REPORTS,
			PluginNavs::SUBNAV_REPORTS_OVERVIEW
		);

		$this->assertFalse( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_simple_non_security_admin_shield_get_requests_remain_loggable() :void {
		$this->loginAsAdministrator();
		$this->applyCurrentShieldPluginPageRequest(
			PluginNavs::NAV_DASHBOARD,
			PluginNavs::SUBNAV_DASHBOARD_OVERVIEW
		);

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_shield_get_requests_with_extra_query_state_remain_loggable() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldPluginPageRequest(
			PluginNavs::NAV_DASHBOARD,
			PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
			[
				'extra' => '1',
			]
		);

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_shield_get_requests_with_invalid_route_remain_loggable() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldPluginPageRequest(
			PluginNavs::NAV_DASHBOARD,
			'invalid-subnav'
		);

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_shield_like_get_requests_with_wrong_page_slug_remain_loggable() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldPluginPageRequest(
			PluginNavs::NAV_DASHBOARD,
			PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
			[
				'page' => 'other-plugin-page',
			]
		);

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_shield_like_get_requests_outside_admin_context_remain_loggable() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldPluginPageRequest(
			PluginNavs::NAV_DASHBOARD,
			PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
			[],
			[
				'REQUEST_URI' => '/?page=icwp-wpsf-plugin&nav=dashboard&nav_sub=overview',
			],
			[
				'path'         => '/',
				'script_name'  => 'index.php',
				'wp_is_admin'  => false,
			]
		);

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_live_monitor_batch_requests_are_suppressed_only_when_all_nested_requests_match() :void {
		$this->loginAsSecurityAdmin();

		$allSuppressible = ActionData::Build( AjaxBatchRequests::class, true, [
			'requests' => [
				[
					'id'      => 'ticker',
					'request' => ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
				],
				[
					'id'      => 'traffic',
					'request' => ActionData::BuildAjaxRender( TrafficLiveLogs::class, [ 'limit' => 12 ] ),
				],
			],
		] );

		$this->applyCurrentShieldAjaxRequest( $allSuppressible, true );
		$this->assertFalse( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );

		$mixedBatch = ActionData::Build( AjaxBatchRequests::class, true, [
			'requests' => [
				[
					'id'      => 'ticker',
					'request' => ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
				],
				[
					'id'      => 'other',
					'request' => ActionData::Build( PluginBadgeClose::class ),
				],
			],
		] );

		$this->applyCurrentShieldAjaxRequest( $mixedBatch, true );
		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_empty_live_monitor_batch_requests_remain_loggable() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldAjaxRequest(
			ActionData::Build( AjaxBatchRequests::class, true, [
				'requests' => [],
			] ),
			true
		);

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_malformed_live_monitor_batch_requests_remain_loggable() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldAjaxRequest(
			ActionData::Build( AjaxBatchRequests::class, true, [
				'requests' => [
					[
						'id'      => 'broken',
						'request' => 'not-an-array',
					],
				],
			] ),
			true
		);

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_programmatic_filter_can_disable_suppressions() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldAjaxRequest(
			ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
			true
		);

		$callback = fn( bool $enabled ) => false;
		add_filter( RequestLogger::FILTER_BUILTIN_SUPPRESSIONS_ENABLED, $callback, 10, 1 );

		try {
			$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
		}
		finally {
			remove_filter( RequestLogger::FILTER_BUILTIN_SUPPRESSIONS_ENABLED, $callback, 10 );
		}
	}

	public function test_live_logging_bypasses_suppressions() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldAjaxRequest(
			ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
			true
		);

		$this->enablePremiumCapabilities( [ 'traffic_live_log' ] );
		$this->requireController()->opts
			 ->optSet( 'enable_live_log', 'Y' )
			 ->optSet( 'live_log_started_at', \time() );

		try {
			$this->assertSame( 'Y', $this->requireController()->opts->optGet( 'enable_live_log' ) );
			$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
		}
		finally {
			$this->requireController()->opts
				 ->optSet( 'enable_live_log', 'N' )
				 ->optSet( 'live_log_started_at', 0 );
		}
	}

	public function test_dependent_log_creation_still_writes_suppressible_requests() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldAjaxRequest(
			ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
			true
		);

		$before = $this->rowCount( 'req_logs' );
		$this->withTrafficLoggingEnabled( function () {
			( new RequestLogger() )->createDependentLog();
		} );

		$this->assertSame( $before + 1, $this->rowCount( 'req_logs' ) );
	}

	private function rowCount( string $dbKey ) :int {
		global $wpdb;
		return (int)$wpdb->get_var(
			sprintf(
				'SELECT COUNT(*) FROM `%s`',
				$this->requireController()->db_con->{$dbKey}->getTable()
			)
		);
	}

	private function permalinkRestRoot() :string {
		$prefix = \function_exists( 'rest_get_url_prefix' ) ? \rest_get_url_prefix() : 'wp-json';
		return \home_url( '/'.\trim( $prefix, '/' ).'/' );
	}

	private function applyCurrentHeartbeatRequest( array $post = [], string $method = 'POST' ) :void {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => $method,
				'REQUEST_URI'    => '/wp-admin/admin-ajax.php',
			],
			[],
			\array_merge( [
				'action'    => 'heartbeat',
				'interval'  => '60',
				'_nonce'    => 'integration-test-heartbeat',
				'has_focus' => 'true',
			], $post ),
			[
				'path'       => '/wp-admin/admin-ajax.php',
				'wp_is_ajax' => true,
			]
		);
	}

	private function applyCurrentShieldPluginPageRequest(
		string $nav,
		string $subNav,
		array $extraQuery = [],
		array $server = [],
		array $requestOverrides = []
	) :void {
		$query = \array_merge( [
			'page'    => $this->requireController()->plugin_urls->rootAdminPageSlug(),
			PluginNavs::FIELD_NAV    => $nav,
			PluginNavs::FIELD_SUBNAV => $subNav,
		], $extraQuery );

		$uri = '/wp-admin/admin.php?page='.$query[ 'page' ]
			   .'&'.PluginNavs::FIELD_NAV.'='.$nav
			   .'&'.PluginNavs::FIELD_SUBNAV.'='.$subNav;

		if ( !empty( $extraQuery ) ) {
			$uri .= '&'.\http_build_query( $extraQuery, '', '&', \PHP_QUERY_RFC3986 );
		}

		$this->applyCurrentRequestState(
			\array_merge( [
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => $uri,
				'SCRIPT_NAME'    => '/wp-admin/admin.php',
				'SCRIPT_FILENAME'=> '/wp-admin/admin.php',
				'PHP_SELF'       => '/wp-admin/admin.php',
			], $server ),
			$query,
			[],
			\array_merge( [
				'path'             => '/wp-admin/admin.php',
				'script_name'      => 'admin.php',
				'wp_is_admin'      => true,
				'wp_is_ajax'       => false,
			], $requestOverrides )
		);
	}

	private function withTrafficLoggingEnabled( callable $callback ) {
		add_filter( 'shield/is_log_traffic', '__return_true', \PHP_INT_MAX );

		try {
			return $callback();
		}
		finally {
			remove_filter( 'shield/is_log_traffic', '__return_true', \PHP_INT_MAX );
		}
	}
}
