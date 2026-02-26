<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\PageAdminPlugin,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ReportsRoutingIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'reports' );
		$this->requireDb( 'events' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderReportsSubNavHtml( string $subNav ) :string {
		$filter = self::con()->prefix( 'bypass_is_plugin_admin' );
		add_filter( $filter, '__return_true', 1000 );

		try {
			$response = $this->processor()->processAction( PageAdminPlugin::SLUG, [
				Constants::NAV_ID     => PluginNavs::NAV_REPORTS,
				Constants::NAV_SUB_ID => $subNav,
			] );
			return (string)( $response->payload()[ 'render_output' ] ?? '' );
		}
		finally {
			remove_filter( $filter, '__return_true', 1000 );
		}
	}

	public function test_reports_routes_render_expected_markers_without_render_exceptions() :void {
		$routeMarkers = [
			PluginNavs::SUBNAV_REPORTS_OVERVIEW => [
				'Open Reports List',
				'Open Charts & Trends',
				'Open Alert Settings',
			],
			PluginNavs::SUBNAV_REPORTS_LIST     => [
				'id="ReportsTable"',
			],
			PluginNavs::SUBNAV_REPORTS_CHARTS   => [
				'Review recent security trend metrics.',
				'id="SectionStats"',
			],
			PluginNavs::SUBNAV_REPORTS_SETTINGS => [
				'Alert Settings',
				'options_form_for--modern',
			],
		];

		foreach ( $routeMarkers as $subNav => $markers ) {
			$html = $this->renderReportsSubNavHtml( $subNav );
			$this->assertNotSame( '', $html, 'Expected non-empty render output for reports/'.$subNav );
			$this->assertHtmlNotContainsMarker( 'Exception during render', $html, 'Reports route render exception check' );
			foreach ( $markers as $marker ) {
				$this->assertHtmlContainsMarker( $marker, $html, 'Reports route marker check for '.$subNav );
			}
		}
	}
}
