<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageDrillDownTestLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class DrillDownTestLandingIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_scans_overview_route_renders_temporary_drill_down_page() :void {
		$payload = $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_SCANS,
			PluginNavs::SUBNAV_SCANS_OVERVIEW
		);
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'temporary drill-down route' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-shell="1" and @data-drill-shell-mode="actions" and @data-drill-shell-id="actions_drill_shell"]',
			1,
			'Temporary drill shell route contract'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-layer]',
			3,
			'Temporary drill shell should keep only three normalized layers'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-layer-key="fourth_layer"]',
			0,
			'Fourth layer should be dropped by max-depth normalization'
		);
		$thirdLayer = $this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer="2"]',
			'Third layer should exist after normalization'
		);
		$this->assertSame(
			'finaldetail',
			$thirdLayer instanceof \DOMElement ? $thirdLayer->getAttribute( 'data-drill-layer-key' ) : ''
		);
		$thirdLayerContext = $this->decodeJsonAttribute(
			$thirdLayer,
			'data-drill-layer-context',
			'Third layer context'
		);
		$this->assertSame(
			[
				'path'      => [ 'Start', 'Queue', 'Bucket', 'Item' ],
				'focus'     => 'Review the selected item.',
				'next_step' => 'Take the specific recommended action.',
			],
			$thirdLayerContext
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-context-card="actions_drill_shell" and contains(@data-drill-context-mode, "actions")]',
			'Temporary context card route contract'
		);
	}

	public function test_temporary_drill_down_page_exposes_normalized_vars_for_deep_link() :void {
		$payload = $this->processActionPayloadWithAdminBypass( PageDrillDownTestLanding::SLUG, [
			'layer' => '1',
		] );
		$this->assertRouteRenderOutputHealthy( $payload, 'temporary drill-down deep-link payload' );
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];
		$drillShell = \is_array( $vars[ 'drill_shell' ] ?? null ) ? $vars[ 'drill_shell' ] : [];
		$drillCard = \is_array( $vars[ 'drill_context_card' ] ?? null ) ? $vars[ 'drill_context_card' ] : [];

		$this->assertSame( 'actions_drill_shell', $drillShell[ 'id' ] ?? '' );
		$this->assertSame( 'actions', $drillShell[ 'mode' ] ?? '' );
		$this->assertSame( 1, $drillShell[ 'active_index' ] ?? -1 );
		$this->assertCount( 3, $drillShell[ 'layers' ] ?? [] );
		$this->assertSame( [ false, true, false ], \array_column( $drillShell[ 'layers' ] ?? [], 'is_active' ) );
		$this->assertSame(
			[
				'path'      => [ 'Start', 'Queue', 'Bucket' ],
				'focus'     => 'Narrow the queue to a specific group.',
				'next_step' => 'Open the next layer for a concrete item.',
			],
			$drillCard[ 'initial_context' ] ?? []
		);
	}
}
