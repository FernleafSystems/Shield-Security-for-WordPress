<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\PageInvestigateByIp,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	LookupRouteFormAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByIpPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions, LookupRouteFormAssertions, PluginAdminRouteRenderAssertions;

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
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );

		$payload = $this->renderByIpPage( '203.0.113.88' );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-landing="1"]',
			'Legacy by-ip route renders investigate landing'
		);
		$this->assertXPathExists(
			$xpath,
			'//button[@data-investigate-subject="ip" and @data-mode-panel-target="ip" and @aria-expanded="true"]',
			'Legacy by-ip route marks ip tile active'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip" and @aria-hidden="false" and @data-investigate-panel-loaded="1"]',
			'Legacy by-ip route opens ip panel'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-shell="1" and @data-mode-active-panel="ip"]',
			'Legacy by-ip route mode shell active panel marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-landing-hint="1" and @aria-hidden="true"]',
			'Legacy by-ip route hides landing hint when panel is pre-opened'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-header="1"]',
			'Legacy by-ip route renders panel header host marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-content="1"]',
			'Legacy by-ip route renders panel content host marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-content="1"]//*[contains(concat(" ", normalize-space(@class), " "), " investigate-subject-header ")]',
			'Legacy by-ip route renders subject header within panel content'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-content="1"]//*[contains(concat(" ", normalize-space(@class), " "), " investigate-subject-header__title ") and normalize-space()="203.0.113.88"]',
			'Legacy by-ip route subject header title matches lookup value'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-change-subject="1"]',
			'Legacy by-ip route exposes change IP action'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-content="1"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-ipanalyse ")]',
			'By-ip analysis container marker in panel content'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-content="1"]//*[contains(concat(" ", normalize-space(@class), " "), " inner-page-body-shell ")]',
			0,
			'By-ip panel content should not include nested inner-page-body shell'
		);
	}

	public function test_no_lookup_renders_without_ip_analysis_container() :void {
		$payload = $this->renderByIpPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-landing="1"]',
			'Legacy by-ip route without lookup still renders investigate landing'
		);
		$this->assertXPathExists(
			$xpath,
			'//button[@data-investigate-subject="ip" and @data-mode-panel-target="ip" and @aria-expanded="true"]',
			'Legacy by-ip route without lookup keeps ip tile active'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip" and @aria-hidden="false" and @data-investigate-panel-loaded="1"]',
			'Legacy by-ip route without lookup keeps ip panel preloaded'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-content="1"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-ipanalyse ")]',
			0,
			'By-ip analysis container should not render without valid lookup'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-change-subject="1"]',
			0,
			'By-ip change-subject action does not render without active subject'
		);
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderByIpPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
	}
}
