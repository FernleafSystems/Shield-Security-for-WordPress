<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ConfigureLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderConfigureLandingPage() :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ZONES,
			PluginNavs::SUBNAV_ZONES_OVERVIEW
		);
	}

	public function test_configure_landing_renders_expected_sections_and_contract_markers() :void {
		$payload = $this->renderConfigureLandingPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertNotSame( '', $html, 'Expected non-empty render output for configure landing.' );
		$this->assertHtmlNotContainsMarker( 'Exception during render', $html, 'Configure landing render exception check' );

		$xpath = $this->createDomXPathFromHtml( $html );
		$this->assertXPathExists( $xpath, '//*[@data-configure-section="hero"]', 'Configure hero section marker' );
		$this->assertXPathExists( $xpath, '//*[@data-configure-section="stats"]', 'Configure stats section marker' );
		$this->assertXPathExists( $xpath, '//*[@data-configure-section="overview-meters"]', 'Configure overview section marker' );
		$this->assertXPathExists( $xpath, '//*[@data-configure-section="zones"]', 'Configure zones section marker' );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="hero"]//a[contains(concat(" ", normalize-space(@class), " "), " offcanvas_meter_analysis ") and @data-meter_channel="config"]',
			'Configure hero offcanvas meter link with config channel'
		);

		$zoneCount = \count( self::con()->comps->zones->getZones() );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-zone]',
			$zoneCount,
			'Configure zone navigation marker count'
		);
	}
}

