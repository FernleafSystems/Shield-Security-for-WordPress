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

	private function requireRenderData( array $payload ) :array {
		$this->assertIsArray( $payload[ 'render_data' ] );

		return $payload[ 'render_data' ];
	}

	public function test_live_traffic_route_and_render_actions_share_the_same_structured_render_contract() :void {
		$routePayload = $this->renderLiveTrafficPage();
		$fullPayload = $this->renderLiveTrafficInnerPage();
		$panelPayload = $this->renderLiveTrafficPanelBody();

		$routeRenderData = $this->requireRenderData( $routePayload );
		$fullRenderData = $this->requireRenderData( $fullPayload );
		$panelRenderData = $this->requireRenderData( $panelPayload );

		$this->assertIsArray( $routeRenderData[ 'vars' ] );
		$routeVars = $routeRenderData[ 'vars' ];

		$this->assertSame( PluginNavs::SUBNAV_LIVE, $routeVars[ 'active_module_settings' ] );
		$this->assertSame(
			$fullRenderData[ 'ajax' ][ 'load_live_logs' ],
			$panelRenderData[ 'ajax' ][ 'load_live_logs' ]
		);
		$this->assertSame(
			$fullRenderData[ 'flags' ][ 'is_enabled' ],
			$panelRenderData[ 'flags' ][ 'is_enabled' ]
		);
		$this->assertSame(
			$fullRenderData[ 'strings' ][ 'inner_page_title' ],
			$panelRenderData[ 'strings' ][ 'inner_page_title' ]
		);
		$this->assertSame(
			$fullRenderData[ 'strings' ][ 'waiting_live_logs' ],
			$panelRenderData[ 'strings' ][ 'waiting_live_logs' ]
		);
		$this->assertSame(
			$fullRenderData[ 'imgs' ][ 'inner_page_title_icon' ],
			$panelRenderData[ 'imgs' ][ 'inner_page_title_icon' ]
		);
		$this->assertNotSame( '', $fullRenderData[ 'strings' ][ 'live_view_status' ] );
		$this->assertNotSame( '', $panelRenderData[ 'strings' ][ 'live_view_status' ] );
	}
}
