<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ReportsRoutingIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
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

	public function test_reports_overview_renders_structured_sections_and_contextual_cta() :void {
		$xpath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_OVERVIEW );
		$this->assertXPathExists( $xpath, '//*[@data-reports-section="charts"]', 'Reports overview charts section marker' );
		$this->assertXPathExists( $xpath, '//*[@data-reports-heading="charts"]', 'Reports overview charts heading marker' );
		$this->assertXPathExists( $xpath, '//*[@data-reports-content="summary-charts"]', 'Reports overview summary charts content marker' );
		$this->assertXPathExists( $xpath, '//*[@id="SectionStats"]', 'Reports overview summary stats strip marker' );

		$this->assertXPathExists( $xpath, '//*[@data-reports-section="recent-reports"]', 'Reports overview recent reports section marker' );
		$this->assertXPathExists( $xpath, '//*[@data-reports-heading="recent-reports"]', 'Reports overview recent reports heading marker' );
		$this->assertXPathExists( $xpath, '//*[@data-reports-content="recent-reports"]', 'Reports overview recent reports content marker' );
		$this->assertXPathCount( $xpath, '//*[@data-reports-cta="reports-list"]', 1, 'Reports overview contextual reports-list CTA marker count' );
		$this->assertXPathCount( $xpath, '//*[@data-reports-cta="reports-charts"]', 0, 'Reports overview no charts CTA marker' );
		$this->assertXPathCount( $xpath, '//*[@data-reports-cta="reports-settings"]', 0, 'Reports overview no settings CTA marker' );
	}

	public function test_reports_workspace_routes_render_expected_structural_markers() :void {
		$listXPath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_LIST );
		$this->assertXPathExists( $listXPath, '//*[@id="ReportsTable"]', 'Reports list table container marker' );

		$chartsXPath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_CHARTS );
		$this->assertXPathExists( $chartsXPath, '//*[@id="SectionStats"]', 'Reports charts stats strip marker' );

		$settingsXPath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_SETTINGS );
		$this->assertXPathExists( $settingsXPath, '//*[contains(concat(" ", normalize-space(@class), " "), " options_form_for--modern ")]', 'Reports settings options form marker' );
	}
}
