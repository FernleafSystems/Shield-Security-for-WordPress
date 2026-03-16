<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ReportsRoutingIntegrationTest extends ShieldIntegrationTestCase {

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

	public function test_reports_overview_renders_interactive_tile_panel_with_default_reports_table() :void {
		$landingWorkspaceDefinitions = PluginNavs::reportsLandingWorkspaceDefinitions();
		$landingSubNavs = \array_keys( $landingWorkspaceDefinitions );
		$expectedLandingCount = \count( $landingSubNavs );
		$payload = $this->renderReportsSubNavPayload( PluginNavs::SUBNAV_REPORTS_OVERVIEW );
		$vars = (array)( $payload[ 'render_data' ][ 'vars' ] ?? [] );

		$this->assertModeShellPayload( $vars, 'reports', 'warning', true );
		$this->assertSame( PluginNavs::SUBNAV_REPORTS_LIST, (string)( $vars[ 'mode_panel' ][ 'active_target' ] ?? '' ) );
		$this->assertTrue( (bool)( $vars[ 'mode_panel' ][ 'is_open' ] ?? false ) );
		$this->assertCount( $expectedLandingCount, $vars[ 'mode_tiles' ] ?? [] );

		foreach ( $landingSubNavs as $subNav ) {
			$tileMatches = \array_values( \array_filter(
				(array)( $vars[ 'mode_tiles' ] ?? [] ),
				static fn( array $tile ) :bool => (string)( $tile[ 'key' ] ?? '' ) === $subNav
			) );
			$this->assertCount( 1, $tileMatches, 'Reports tile target contract for '.$subNav );
		}
		$this->assertSame( [], \array_values( \array_filter(
			(array)( $vars[ 'mode_tiles' ] ?? [] ),
			static fn( array $tile ) :bool => (string)( $tile[ 'key' ] ?? '' ) === PluginNavs::SUBNAV_REPORTS_CHARTS
		) ) );
	}

	public function test_reports_workspace_routes_render_expected_structural_markers_including_legacy_routes() :void {
		foreach ( \array_keys( PluginNavs::reportsWorkspaceDefinitions() ) as $subNav ) {
			$payload = $this->renderReportsSubNavPayload( $subNav );
			if ( $subNav === PluginNavs::SUBNAV_REPORTS_LIST ) {
				$this->assertStringContainsString( 'ShieldTable-Reports', (string)( $payload[ 'render_output' ] ?? '' ) );
			}
			elseif ( $subNav === PluginNavs::SUBNAV_REPORTS_CHARTS ) {
				$this->assertStringContainsString( 'SectionStats', (string)( $payload[ 'render_output' ] ?? '' ) );
			}
			else {
				$this->assertStringContainsString( '<form', (string)( $payload[ 'render_output' ] ?? '' ) );
			}
		}
	}
}
