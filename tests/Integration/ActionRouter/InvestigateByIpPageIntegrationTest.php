<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\PageInvestigateByIp,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	LookupRouteFormAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByIpPageIntegrationTest extends ShieldIntegrationTestCase {

	use LookupRouteFormAssertions, PluginAdminRouteRenderAssertions;

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

	private function renderByIpInnerPage( string $ip = '' ) :array {
		$params = [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_IP,
		];
		if ( $ip !== '' ) {
			$params[ 'analyse_ip' ] = $ip;
		}
		return $this->processActionPayloadWithAdminBypass( PageInvestigateByIp::SLUG, $params );
	}

	public function test_valid_ip_lookup_renders_ip_analysis_container() :void {
		$renderData = (array)( $this->renderByIpInnerPage( '203.0.113.88' )[ 'render_data' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );

		$payload = $this->renderByIpPage( '203.0.113.88' );
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertHtmlContainsMarker( 'shield-ipanalyse', $html, 'By-ip analysis container marker' );
		$this->assertHtmlContainsMarker( 'Overview', $html, 'By-ip overview tab label marker' );
		$this->assertHtmlNotContainsMarker( 'Change IP', $html, 'Removed by-ip wrapper text marker' );
	}

	public function test_no_lookup_renders_without_ip_analysis_container() :void {
		$payload = $this->renderByIpPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertHtmlNotContainsMarker( 'shield-ipanalyse', $html, 'By-ip analysis container without lookup' );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderByIpPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
	}
}
