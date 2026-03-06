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

	private function renderReportsSubNavPayload( string $subNav ) :array {
		$payload = $this->renderPluginAdminRoutePayload( PluginNavs::NAV_REPORTS, $subNav );
		$this->assertRouteRenderOutputHealthy( $payload, 'reports/'.$subNav );
		return $payload;
	}

	private function renderReportsSubNavHtml( string $subNav ) :string {
		$payload = $this->renderReportsSubNavPayload( $subNav );
		return (string)( $payload[ 'render_output' ] ?? '' );
	}

	private function renderReportsSubNavXPath( string $subNav ) :\DOMXPath {
		$html = $this->renderReportsSubNavHtml( $subNav );
		return $this->createDomXPathFromHtml( $html );
	}

	private function assertReportsTableRendered( \DOMXPath $xpath, string $label ) :void {
		$this->assertXPathExists( $xpath, '//*[@data-reports-table="1"]', $label.' reports table marker' );
	}

	public function test_reports_overview_renders_interactive_tile_panel_with_default_reports_table() :void {
		$landingWorkspaceDefinitions = PluginNavs::reportsLandingWorkspaceDefinitions();
		$landingSubNavs = \array_keys( $landingWorkspaceDefinitions );
		$expectedLandingCount = \count( $landingSubNavs );
		$xpath = $this->renderReportsSubNavXPath( PluginNavs::SUBNAV_REPORTS_OVERVIEW );
		$this->assertModeShellAndAccentContract( $xpath, 'reports', 'warning', 'Reports', true );
		$this->assertXPathExists( $xpath, '//*[@data-reports-landing="1"]', 'Reports landing root marker' );
		$this->assertXPathCount( $xpath, '//*[@data-mode-tile="1"]', $expectedLandingCount, 'Reports mode tile count marker' );
		$this->assertXPathCount( $xpath, '//*[@data-mode-panel="1"]', $expectedLandingCount, 'Reports mode panel count marker' );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-shell="1" and @data-mode-active-panel="'.PluginNavs::SUBNAV_REPORTS_LIST.'"]',
			'Reports mode shell active panel contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-reports-panel="'.PluginNavs::SUBNAV_REPORTS_LIST.'" and @aria-hidden="false"]',
			'Reports security reports panel default-open marker'
		);
		$this->assertXPathExists( $xpath, '//*[@data-reports-content="'.PluginNavs::SUBNAV_REPORTS_LIST.'"]', 'Reports table panel content marker' );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-reports-content="'.PluginNavs::SUBNAV_REPORTS_LIST.'"]//*[@data-reports-table="1"]',
			'Reports table in landing panel marker'
		);
		$this->assertXPathExists( $xpath, '//*[@data-reports-content="'.PluginNavs::SUBNAV_REPORTS_SETTINGS.'"]//form', 'Inline reports settings form marker' );

		foreach ( $landingSubNavs as $subNav ) {
			$this->assertXPathExists(
				$xpath,
				'//*[@data-reports-tile="'.$subNav.'" and @data-mode-panel-target="'.$subNav.'"]',
				'Reports tile target contract for '.$subNav
			);
			$this->assertXPathExists(
				$xpath,
				'//*[@data-reports-panel="'.$subNav.'" and @data-mode-panel-target="'.$subNav.'"]',
				'Reports panel target contract for '.$subNav
			);
		}
		$this->assertXPathCount( $xpath, '//*[@data-reports-tile="'.PluginNavs::SUBNAV_REPORTS_CHARTS.'"]', 0, 'Reports charts tile remains excluded from landing' );
		$this->assertXPathCount( $xpath, '//*[@data-reports-panel="'.PluginNavs::SUBNAV_REPORTS_CHARTS.'"]', 0, 'Reports charts panel remains excluded from landing' );
	}

	public function test_reports_workspace_routes_render_expected_structural_markers_including_legacy_routes() :void {
		foreach ( \array_keys( PluginNavs::reportsWorkspaceDefinitions() ) as $subNav ) {
			$payload = $this->renderReportsSubNavPayload( $subNav );
			$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );
			if ( $subNav === PluginNavs::SUBNAV_REPORTS_LIST ) {
				$this->assertReportsTableRendered( $xpath, 'Reports list route' );
			}
			elseif ( $subNav === PluginNavs::SUBNAV_REPORTS_CHARTS ) {
				$this->assertXPathExists( $xpath, '//*[@id="SectionStats"]', 'Reports legacy charts stats strip marker' );
			}
			else {
				$this->assertXPathExists( $xpath, '//form', 'Reports form route marker for '.$subNav );
			}
		}
	}
}
