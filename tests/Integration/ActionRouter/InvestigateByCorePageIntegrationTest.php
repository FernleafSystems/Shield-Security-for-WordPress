<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\PageAdminPlugin,
	Actions\Render\PluginAdminPages\PageInvestigateByCore,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByCorePageIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderByCorePage() :array {
		return $this->renderByCorePageAction( PageAdminPlugin::SLUG );
	}

	private function renderByCoreInnerPage() :array {
		return $this->renderByCorePageAction( PageInvestigateByCore::SLUG );
	}

	private function renderByCorePageAction( string $actionSlug ) :array {
		$filter = self::con()->prefix( 'bypass_is_plugin_admin' );
		add_filter( $filter, '__return_true', 1000 );

		try {
			return $this->processor()
						->processAction( $actionSlug, [
							Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
							Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_CORE,
						] )
						->payload();
		}
		finally {
			remove_filter( $filter, '__return_true', 1000 );
		}
	}

	public function test_core_page_renders_file_status_and_activity_tables() :void {
		$renderData = (array)( $this->renderByCoreInnerPage()[ 'render_data' ] ?? [] );
		$fileStatusCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'file_status' ][ 'count' ] ?? 0 );
		$activityCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'activity' ][ 'count' ] ?? 0 );
		$expectedTableCount = ( $fileStatusCount > 0 ? 1 : 0 ) + ( $activityCount > 0 ? 1 : 0 );

		$payload = $this->renderByCorePage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertSame( $expectedTableCount, \substr_count( $html, 'data-investigation-table="1"' ) );
		if ( $fileStatusCount > 0 ) {
			$this->assertHtmlContainsMarker( 'data-table-type="file_scan_results"', $html, 'By-core file status table marker' );
		}
		else {
			$this->assertHtmlNotContainsMarker( 'data-table-type="file_scan_results"', $html, 'By-core file status empty state' );
		}
		if ( $activityCount > 0 ) {
			$this->assertHtmlContainsMarker( 'data-table-type="activity"', $html, 'By-core activity table marker' );
		}
		else {
			$this->assertHtmlNotContainsMarker( 'data-table-type="activity"', $html, 'By-core activity empty state' );
		}
		if ( $expectedTableCount > 0 ) {
			$this->assertHtmlContainsMarker( 'data-subject-type="core"', $html, 'By-core subject type marker' );
		}
		else {
			$this->assertHtmlNotContainsMarker( 'data-subject-type="core"', $html, 'By-core subject type absent on empty tables' );
		}
	}
}
