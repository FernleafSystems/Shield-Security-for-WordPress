<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\InvestigateByCorePanelBody,
	Actions\Render\PluginAdminPages\PageInvestigateByCore,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByCorePageIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

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

	private function renderByCorePanelBody() :array {
		return $this->processActionPayloadWithAdminBypass( InvestigateByCorePanelBody::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_CORE,
		] );
	}

	public function test_core_page_renders_file_status_and_activity_tables() :void {
		$renderData = (array)( $this->renderByCoreInnerPage()[ 'render_data' ] ?? [] );
		$tables = (array)( $renderData[ 'vars' ][ 'tables' ] ?? [] );
		$fileStatusCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'file_status' ][ 'count' ] ?? 0 );
		$activityCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'activity' ][ 'count' ] ?? 0 );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame( true, $tables[ 'file_status' ][ 'is_flat' ] ?? null );
		$this->assertSame( true, $tables[ 'activity' ][ 'is_flat' ] ?? null );
		$this->assertSame( 'core', (string)( $tables[ 'file_status' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( 'core', (string)( $tables[ 'activity' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( $fileStatusCount > 0, isset( $tables[ 'file_status' ][ 'table_type' ] ) );
		$this->assertSame( $activityCount > 0, isset( $tables[ 'activity' ][ 'table_type' ] ) );

		$payload = $this->renderByCorePage();
		$this->assertRouteRenderOutputHealthy( $payload, 'legacy by-core route' );
		$routeVars = (array)( $payload[ 'render_data' ][ 'vars' ] ?? [] );
		$subjects = [];
		foreach ( (array)( $routeVars[ 'subjects' ] ?? [] ) as $subject ) {
			if ( \is_array( $subject ) && isset( $subject[ 'key' ] ) ) {
				$subjects[ (string)$subject[ 'key' ] ] = $subject;
			}
		}

		$this->assertSame( 'core', (string)( $routeVars[ 'mode_panel' ][ 'active_target' ] ?? '' ) );
		$this->assertTrue( (bool)( $routeVars[ 'mode_panel' ][ 'is_open' ] ?? false ) );
		$this->assertTrue( (bool)( $subjects[ 'core' ][ 'is_loaded' ] ?? false ) );
		$this->assertSame( '', (string)( $subjects[ 'core' ][ 'lookup_key' ] ?? '' ) );
	}

	public function test_full_page_and_panel_body_actions_share_the_same_core_markup_contract() :void {
		$fullHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderByCoreInnerPage(),
			'investigate by-core full page action'
		);
		$this->assertStringContainsString( 'data-inner-page-body-shell="1"', $fullHtml );
		$this->assertStringContainsString( 'data-investigate-subject-header="1"', $fullHtml );

		$panelHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderByCorePanelBody(),
			'investigate by-core panel body action'
		);
		$this->assertStringContainsString( 'data-investigate-subject-header="1"', $panelHtml );
		$this->assertStringContainsString( 'ShieldInvestigateByCoreTabsNav', $panelHtml );
		$this->assertStringNotContainsString( 'data-inner-page-body-shell="1"', $panelHtml );
	}
}
