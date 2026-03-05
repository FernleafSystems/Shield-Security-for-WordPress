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
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertSame( true, $renderData[ 'vars' ][ 'tables' ][ 'file_status' ][ 'is_flat' ] ?? null );
		$this->assertSame( true, $renderData[ 'vars' ][ 'tables' ][ 'activity' ][ 'is_flat' ] ?? null );

		$payload = $this->renderByCorePage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->investigateDomXPath( $html );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-landing="1"]',
			'Legacy by-core route renders investigate landing'
		);
		$this->assertXPathExists(
			$xpath,
			'//button[@data-investigate-subject="core" and @data-mode-panel-target="core" and @aria-expanded="true"]',
			'Legacy by-core route marks core tile active'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-shell="1" and @data-mode-active-panel="core"]',
			'Legacy by-core route mode shell active panel marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="core" and @aria-hidden="false" and @data-investigate-panel-loaded="1"]',
			'Legacy by-core route opens core panel'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="core"]//*[@data-investigate-panel-header="1"]',
			'Legacy by-core route panel header host marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-panel="core"]//*[@data-investigate-change-subject="1"]',
			0,
			'Legacy by-core route does not render change-subject action'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-panel="core"]//*[@data-investigate-panel-content="1"]//*[contains(concat(" ", normalize-space(@class), " "), " inner-page-body-shell ")]',
			0,
			'By-core panel content should not include nested inner-page-body shell'
		);

		$this->assertInvestigateDatatableCount( $xpath, $expectedTableCount, 'By-core datatable count marker' );
		$this->assertInvestigateTableTypeByCount( $xpath, 'file_scan_results', $fileStatusCount, 'By-core file status table marker' );
		$this->assertInvestigateTableTypeByCount( $xpath, 'activity', $activityCount, 'By-core activity table marker' );
		$this->assertInvestigateSubjectTypeByCount( $xpath, 'core', $expectedTableCount, 'By-core subject type marker' );
	}
}
