<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	LookupRouteFormAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use LookupRouteFormAssertions, PluginAdminRouteRenderAssertions;

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

		$this->assertHtmlContainsMarker( 'data-investigate-section="selector"', $html, 'Landing selector section marker' );
		$this->assertHtmlContainsMarker( 'data-investigate-section="lookup-shell"', $html, 'Landing lookup shell marker' );
		$this->assertHtmlContainsMarker( 'data-investigate-subject="users"', $html, 'Landing users subject marker' );
		$this->assertHtmlContainsMarker( 'data-investigate-subject="ips"', $html, 'Landing ips subject marker' );
		$this->assertHtmlContainsMarker( 'data-investigate-subject="plugins"', $html, 'Landing plugins subject marker' );
		$this->assertHtmlContainsMarker( 'data-investigate-subject="themes"', $html, 'Landing themes subject marker' );
		$this->assertHtmlContainsMarker( 'data-investigate-subject="wordpress"', $html, 'Landing wordpress subject marker' );
		$this->assertHtmlContainsMarker( 'data-investigate-subject="requests"', $html, 'Landing requests subject marker' );
		$this->assertHtmlContainsMarker( 'data-investigate-subject="activity"', $html, 'Landing activity subject marker' );
		$this->assertHtmlContainsMarker( 'data-investigate-subject="woocommerce"', $html, 'Landing woocommerce subject marker' );
		$this->assertHtmlContainsMarker( 'aria-disabled="true"', $html, 'Landing woocommerce disabled marker' );
		$this->assertHtmlNotContainsMarker( 'quick-tool-link', $html, 'Landing quick-access class marker' );
		$this->assertHtmlNotContainsMarker( 'data-investigate-section="quick-access"', $html, 'Landing quick-access section marker' );
	}
}
