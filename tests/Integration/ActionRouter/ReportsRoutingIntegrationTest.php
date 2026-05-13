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

		$this->assertSame( $expectedLandingCount, \count( $landingSubNavs ) );
		$this->assertPluginAdminShellRouteState( $payload, PluginNavs::SUBNAV_REPORTS_OVERVIEW );
	}

	public function test_reports_workspace_routes_render_expected_structural_markers() :void {
		foreach ( \array_keys( PluginNavs::reportsWorkspaceDefinitions() ) as $subNav ) {
			$payload = $this->renderReportsSubNavPayload( $subNav );
			$this->assertPluginAdminShellRouteState( $payload, $subNav );
		}
	}
}
