<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ReportsRoutingIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'reports' );
		$this->requireDb( 'events' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderReportsSubNavHtml( string $subNav ) :string {
		$payload = $this->renderPluginAdminRoutePayload( PluginNavs::NAV_REPORTS, $subNav );
		return (string)( $payload[ 'render_output' ] ?? '' );
	}

	private function renderReportsSubNavXPath( string $subNav ) :\DOMXPath {
		$html = $this->renderReportsSubNavHtml( $subNav );
		$this->assertNotSame( '', $html, 'Expected non-empty render output for reports/'.$subNav );
		$this->assertHtmlNotContainsMarker( 'Exception during render', $html, 'Reports route render exception check for '.$subNav );
		return $this->createDomXPathFromHtml( $html );
	}

	public function test_reports_overview_renders_interactive_tile_panel_with_default_reports_table() :void {
		$xpath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_OVERVIEW );
		$this->assertModeShellAndAccentContract( $xpath, 'reports', 'warning', 'Reports', true );
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " inner-page-header ") and contains(concat(" ", normalize-space(@class), " "), " inner-page-header-compact ")]',
			'Reports compact header marker'
		);
		$this->assertXPathExists( $xpath, '//*[@data-reports-landing="1"]', 'Reports landing root marker' );
		$this->assertXPathCount( $xpath, '//*[@data-mode-tile="1"]', 3, 'Reports mode tile count marker' );
		$this->assertXPathCount( $xpath, '//*[@data-mode-panel="1"]', 3, 'Reports mode panel count marker' );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-reports-panel="list" and contains(concat(" ", normalize-space(@class), " "), " is-open ") and @aria-hidden="false"]',
			'Reports security reports panel default-open marker'
		);
		$this->assertXPathExists( $xpath, '//*[@data-reports-content="security-reports"]', 'Reports table panel content marker' );
		$this->assertXPathExists( $xpath, '//*[@data-reports-content="security-reports"]//*[@id="ReportsTable"]', 'Reports table in landing panel marker' );
		$this->assertXPathCount( $xpath, '//*[@data-reports-cta="alerts"]', 1, 'Reports alerts CTA marker count' );
		$this->assertXPathCount( $xpath, '//*[@data-reports-cta="reporting"]', 1, 'Reports reporting CTA marker count' );
		$this->assertXPathCount( $xpath, '//*[@data-reports-section="charts"]', 0, 'Reports overview no longer renders charts section marker' );
	}

	public function test_reports_workspace_routes_render_expected_structural_markers_including_legacy_routes() :void {
		$listXPath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_LIST );
		$this->assertXPathExists( $listXPath, '//*[@id="ReportsTable"]', 'Reports list table container marker' );

		$alertsXPath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_ALERTS );
		$this->assertXPathExists( $alertsXPath, '//*[contains(concat(" ", normalize-space(@class), " "), " options_form_for--modern ")]', 'Reports alerts options form marker' );

		$reportingXPath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_REPORTING );
		$this->assertXPathExists( $reportingXPath, '//*[contains(concat(" ", normalize-space(@class), " "), " options_form_for--modern ")]', 'Reports reporting options form marker' );

		$chartsXPath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_CHARTS );
		$this->assertXPathExists( $chartsXPath, '//*[@id="SectionStats"]', 'Reports legacy charts stats strip marker' );

		$settingsXPath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_SETTINGS );
		$this->assertXPathExists( $settingsXPath, '//*[contains(concat(" ", normalize-space(@class), " "), " options_form_for--modern ")]', 'Reports legacy settings options form marker' );
	}
}
