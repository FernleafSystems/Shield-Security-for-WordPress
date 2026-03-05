<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxRender,
	Actions\Render\PluginAdminPages\PageConfigureLanding,
	Constants
};
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
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'configure landing' );
		$tileDefinitions = PluginNavs::configureLandingTileDefinitions();
		$expectedCount = \count( $tileDefinitions );
		$xpath = $this->createDomXPathFromHtml( $html );
		$landingNode = $this->assertXPathExists(
			$xpath,
			'//*[@data-configure-landing="1" and string-length(normalize-space(@data-configure-render-action)) > 0]',
			'Configure landing render action marker'
		);
		$renderActionData = $this->decodeJsonAttribute(
			$landingNode,
			'data-configure-render-action',
			'Configure landing render action contract'
		);
		$this->assertSame( ActionData::FIELD_SHIELD, $renderActionData[ ActionData::FIELD_ACTION ] ?? '' );
		$this->assertSame( AjaxRender::SLUG, $renderActionData[ ActionData::FIELD_EXECUTE ] ?? '' );
		$this->assertSame( PageConfigureLanding::SLUG, $renderActionData[ 'render_slug' ] ?? '' );
		$this->assertSame( PluginNavs::NAV_ZONES, $renderActionData[ Constants::NAV_ID ] ?? '' );
		$this->assertSame( PluginNavs::SUBNAV_ZONES_OVERVIEW, $renderActionData[ Constants::NAV_SUB_ID ] ?? '' );

		$this->assertXPathExists( $xpath, '//*[@data-configure-section="hero"]', 'Configure hero section marker' );
		$this->assertXPathExists( $xpath, '//*[@data-configure-section="zones"]', 'Configure zones section marker' );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="zones"]//*[@data-mode-tiles="1"]',
			'Configure zones section renders mode tile grid directly'
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
			'//*[@data-configure-zone and @data-mode-tile="1"]',
			$expectedCount,
			'Configure zone navigation marker count'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-tile="1"]',
			$expectedCount,
			'Configure shared mode tile marker count'
		);
		$this->assertXPathCount( $xpath, '//*[@data-mode-tile="1" and self::button]', $expectedCount, 'Configure tiles render as button controls' );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]',
			$expectedCount,
			'Configure inline panel marker count'
		);
		$this->assertSharedModePanelMarkerCount( $xpath, $expectedCount, 'Configure' );
		$this->assertModePanelHasDataAttribute( $xpath, 'configure-panel', 'Configure' );
		$this->assertModePanelHasDataAttribute( $xpath, 'mode-panel-target-default', 'Configure' );
		$this->assertModePanelHasDataAttribute( $xpath, 'mode-panel-static-target', 'Configure' );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-panel="1"]//button[@data-mode-panel-close="1"]',
			$expectedCount,
			'Configure mode panel close button marker count'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]//a[@data-configure-zone-settings]',
			$expectedCount,
			'Configure panel settings CTA marker count'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]//a[@data-configure-zone-settings and string-length(normalize-space(@href)) > 0]',
			$expectedCount,
			'Configure panel settings CTA href contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]//*[@data-configure-component-status]',
			'Configure component status row marker contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]//*[@data-configure-component-status]//span[@role="img" and string-length(normalize-space(@aria-label)) > 0]',
			'Configure component status icon accessibility contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-panel and @data-mode-panel="1"]//*[@data-configure-component-status]//a[@href]',
			'Configure component action link marker contract'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-zone and @data-mode-tile="1"]//i[@aria-hidden="true"]',
			$expectedCount,
			'Configure icon-led tile marker count'
		);

		foreach ( $tileDefinitions as $tileDefinition ) {
			$zoneKey = (string)$tileDefinition[ 'key' ];
			$this->assertXPathExists(
				$xpath,
				'//*[@data-configure-zone="'.$zoneKey.'" and @data-mode-panel-target="'.$zoneKey.'"]',
				'Configure tile-to-panel target contract for '.$zoneKey
			);
			$this->assertXPathExists(
				$xpath,
				'//*[@data-configure-panel="'.$zoneKey.'" and @data-mode-panel-target="'.$zoneKey.'"]',
				'Configure panel target contract for '.$zoneKey
			);
			$this->assertXPathExists(
				$xpath,
				'//*[@data-configure-panel="'.$zoneKey.'" and @data-mode-panel="1"]//a[@data-configure-zone-settings="'.$zoneKey.'"]',
				'Configure zone settings CTA marker for '.$zoneKey
			);
		}
	}

}
