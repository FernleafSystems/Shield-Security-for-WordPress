<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\PageInvestigateLanding,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderInvestigateLandingPage( array $extra = [] ) :array {
		return $this->processActionPayloadWithAdminBypass(
			PageInvestigateLanding::SLUG,
			\array_merge(
				[
					Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
					Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_OVERVIEW,
				],
				$extra
			)
		);
	}

	public function test_landing_renders_drill_shell_tiles_and_single_panel_wrapper() :void {
		$payload = $this->renderInvestigateLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'investigate landing' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$layers = \is_array( $vars[ 'drill_shell' ][ 'layers' ] ?? null ) ? $vars[ 'drill_shell' ][ 'layers' ] : [];

		$this->assertModeShellPayload( $vars, 'investigate', 'investigate', false );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertArrayNotHasKey( 'subjects', $vars );
		$this->assertSame( [ 'subjects', 'panel' ], \array_column( $layers, 'key' ) );
		$this->assertSame( 0, (int)( $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
		$headerJson = \json_decode( (string)( $layers[ 0 ][ 'header_json' ] ?? '' ), true );
		$this->assertSame( 'investigate', (string)( $headerJson[ 'color_key' ] ?? '' ) );
		$header = $layers[ 0 ][ 'header' ] ?? [];
		$this->assertSame( [
			'compact_back_label',
			'active_back_label',
			'meta',
			'breadcrumb_label',
			'title',
			'summary',
			'focus',
			'next_step',
			'icon_class',
			'badge',
			'badge_status',
			'color_key',
			'actions',
		], \array_keys( $header ) );
		$this->assertSame( '', (string)( $header[ 'meta' ] ?? 'missing' ) );
		$this->assertSame( 'investigate', (string)( $header[ 'color_key' ] ?? '' ) );
		$this->assertSame( 'panel', (string)( $layers[ 1 ][ 'key' ] ?? '' ) );
		$this->assertSame( [], $layers[ 1 ][ 'header' ][ 'actions' ] ?? [] );
	}

	public function test_valid_deep_link_compacts_subject_layer_and_preloads_the_single_panel_wrapper() :void {
		$payload = $this->renderInvestigateLandingPage( [
			'subject'    => 'ip',
			'analyse_ip' => '203.0.113.88',
		] );
		$this->assertRouteRenderOutputHealthy( $payload, 'investigate landing deep link' );
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];
		$layers = \is_array( $vars[ 'drill_shell' ][ 'layers' ] ?? null ) ? $vars[ 'drill_shell' ][ 'layers' ] : [];

		$this->assertSame( 1, (int)( $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
		$this->assertSame( 'panel', (string)( $layers[ 1 ][ 'key' ] ?? '' ) );
		$this->assertSame( 'info', (string)( $layers[ 1 ][ 'header' ][ 'badge_status' ] ?? '' ) );
		$this->assertSame( 'investigate', (string)( $layers[ 1 ][ 'header' ][ 'color_key' ] ?? '' ) );
	}
}
