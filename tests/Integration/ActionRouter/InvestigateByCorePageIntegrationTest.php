<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\PageInvestigateByCore,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	InvestigatePageAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByCorePageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions, InvestigatePageAssertions, PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderByCorePage() :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_CORE
		);
	}

	private function renderByCoreInnerPage() :array {
		return $this->processActionPayloadWithAdminBypass( PageInvestigateByCore::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_CORE,
		] );
	}

	public function test_core_page_renders_file_status_and_activity_tables() :void {
		$renderData = (array)( $this->renderByCoreInnerPage()[ 'render_data' ] ?? [] );
		$fileStatusCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'file_status' ][ 'count' ] ?? 0 );
		$activityCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'activity' ][ 'count' ] ?? 0 );
		$expectedTableCount = ( $fileStatusCount > 0 ? 1 : 0 ) + ( $activityCount > 0 ? 1 : 0 );

		$payload = $this->renderByCorePage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->investigateDomXPath( $html );
		$this->assertHtmlContainsMarker( 'File Scan Status', $html, 'By-core file tab label marker' );
		$this->assertHtmlContainsMarker( 'Full Scan Results', $html, 'By-core file CTA label marker' );
		$this->assertInvestigateOverviewLabel( $xpath, 'WordPress Version', 'By-core overview table row marker' );
		$this->assertHtmlNotContainsMarker( 'Back To Investigate', $html, 'By-core back button removed marker' );
		$this->assertHtmlNotContainsMarker( 'investigate-summary-grid', $html, 'By-core summary cards removed marker' );

		$this->assertInvestigateDatatableCount( $xpath, $expectedTableCount, 'By-core datatable count marker' );
		$this->assertInvestigateTableTypeByCount( $xpath, 'file_scan_results', $fileStatusCount, 'By-core file status table marker' );
		$this->assertInvestigateTableTypeByCount( $xpath, 'activity', $activityCount, 'By-core activity table marker' );
		$this->assertInvestigateSubjectTypeByCount( $xpath, 'core', $expectedTableCount, 'By-core subject type marker' );
	}
}

