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
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="zones" and not(contains(concat(" ", normalize-space(@class), " "), " card ")) and not(contains(concat(" ", normalize-space(@class), " "), " shield-card "))]',
			'Configure zones section no longer uses card wrapper classes'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="zones"]//*[@data-mode-tiles="1" and contains(concat(" ", normalize-space(@class), " "), " configure-landing__zone-grid ")]',
			'Configure zones section renders mode tile grid directly'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-section="zones"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-card-accent ")]',
			0,
			'Configure zones section should not render shield card accent'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-section="zones"]//*[contains(concat(" ", normalize-space(@class), " "), " card-body ")]',
			0,
			'Configure zones section should not render card body wrapper'
		);
		$this->assertModeShellAndAccentContract( $xpath, 'configure', 'good', 'Configure', true );
		$this->assertXPathCount( $xpath, '//*[@data-configure-section="stats"]', 0, 'Configure stats section removed' );
		$this->assertXPathCount( $xpath, '//*[@data-configure-section="overview-meters"]', 0, 'Configure overview section removed' );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="hero"]//*[@data-configure-posture-strip="1"]',
			'Configure posture strip container marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="hero"]//*[@data-configure-posture-chip="1"]',
			'Configure posture status chip marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="hero"]//*[@data-configure-posture-bar="1"]',
			'Configure posture bar marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="hero"]//*[@data-configure-posture-summary="1"]',
			'Configure posture summary marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-section="hero"]//*[contains(concat(" ", normalize-space(@class), " "), " progress-metercard ")]',
			0,
			'Configure hero no longer renders progress meter card wrapper'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-section="hero"]//a[contains(concat(" ", normalize-space(@class), " "), " offcanvas_meter_analysis ")]',
			0,
			'Configure hero no longer renders offcanvas meter link'
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
		$this->assertSharedModePanelMarkerCount( $xpath, 8, 'Configure' );
		$this->assertModePanelHasDataAttribute( $xpath, 'configure-panel', 'Configure' );
		$this->assertModePanelHasDataAttribute( $xpath, 'mode-panel-target-default', 'Configure' );
		$this->assertModePanelHasDataAttribute( $xpath, 'mode-panel-static-target', 'Configure' );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-panel="1" and (contains(concat(" ", normalize-space(@class), " "), " status-good ") or contains(concat(" ", normalize-space(@class), " "), " status-warning ") or contains(concat(" ", normalize-space(@class), " "), " status-critical ") or contains(concat(" ", normalize-space(@class), " "), " status-info ") or contains(concat(" ", normalize-space(@class), " "), " status-neutral "))]',
			8,
			'Configure mode panels include status class on panel root'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-panel="1"]//button[@data-mode-panel-close="1" and contains(concat(" ", normalize-space(@class), " "), " mode-panel-close-btn ")]',
			8,
			'Configure mode panel close button uses minimal close class'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-panel="1"]//button[contains(concat(" ", normalize-space(@class), " "), " btn-outline-secondary ")]',
			0,
			'Configure mode panel close button no longer uses bootstrap outline class'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]//a[@data-configure-zone-settings]',
			8,
			'Configure panel settings CTA marker count'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]//a[@data-configure-zone-settings and contains(concat(" ", normalize-space(@class), " "), " configure-landing__panel-cta ")]',
			8,
			'Configure panel settings CTA uses redesign class'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]//a[@data-configure-zone-settings and (contains(concat(" ", normalize-space(@class), " "), " status-good ") or contains(concat(" ", normalize-space(@class), " "), " status-warning ") or contains(concat(" ", normalize-space(@class), " "), " status-critical "))]',
			8,
			'Configure panel settings CTA carries status class'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]//a[@data-configure-zone-settings and contains(concat(" ", normalize-space(@class), " "), " btn-outline-secondary ")]',
			0,
			'Configure panel settings CTA no longer uses bootstrap outline class'
		);
	}
}
