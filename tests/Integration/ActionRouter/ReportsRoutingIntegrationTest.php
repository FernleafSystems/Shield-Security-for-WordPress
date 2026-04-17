<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ReportsRoutingIntegrationTest extends ShieldIntegrationTestCase {

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

	public function test_reports_overview_renders_shared_drilldown_with_three_landing_workspaces() :void {
		$landingWorkspaceDefinitions = PluginNavs::reportsWorkspaceDefinitions();
		$landingSubNavs = \array_keys( $landingWorkspaceDefinitions );
		$expectedLandingCount = \count( $landingSubNavs );
		$payload = $this->renderReportsSubNavPayload( PluginNavs::SUBNAV_REPORTS_OVERVIEW );
		$output = (string)( $payload[ 'render_output' ] ?? '' );

		foreach ( $landingSubNavs as $subNav ) {
			$this->assertStringContainsString(
				'data-reports-workspace="'.$subNav.'"',
				$output,
				'Reports workspace marker contract for '.$subNav
			);
		}
		$this->assertSame( $expectedLandingCount, \count( $landingSubNavs ) );
		$this->assertStringContainsString( 'data-drill-shell="1"', $output );
		$this->assertStringContainsString( 'data-drill-shell-mode="reports"', $output );
		$this->assertStringContainsString( 'data-reports-workspace-selection=', $output );
	}

	public function test_reports_workspace_routes_render_expected_structural_markers() :void {
		foreach ( \array_keys( PluginNavs::reportsWorkspaceDefinitions() ) as $subNav ) {
			$payload = $this->renderReportsSubNavPayload( $subNav );
			if ( $subNav === PluginNavs::SUBNAV_REPORTS_LIST ) {
				$this->assertStringContainsString( 'ShieldTable-Reports', (string)( $payload[ 'render_output' ] ?? '' ) );
			}
			elseif ( $subNav === PluginNavs::SUBNAV_REPORTS_CHARTS ) {
				$this->assertStringContainsString( 'data-reports-trends="1"', (string)( $payload[ 'render_output' ] ?? '' ) );
				$this->assertStringContainsString( 'data-reports-trends-form="1"', (string)( $payload[ 'render_output' ] ?? '' ) );
				$this->assertStringContainsString( 'data-reports-chart-output="1"', (string)( $payload[ 'render_output' ] ?? '' ) );
			}
			else {
				$this->assertStringContainsString( '<form', (string)( $payload[ 'render_output' ] ?? '' ) );
			}
		}
	}
}
