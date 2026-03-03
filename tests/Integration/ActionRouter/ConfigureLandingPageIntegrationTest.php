<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ConfigureLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use ModeLandingAssertions;
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
		$this->assertXPathExists( $xpath, '//*[@data-configure-section="zones"]', 'Configure zones section marker' );
		$this->assertModeShellAndAccentContract( $xpath, 'configure', 'good', 'Configure', true );
		$this->assertXPathCount( $xpath, '//*[@data-configure-section="stats"]', 0, 'Configure stats section removed' );
		$this->assertXPathCount( $xpath, '//*[@data-configure-section="overview-meters"]', 0, 'Configure overview section removed' );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="hero"]//a[contains(concat(" ", normalize-space(@class), " "), " offcanvas_meter_analysis ") and @data-meter_channel="config"]',
			'Configure hero offcanvas meter link with config channel'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="zones"]//div[contains(concat(" ", normalize-space(@class), " "), " shield-card-accent ") and contains(concat(" ", normalize-space(@class), " "), " status-good ")]',
			'Configure zones section uses good status accent'
		);

		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-zone and @data-mode-tile="1"]',
			8,
			'Configure zone navigation marker count'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-tile="1"]',
			8,
			'Configure shared mode tile marker count'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-tile="1" and self::a]',
			0,
			'Configure tiles must not navigate directly'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]',
			8,
			'Configure inline panel marker count'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-panel="secadmin"]//*[@data-configure-zone-settings="secadmin"]',
			'Configure secadmin panel settings CTA'
		);
		$this->assertSharedModePanelMarker( $xpath, 'Configure' );
	}
}
