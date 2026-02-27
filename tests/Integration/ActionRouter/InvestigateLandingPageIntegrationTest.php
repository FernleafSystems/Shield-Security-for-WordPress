<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	LookupRouteFormAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions, LookupRouteFormAssertions, PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderInvestigateLandingPage() :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_OVERVIEW
		);
	}

	public function test_landing_user_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderInvestigateLandingPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
	}

	public function test_landing_ip_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderInvestigateLandingPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
	}

	public function test_landing_plugin_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderInvestigateLandingPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN );
	}

	public function test_landing_theme_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderInvestigateLandingPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_THEME );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_THEME );
	}

	public function test_landing_renders_selector_lookup_and_disabled_woocommerce_tile_markers() :void {
		$payload = $this->renderInvestigateLandingPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-section="selector"]',
			'Landing selector section marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-section="lookup-shell"]',
			'Landing lookup shell marker'
		);

		foreach ( [ 'users', 'ips', 'plugins', 'themes', 'wordpress', 'requests', 'activity' ] as $subjectKey ) {
			$this->assertXPathExists(
				$xpath,
				'//*[@data-investigate-subject="'.$subjectKey.'"]',
				'Landing '.$subjectKey.' subject marker'
			);
		}

		$this->assertXPathExists(
			$xpath,
			'//div[@data-investigate-subject="woocommerce" and @aria-disabled="true"]',
			'Landing woocommerce disabled marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " quick-tool-link ")]',
			0,
			'Landing quick-access class marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-section="quick-access"]',
			0,
			'Landing quick-access section marker'
		);
	}
}
