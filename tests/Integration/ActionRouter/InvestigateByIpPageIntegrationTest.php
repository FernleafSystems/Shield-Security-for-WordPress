<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\InvestigateByIpPanelBody,
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

	use HtmlDomAssertions;
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
		$renderData = (array)( $this->renderByIpInnerPage( '203.0.113.88' )[ 'render_data' ] ?? [] );
		$vars = (array)( $renderData[ 'vars' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame( '203.0.113.88', (string)( $vars[ 'analyse_ip' ] ?? '' ) );

		$payload = $this->renderByIpPage( '203.0.113.88' );
		$this->assertRouteRenderOutputHealthy( $payload, 'legacy by-ip route' );
		$routeVars = (array)( $payload[ 'render_data' ][ 'vars' ] ?? [] );
		$subjects = [];
		foreach ( (array)( $routeVars[ 'subjects' ] ?? [] ) as $subject ) {
			if ( \is_array( $subject ) && isset( $subject[ 'key' ] ) ) {
				$subjects[ (string)$subject[ 'key' ] ] = $subject;
			}
		}

		$this->assertSame( 'ip', (string)( $routeVars[ 'mode_panel' ][ 'active_target' ] ?? '' ) );
		$this->assertTrue( (bool)( $routeVars[ 'mode_panel' ][ 'is_open' ] ?? false ) );
		$this->assertTrue( (bool)( $subjects[ 'ip' ][ 'is_loaded' ] ?? false ) );
		$this->assertSame( '203.0.113.88', (string)( $subjects[ 'ip' ][ 'subject_title' ] ?? '' ) );
		$this->assertSame( 'analyse_ip', (string)( $subjects[ 'ip' ][ 'lookup_key' ] ?? '' ) );
	}

	public function test_no_lookup_renders_without_ip_analysis_container() :void {
		$payload = $this->renderByIpPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'legacy by-ip route without lookup' );
		$routeVars = (array)( $payload[ 'render_data' ][ 'vars' ] ?? [] );
		$subjects = [];
		foreach ( (array)( $routeVars[ 'subjects' ] ?? [] ) as $subject ) {
			if ( \is_array( $subject ) && isset( $subject[ 'key' ] ) ) {
				$subjects[ (string)$subject[ 'key' ] ] = $subject;
			}
		}

		$this->assertSame( 'ip', (string)( $routeVars[ 'mode_panel' ][ 'active_target' ] ?? '' ) );
		$this->assertTrue( (bool)( $routeVars[ 'mode_panel' ][ 'is_open' ] ?? false ) );
		$this->assertFalse( (bool)( $subjects[ 'ip' ][ 'is_loaded' ] ?? true ) );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderByIpPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
	}

	public function test_full_page_and_panel_body_actions_share_the_same_ip_markup_contract() :void {
		$fullHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderByIpInnerPage( '203.0.113.88' ),
			'investigate by-ip full page action'
		);
		$this->assertStringContainsString( 'data-inner-page-body-shell="1"', $fullHtml );
		$this->assertStringContainsString( 'data-investigate-subject-header="1"', $fullHtml );
		$this->assertIpInvestigationTablesMarkup( $fullHtml, '203.0.113.88', 'investigate by-ip full page action' );

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
