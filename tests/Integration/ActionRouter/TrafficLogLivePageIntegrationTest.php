<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\TrafficLiveLog_SetEnabled,
	Actions\Render\PluginAdminPages\PageInvestigateLanding,
	Actions\Render\PageAdminPluginRouteResolver,
	Actions\Render\PluginAdminPages\TrafficLogLivePanelBody,
	Constants,
	Exceptions\SecurityAdminRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class TrafficLogLivePageIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'enable_live_log',
			'live_log_started_at',
		] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	private function renderLiveTrafficPage() :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_TRAFFIC,
			PluginNavs::SUBNAV_LIVE
		);
	}

	private function renderLiveTrafficPanelBody() :array {
		return $this->processActionPayloadWithAdminBypass( TrafficLogLivePanelBody::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_OVERVIEW,
		] );
	}

	private function requireRenderData( array $payload ) :array {
		$this->assertArrayHasKey( 'render_data', $payload );
		$this->assertIsArray( $payload[ 'render_data' ] );

		return $payload[ 'render_data' ];
	}

	private function liveLogControl( array $renderData ) :array {
		$this->assertArrayHasKey( 'vars', $renderData );
		$this->assertIsArray( $renderData[ 'vars' ] );
		$this->assertArrayHasKey( 'live_log_control', $renderData[ 'vars' ] );
		$this->assertIsArray( $renderData[ 'vars' ][ 'live_log_control' ] );

		return $renderData[ 'vars' ][ 'live_log_control' ];
	}

	private function assertLiveLogFailurePayloadContract( array $payload ) :void {
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertArrayHasKey( 'is_enabled', $payload );
		$this->assertArrayHasKey( 'time_remaining', $payload );
		$this->assertFalse( (bool)$payload[ 'success' ] );
		$this->assertFalse( (bool)$payload[ 'page_reload' ] );
		$this->assertIsString( $payload[ 'message' ] );
		$this->assertIsInt( $payload[ 'time_remaining' ] );
	}

	public function test_legacy_live_route_resolves_to_the_active_investigation_panel() :void {
		$this->enablePremiumCapabilities( [ 'traffic_live_log' ] );
		$route = ( new PageAdminPluginRouteResolver() )->resolve( [
			Constants::NAV_ID => PluginNavs::NAV_TRAFFIC,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_LIVE,
		], true );
		$this->assertSame( PageInvestigateLanding::class, $route[ 'delegate_action' ] );
		$this->assertSame( PluginNavs::NAV_ACTIVITY, $route[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_OVERVIEW, $route[ 'subnav' ] );
		$this->assertSame( 'live_traffic', $route[ 'delegate_payload' ][ 'subject' ] ?? null );
		$landing = $this->processActionPayloadWithAdminBypass( $route[ 'delegate_action' ]::SLUG, $route[ 'delegate_payload' ] );
		$this->assertRouteRenderOutputHealthy( $landing, 'live investigation landing' );
		$this->assertSame( 1, $landing[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'active_index' ] ?? null );
		$routePayload = $this->renderLiveTrafficPage();
		$this->assertRouteRenderOutputHealthy( $routePayload, 'legacy live route' );
		$routeRenderData = $this->requireRenderData( $routePayload );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_OVERVIEW, $routeRenderData[ 'vars' ][ 'active_module_settings' ] );
	}

	public function test_live_traffic_panel_contract_exposes_panel_owned_switch_state() :void {
		$this->enablePremiumCapabilities( [ 'traffic_live_log' ] );
		$now = Services::Request()->ts();
		$this->requireController()->opts
			->optSet( 'enable_live_log', 'Y' )
			->optSet( 'live_log_started_at', $now )
			->store();

		$payload = $this->renderLiveTrafficPanelBody();
		$this->assertRouteRenderOutputHealthy( $payload, 'live traffic control' );
		$renderData = $this->requireRenderData( $payload );
		$control = $this->liveLogControl( $renderData );

		$this->assertSame( 'TrafficLiveLogToggle', $control[ 'id' ] );
		$this->assertSame( 'investigate_panel', $control[ 'owner' ] );
		$this->assertTrue( (bool)$control[ 'is_available' ] );
		$this->assertTrue( (bool)$control[ 'is_enabled' ] );
		$this->assertGreaterThan( 0, $control[ 'time_remaining' ] );
		$this->assertArrayNotHasKey( 'is_enabled', $renderData[ 'flags' ] );
	}

	public function test_live_traffic_panel_exposes_stopped_capture_without_remaining_time() :void {
		$this->enablePremiumCapabilities( [ 'traffic_live_log' ] );
		$this->requireController()->opts
			->optSet( 'enable_live_log', 'N' )
			->optSet( 'live_log_started_at', 0 )
			->store();

		$control = $this->liveLogControl( $this->requireRenderData( $this->renderLiveTrafficPanelBody() ) );
		$this->assertTrue( $control[ 'is_available' ] );
		$this->assertFalse( $control[ 'is_enabled' ] );
		$this->assertSame( 0, $control[ 'time_remaining' ] );
	}

	public function test_live_traffic_panel_contract_disables_switch_when_capability_unavailable() :void {
		$this->requireController()->opts
			->optSet( 'enable_live_log', 'N' )
			->optSet( 'live_log_started_at', 0 )
			->store();

		$renderData = $this->requireRenderData( $this->renderLiveTrafficPanelBody() );
		$control = $this->liveLogControl( $renderData );

		$this->assertFalse( (bool)$control[ 'is_available' ] );
		$this->assertFalse( (bool)$control[ 'is_enabled' ] );
		$this->assertSame( 'investigate_panel', $control[ 'owner' ] );
		$this->assertSame( 0, $control[ 'time_remaining' ] );
	}

	public function test_live_log_toggle_action_enables_live_logging() :void {
		$this->enablePremiumCapabilities( [ 'traffic_live_log' ] );
		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_live_log', 'N' )
			->optSet( 'live_log_started_at', 0 )
			->store();
		$before = Services::Request()->ts();

		$payload = ( new ActionProcessor() )->processAction( TrafficLiveLog_SetEnabled::SLUG, [
			'enabled' => 'Y',
		] )->payload();
		$after = Services::Request()->ts();

		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertFalse( (bool)$payload[ 'page_reload' ] );
		$this->assertTrue( (bool)$payload[ 'is_enabled' ] );
		$this->assertNotEmpty( $payload[ 'message' ] );
		$this->assertGreaterThan( 0, $payload[ 'time_remaining' ] );
		$this->assertSame( 'Y', (string)$con->opts->optGet( 'enable_live_log' ) );
		$startedAt = (int)$con->opts->optGet( 'live_log_started_at' );
		$this->assertGreaterThanOrEqual( $before, $startedAt );
		$this->assertLessThanOrEqual( $after, $startedAt );
	}

	public function test_live_log_toggle_action_disables_live_logging() :void {
		$this->enablePremiumCapabilities( [ 'traffic_live_log' ] );
		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_live_log', 'Y' )
			->optSet( 'live_log_started_at', Services::Request()->ts() )
			->store();

		$payload = ( new ActionProcessor() )->processAction( TrafficLiveLog_SetEnabled::SLUG, [
			'enabled' => 'N',
		] )->payload();

		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertFalse( (bool)$payload[ 'page_reload' ] );
		$this->assertFalse( (bool)$payload[ 'is_enabled' ] );
		$this->assertNotEmpty( $payload[ 'message' ] );
		$this->assertSame( 0, $payload[ 'time_remaining' ] );
		$this->assertSame( 'N', (string)$con->opts->optGet( 'enable_live_log' ) );
		$this->assertSame( 0, (int)$con->opts->optGet( 'live_log_started_at' ) );
	}

	public function test_live_log_toggle_action_requires_security_admin_without_mutation() :void {
		$this->enablePremiumCapabilities( [ 'traffic_live_log' ] );
		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_live_log', 'N' )
			->optSet( 'live_log_started_at', 0 )
			->store();
		$isSecurityAdmin = (bool)$con->this_req->is_security_admin;
		$con->this_req->is_security_admin = false;

		try {
			$this->expectException( SecurityAdminRequiredException::class );
			( new ActionProcessor() )->processAction( TrafficLiveLog_SetEnabled::SLUG, [
				'enabled' => 'Y',
			] );
		}
		finally {
			$con->this_req->is_security_admin = $isSecurityAdmin;
			$this->assertSame( 'N', (string)$con->opts->optGet( 'enable_live_log' ) );
			$this->assertSame( 0, (int)$con->opts->optGet( 'live_log_started_at' ) );
		}
	}

	public function test_live_log_toggle_action_rejects_invalid_enabled_payload_without_mutation() :void {
		$this->enablePremiumCapabilities( [ 'traffic_live_log' ] );
		$con = $this->requireController();
		$startedAt = Services::Request()->ts();
		$con->opts
			->optSet( 'enable_live_log', 'Y' )
			->optSet( 'live_log_started_at', $startedAt )
			->store();

		$payload = ( new ActionProcessor() )->processAction( TrafficLiveLog_SetEnabled::SLUG, [
			'enabled' => 'maybe',
		] )->payload();

		$this->assertLiveLogFailurePayloadContract( $payload );
		$this->assertSame( 'Y', (string)$con->opts->optGet( 'enable_live_log' ) );
		$this->assertSame( $startedAt, (int)$con->opts->optGet( 'live_log_started_at' ) );
	}

	public function test_live_log_toggle_action_rejects_invalid_payload_without_initializing_missing_started_at() :void {
		$this->enablePremiumCapabilities( [ 'traffic_live_log' ] );
		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_live_log', 'Y' )
			->optSet( 'live_log_started_at', 0 )
			->store();

		$payload = ( new ActionProcessor() )->processAction( TrafficLiveLog_SetEnabled::SLUG, [
			'enabled' => 'maybe',
		] )->payload();

		$this->assertLiveLogFailurePayloadContract( $payload );
		$this->assertFalse( (bool)$payload[ 'is_enabled' ] );
		$this->assertSame( 0, $payload[ 'time_remaining' ] );
		$this->assertSame( 'Y', (string)$con->opts->optGet( 'enable_live_log' ) );
		$this->assertSame( 0, (int)$con->opts->optGet( 'live_log_started_at' ) );
	}

	public function test_live_log_toggle_action_rejects_enable_when_capability_unavailable() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_live_log', 'N' )
			->optSet( 'live_log_started_at', 0 )
			->store();

		$payload = ( new ActionProcessor() )->processAction( TrafficLiveLog_SetEnabled::SLUG, [
			'enabled' => 'Y',
		] )->payload();

		$this->assertLiveLogFailurePayloadContract( $payload );
		$this->assertFalse( (bool)$payload[ 'is_enabled' ] );
		$this->assertSame( 'N', (string)$con->opts->optGet( 'enable_live_log' ) );
		$this->assertSame( 0, (int)$con->opts->optGet( 'live_log_started_at' ) );
	}

	public function test_live_log_toggle_action_rejects_unavailable_capability_without_initializing_missing_started_at() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_live_log', 'Y' )
			->optSet( 'live_log_started_at', 0 )
			->store();

		$payload = ( new ActionProcessor() )->processAction( TrafficLiveLog_SetEnabled::SLUG, [
			'enabled' => 'N',
		] )->payload();

		$this->assertLiveLogFailurePayloadContract( $payload );
		$this->assertFalse( (bool)$payload[ 'is_enabled' ] );
		$this->assertSame( 0, $payload[ 'time_remaining' ] );
		$this->assertSame( 'Y', (string)$con->opts->optGet( 'enable_live_log' ) );
		$this->assertSame( 0, (int)$con->opts->optGet( 'live_log_started_at' ) );
	}
}
