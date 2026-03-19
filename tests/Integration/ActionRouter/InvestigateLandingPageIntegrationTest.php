<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\PageInvestigateLanding,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
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
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'investigate landing' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertModeShellPayload( $vars, 'investigate', 'info', false );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertArrayNotHasKey( 'subjects', $vars );
		$this->assertSame( [ 'subjects', 'panel' ], \array_column( $vars[ 'drill_shell' ][ 'layers' ] ?? [], 'key' ) );
		$this->assertSame( 0, (int)( $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
		$this->assertSame( 'Back to Investigate', (string)( $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'header' ][ 'compact_back_label' ] ?? '' ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-section="drilldown"]/div[1][@data-drill-shell="1" and @data-drill-shell-mode="investigate"]',
			'Investigate landing should render the drill shell first'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer-key="subjects" and string-length(@data-drill-layer-header) > 0]',
			'Investigate landing should render producer-owned layer header JSON'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer-key="subjects"]//*[@data-drill-layer-compact-back="1"]',
			'Investigate landing should render the shared drill back control'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " investigate-landing__section-label ") and normalize-space()="Choose a subject to investigate"]',
			'Investigate landing should render the heading inside the subjects layer'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " investigate-landing__subject-card ")]',
			7,
			'Investigate landing should render the seven canonical subject tiles'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-target="panel" and @data-investigate-render-action and @data-investigate-header]',
			6,
			'Investigate landing should render six enabled drill target buttons'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-subject="premium_integrations" and @aria-disabled="true" and contains(concat(" ", normalize-space(@class), " "), " is-disabled ")]',
			'Investigate landing should keep premium integrations disabled'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-panel="1" and @data-investigate-panel-loaded="0" and @data-investigate-panel-subject="" and @data-investigate-render-action=""]',
			'Investigate landing should render one unloaded panel wrapper in layer 2'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel="1"]',
			1,
			'Investigate landing should use one panel wrapper only'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-tile or @data-mode-panel-target or @data-mode-panel="1"]',
			0,
			'Investigate landing should not render legacy mode-panel markup'
		);
	}

	public function test_valid_deep_link_compacts_subject_layer_and_preloads_the_single_panel_wrapper() :void {
		$payload = $this->renderInvestigateLandingPage( [
			'subject'    => 'ip',
			'analyse_ip' => '203.0.113.88',
		] );
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'investigate landing deep link' );
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 1, (int)( $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
		$this->assertSame( 'IP Address', (string)( $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' ) );
		$this->assertNotSame( '', \trim( (string)( $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? '' ) ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer="1"]//*[@data-drill-layer-header-title="1" and normalize-space()="IP Address"]',
			'Deep-linked Investigate landing should preload the IP lane header'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer="1"]//*[@data-investigate-panel="1" and @data-investigate-panel-subject="ip" and @data-investigate-panel-loaded="1"]',
			'Deep-linked Investigate landing should preload the shared panel wrapper'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer="1"]//*[@data-investigate-panel-header="1"]//*[@data-investigate-subject-header="1"]',
			'Deep-linked Investigate landing should preload the subject header inside the shared panel chrome'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer="1"]//*[@data-investigate-panel-content="1"]//*[contains(concat(" ", normalize-space(@class), " "), " investigate-inline-ipanalyse ")]',
			'Deep-linked Investigate landing should preload the routed panel content inside the shared panel wrapper'
		);
	}
}
