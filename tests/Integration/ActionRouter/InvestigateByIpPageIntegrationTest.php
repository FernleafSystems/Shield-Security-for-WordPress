<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\InvestigateByIpPanelBody,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	InvestigateRoutePanelAssertions,
	LookupRouteFormAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByIpPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use InvestigateRoutePanelAssertions;
	use LookupRouteFormAssertions;
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

		$this->assertInvestigateRoutePreloadsSubjectPanel(
			$this->renderByIpPage( '203.0.113.88' ),
			'activity by-ip route',
			'ip',
			'IP Address',
			'//*[@data-drill-layer="1"]//*[@data-investigate-panel-content="1"]//*[contains(concat(" ", normalize-space(@class), " "), " investigate-inline-ipanalyse ")]'
		);
	}

	public function test_no_lookup_route_preloads_ip_panel_lookup_form() :void {
		$this->assertInvestigateRoutePreloadsLookupPanel(
			$this->renderByIpPage(),
			'activity by-ip route without lookup',
			'ip',
			'IP Address',
			'analyse_ip'
		);
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderByIpPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
	}

	public function test_panel_body_action_renders_ip_markup_contract() :void {
		$panelHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderByIpPanelBody( '203.0.113.88' ),
			'investigate by-ip panel body action'
		);
		$this->assertStringContainsString( 'data-investigate-subject-header="1"', $panelHtml );
		$this->assertStringContainsString( 'investigate-inline-ipanalyse', $panelHtml );
		$this->assertStringNotContainsString( 'data-inner-page-body-shell="1"', $panelHtml );
		$this->assertIpInvestigationTablesMarkup( $panelHtml, '203.0.113.88', 'investigate by-ip panel body action' );
	}

	private function assertIpInvestigationTablesMarkup( string $html, string $ip, string $label ) :void {
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathCount(
			$xpath,
			\sprintf(
				'//*[@data-investigation-table="1" and @data-subject-type="ip" and @data-subject-id="%s"]',
				$ip
			),
			3,
			$label.' shared IP investigation tables'
		);
		$this->assertXPathExists(
			$xpath,
			\sprintf(
				'//*[@data-investigation-table="1" and @data-table-type="sessions" and @data-subject-type="ip" and @data-subject-id="%s"]',
				$ip
			),
			$label.' sessions table marker'
		);
		$this->assertXPathExists(
			$xpath,
			\sprintf(
				'//*[@data-investigation-table="1" and @data-table-type="activity" and @data-subject-type="ip" and @data-subject-id="%s"]',
				$ip
			),
			$label.' activity table marker'
		);
		$this->assertXPathExists(
			$xpath,
			\sprintf(
				'//*[@data-investigation-table="1" and @data-table-type="traffic" and @data-subject-type="ip" and @data-subject-id="%s"]',
				$ip
			),
			$label.' traffic table marker'
		);
	}
}
