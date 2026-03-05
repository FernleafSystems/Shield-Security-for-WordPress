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
		$this->assertXPathExists( $xpath, '//*[@data-reports-content="security-reports"]', 'Reports table panel content marker' );
		$this->assertXPathExists( $xpath, '//*[@data-reports-content="security-reports"]//*[@id="ReportsTable"]', 'Reports table in landing panel marker' );

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
		foreach ( \array_filter(
			$landingSubNavs,
			static fn( string $subNav ) :bool => $subNav !== PluginNavs::SUBNAV_REPORTS_LIST
		) as $configSubNav ) {
			$this->assertXPathExists(
				$xpath,
				'//*[@data-reports-cta="'.$configSubNav.'"]',
				'Reports config CTA marker for '.$configSubNav
			);
		}
		$this->assertXPathCount( $xpath, '//*[@data-reports-tile="'.PluginNavs::SUBNAV_REPORTS_CHARTS.'"]', 0, 'Reports charts tile remains excluded from landing' );
		$this->assertXPathCount( $xpath, '//*[@data-reports-panel="'.PluginNavs::SUBNAV_REPORTS_CHARTS.'"]', 0, 'Reports charts panel remains excluded from landing' );
	}

	public function test_reports_workspace_routes_render_expected_structural_markers_including_legacy_routes() :void {
		foreach ( PluginNavs::reportsWorkspaceDefinitions() as $subNav => $workspaceDefinition ) {
			$payload = $this->renderReportsSubNavPayload( $subNav );
			$renderData = (array)( $payload[ 'render_data' ] ?? [] );
			$content = (array)( $renderData[ 'content' ] ?? [] );
			$contentKey = (string)( $workspaceDefinition[ 'content_key' ] ?? '' );

			$this->assertNotSame( '', $contentKey, 'Reports content key contract for '.$subNav );
			$this->assertArrayHasKey( $contentKey, $content, 'Reports render-data content key for '.$subNav );
			$this->assertNotSame( '', \trim( (string)$content[ $contentKey ] ), 'Reports render-data content payload for '.$subNav );

			$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );
			if ( $subNav === PluginNavs::SUBNAV_REPORTS_LIST ) {
				$this->assertXPathExists( $xpath, '//*[@id="ReportsTable"]', 'Reports list table container marker' );
			}
			if ( $subNav === PluginNavs::SUBNAV_REPORTS_CHARTS ) {
				$this->assertXPathExists( $xpath, '//*[@id="SectionStats"]', 'Reports legacy charts stats strip marker' );
			}
		}
	}
}
