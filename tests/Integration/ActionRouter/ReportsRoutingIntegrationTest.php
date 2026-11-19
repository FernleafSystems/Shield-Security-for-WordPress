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

	/**
	 * Reports is one page with a tab for each workspace, and each tab has a link of its own: the Reports page,
	 * opened on that tab.
	 */
	public function test_each_reports_tab_link_opens_the_reports_page_on_that_tab() :void {
		foreach ( \array_keys( PluginNavs::reportsWorkspaceDefinitions() ) as $workspace ) {
			// The page is asked for exactly as the tab's own link asks for it.
			\parse_str(
				(string)\wp_parse_url( $this->requireController()->plugin_urls->reportsHome( $workspace ), \PHP_URL_QUERY ),
				$link
			);
			$payload = $this->renderPluginAdminRoutePayload(
				(string)$link[ 'nav' ],
				(string)$link[ 'nav_sub' ],
				[ 'workspace' => (string)( $link[ 'workspace' ] ?? '' ) ]
			);
			$output = $this->assertRouteRenderOutputHealthy( $payload, 'reports tab '.$workspace );
			$this->assertPluginAdminShellRouteState( $payload, PluginNavs::SUBNAV_REPORTS_OVERVIEW );
			$this->assertStringContainsString( 'data-reports-current-tab="'.$workspace.'"', $output );
		}
	}
}
