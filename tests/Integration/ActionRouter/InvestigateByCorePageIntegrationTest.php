<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\PageAdminPlugin,
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
		$filter = self::con()->prefix( 'bypass_is_plugin_admin' );
		add_filter( $filter, '__return_true', 1000 );

		try {
			return $this->processor()
						->processAction( PageAdminPlugin::SLUG, [
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
		$payload = $this->renderByCorePage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertHtmlContainsMarker( 'data-investigation-table="1"', $html, 'By-core investigation table marker' );
		$this->assertHtmlContainsMarker( 'data-table-type="file_scan_results"', $html, 'By-core file status table marker' );
		$this->assertHtmlContainsMarker( 'data-table-type="activity"', $html, 'By-core activity table marker' );
		$this->assertHtmlContainsMarker( 'data-subject-type="core"', $html, 'By-core subject type marker' );
	}
}
