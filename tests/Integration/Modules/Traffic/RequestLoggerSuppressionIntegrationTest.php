<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxBatchRequests,
	Actions\PluginBadgeClose,
	Actions\Render\Components\Traffic\TrafficLiveLogs,
	Actions\Render\Components\Widgets\DashboardLiveMonitorTicker
};
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
				'rest_api_root'            => \home_url( '/wp-json/' ),
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
				'rest_api_root'            => \home_url( '/wp-json/' ),
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
