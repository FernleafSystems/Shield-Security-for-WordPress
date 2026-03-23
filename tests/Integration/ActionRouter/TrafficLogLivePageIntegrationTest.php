<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\PageTrafficLogLive,
	Actions\Render\PluginAdminPages\TrafficLogLivePanelBody,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class TrafficLogLivePageIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderLiveTrafficPage() :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_TRAFFIC,
			PluginNavs::SUBNAV_LIVE
		);
	}

	private function renderLiveTrafficInnerPage() :array {
		return $this->processActionPayloadWithAdminBypass( PageTrafficLogLive::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_TRAFFIC,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_LIVE,
		] );
	}

	private function renderLiveTrafficPanelBody() :array {
		return $this->processActionPayloadWithAdminBypass( TrafficLogLivePanelBody::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_TRAFFIC,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_LIVE,
		] );
	}

	public function test_live_traffic_route_and_render_actions_share_the_same_markup_contract() :void {
		$routeHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderLiveTrafficPage(),
			'traffic live route'
		);
		$this->assertStringContainsString( 'SectionTrafficLiveLogs', $routeHtml );

		$fullHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderLiveTrafficInnerPage(),
			'traffic live full page action'
		);
		$this->assertStringContainsString( 'data-inner-page-body-shell="1"', $fullHtml );
		$this->assertStringContainsString( 'SectionTrafficLiveLogs', $fullHtml );

		$panelHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderLiveTrafficPanelBody(),
			'traffic live panel body action'
		);
		$this->assertStringContainsString( 'SectionTrafficLiveLogs', $panelHtml );
		$this->assertStringContainsString( 'shield-live-logs--light', $panelHtml );
		$this->assertStringNotContainsString( 'data-inner-page-body-shell="1"', $panelHtml );
	}
}
