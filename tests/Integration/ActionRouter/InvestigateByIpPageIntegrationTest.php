<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\InvestigateByIpPanelBody,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByIpPageIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'activity_logs' );
		$this->requireDb( 'user_meta' );

		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderByIpPage( string $ip = '' ) :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_IP,
			$ip !== '' ? [ 'analyse_ip' => $ip ] : []
		);
	}

	private function renderByIpPanelBody( string $ip = '' ) :array {
		$params = [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_IP,
		];
		if ( $ip !== '' ) {
			$params[ 'analyse_ip' ] = $ip;
		}
		return $this->processActionPayloadWithAdminBypass( InvestigateByIpPanelBody::SLUG, $params );
	}

	public function test_valid_ip_lookup_renders_ip_analysis_container() :void {
		$renderData = (array)( $this->renderByIpPanelBody( '203.0.113.88' )[ 'render_data' ] ?? [] );
		$vars = (array)( $renderData[ 'vars' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame( '203.0.113.88', (string)( $vars[ 'analyse_ip' ] ?? '' ) );
		$routePayload = $this->renderByIpPage( '203.0.113.88' );
		$this->assertRouteRenderOutputHealthy( $routePayload, 'activity by-ip route' );
		$this->assertPluginAdminShellRouteState( $routePayload, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
	}

	public function test_no_lookup_route_preloads_ip_panel_lookup_form() :void {
		$routePayload = $this->renderByIpPage();
		$this->assertRouteRenderOutputHealthy( $routePayload, 'activity by-ip route without lookup' );
		$this->assertPluginAdminShellRouteState( $routePayload, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$renderData = (array)( $this->renderByIpPanelBody()[ 'render_data' ] ?? [] );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_BY_IP, (string)( $renderData[ 'vars' ][ 'lookup_route' ][ Constants::NAV_SUB_ID ] ?? '' ) );
		$this->assertSame( 'shield-investigate-ip-lookup-analyse_ip-control', (string)( $renderData[ 'vars' ][ 'lookup_field' ][ 'control_id' ] ?? '' ) );
	}

	public function test_panel_body_action_preserves_ip_subject_contract() :void {
		$payload = $this->renderByIpPanelBody( '203.0.113.88' );
		$this->assertRouteRenderOutputHealthy(
			$payload,
			'investigate by-ip panel body action'
		);
		$renderData = (array)( $payload[ 'render_data' ] ?? [] );
		$this->assertSame( '/wpadmin/components/investigate/ip_body.twig', (string)( $payload[ 'render_template' ] ?? '' ) );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertSame( '203.0.113.88', (string)( $renderData[ 'vars' ][ 'analyse_ip' ] ?? '' ) );
		$this->assertSame( '203.0.113.88', (string)( $renderData[ 'vars' ][ 'subject_header' ][ 'title' ] ?? '' ) );
		$this->assertSame( 'ip', (string)( $renderData[ 'vars' ][ 'lookup_ajax' ][ 'subject' ] ?? '' ) );
	}
}
