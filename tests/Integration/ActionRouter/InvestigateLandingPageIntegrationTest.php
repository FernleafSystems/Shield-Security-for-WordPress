<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxBatchRequests,
	Actions\AjaxRender,
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
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_OVERVIEW,
			$extra
		);
	}

	public function test_landing_renders_final_subject_tiles_and_disabled_premium_integrations_marker() :void {
		$payload = $this->renderInvestigateLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'investigate landing' );
		$xpath = $this->createDomXPathFromHtml( $html );
		$subjectDefinitions = PluginNavs::investigateLandingSubjectDefinitions();
		$enabledSubjectDefinitions = \array_filter(
			$subjectDefinitions,
			static fn( array $subject ) :bool => (bool)( $subject[ 'is_enabled' ] ?? false )
		);
		$expectedTileCount = \count( $subjectDefinitions );
		$expectedPanelCount = \count( $enabledSubjectDefinitions );
		$expectedLivePanelCount = \count( \array_filter(
			\array_keys( $enabledSubjectDefinitions ),
			static fn( string $subjectKey ) :bool => $subjectKey === 'live_traffic'
		) );
		$this->assertArrayHasKey( 'premium_integrations', $subjectDefinitions );
		$this->assertFalse(
			(bool)( $subjectDefinitions[ 'premium_integrations' ][ 'is_enabled' ] ?? true ),
			'Premium integrations subject must remain disabled.'
		);

		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-section="selector"]',
			'Landing selector section marker'
		);
		$this->assertModeShellAndAccentContract( $xpath, 'investigate', 'info', 'Investigate', true );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-shell="1" and @data-mode="investigate" and @data-mode-active-panel]',
			0,
			'Investigate landing mode shell has no active panel when no preload is supplied'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-section="selector"]//form',
			0,
			'Landing selector should not render lookup forms'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-section="selector"]/h5',
			0,
			'Landing selector should not render in-body heading'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-section="lookup-shell"]',
			0,
			'Landing lookup shell marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-landing-hint="1" and @aria-hidden="false"]',
			'Investigate landing hint is visible without active subject'
		);

		$landingNode = $this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-landing="1" and string-length(normalize-space(@data-investigate-batch-action)) > 0 and string-length(normalize-space(@data-investigate-panel-error)) > 0]',
			'Investigate landing root contract marker'
		);
		$batchActionData = $this->decodeJsonAttribute(
			$landingNode,
			'data-investigate-batch-action',
			'Investigate landing batch action contract'
		);
		$this->assertSame( ActionData::FIELD_SHIELD, $batchActionData[ ActionData::FIELD_ACTION ] ?? '' );
		$this->assertSame( AjaxBatchRequests::SLUG, $batchActionData[ ActionData::FIELD_EXECUTE ] ?? '' );

		foreach ( $subjectDefinitions as $subjectKey => $subjectDefinition ) {
			if ( (bool)( $subjectDefinition[ 'is_enabled' ] ?? false ) ) {
				$this->assertXPathExists(
					$xpath,
					'//button[@data-investigate-subject="'.$subjectKey.'" and @data-mode-panel-target="'.$subjectKey.'" and @aria-expanded="false"]',
					'Landing enabled subject button marker for '.$subjectKey
				);
			}
			else {
				$this->assertXPathExists(
					$xpath,
					'//div[@data-investigate-subject="'.$subjectKey.'" and @data-mode-tile-disabled="1" and @aria-disabled="true"]',
					'Landing disabled subject tile marker for '.$subjectKey
				);
			}
		}
		$this->assertXPathExists(
			$xpath,
			'//div[@data-investigate-subject="premium_integrations" and @data-mode-tile-disabled="1" and @aria-disabled="true"]',
			'Premium integrations tile disabled marker contract'
		);

		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-tile="1"]',
			$expectedTileCount,
			'Investigate shared mode tile marker count'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-mode-panel="1"]',
			$expectedPanelCount,
			'Investigate inline panel marker count'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-investigate-panel-loaded="0"]',
			$expectedPanelCount,
			'Investigate landing keeps all panels unloaded by default'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-investigate-panel-live="1"]',
			$expectedLivePanelCount,
			'Investigate landing live panel marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-mode-panel="1"]//*[@data-investigate-panel-tabs="1"]',
			$expectedPanelCount,
			'Investigate landing panel tabs host marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-mode-panel="1"]//*[@data-investigate-panel-content="1"]',
			$expectedPanelCount,
			'Investigate landing panel content host marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-mode-panel="1"]//*[@data-investigate-panel-header="1"]',
			$expectedPanelCount,
			'Investigate landing panel header host marker'
		);
		$this->assertSharedModePanelMarkerCount( $xpath, $expectedPanelCount, 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-panel', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-render-action', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-panel-loaded', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-panel-live', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'mode-panel-target-default', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'mode-panel-static-target', 'Investigate' );
		$this->assertXPathCount( $xpath, '//*[@data-mode-panel="1"]//button[@data-mode-panel-close="1"]', $expectedPanelCount, 'Investigate mode panel close button marker count' );

		foreach ( $enabledSubjectDefinitions as $subjectKey => $subjectDefinition ) {
			$panelNode = $this->assertXPathExists(
				$xpath,
				'//*[@data-investigate-panel="'.$subjectKey.'" and @data-mode-panel="1"]',
				'Investigate panel marker for '.$subjectKey
			);
			$this->assertInstanceOf( \DOMElement::class, $panelNode, 'Investigate panel node is a DOM element for '.$subjectKey );
			/** @var \DOMElement $panelNode */

			$this->assertSame(
				(string)( $subjectDefinition[ 'lookup_key' ] ?? '' ),
				(string)$panelNode->getAttribute( 'data-investigate-lookup-key' ),
				'Investigate panel lookup-key marker for '.$subjectKey
			);
			$this->assertSame(
				$subjectKey === 'live_traffic' ? '1' : '0',
				(string)$panelNode->getAttribute( 'data-investigate-panel-live' ),
				'Investigate panel live marker for '.$subjectKey
			);

			$renderActionData = $this->decodeJsonAttribute(
				$panelNode,
				'data-investigate-render-action',
				'Investigate panel render action contract for '.$subjectKey
			);
			$this->assertSame( ActionData::FIELD_SHIELD, $renderActionData[ ActionData::FIELD_ACTION ] ?? '' );
			$this->assertSame( AjaxRender::SLUG, $renderActionData[ ActionData::FIELD_EXECUTE ] ?? '' );
			$this->assertSame( ( $subjectDefinition[ 'render_action' ] )::SLUG, $renderActionData[ 'render_slug' ] ?? '' );
			$this->assertSame( $subjectDefinition[ 'render_nav' ], $renderActionData[ Constants::NAV_ID ] ?? '' );
			$this->assertSame( $subjectDefinition[ 'render_subnav' ], $renderActionData[ Constants::NAV_SUB_ID ] ?? '' );
		}
	}

	public function test_lookup_preload_hides_landing_hint_and_renders_subject_header_contract() :void {
		$enabledSubjectCount = \count( \array_filter(
			PluginNavs::investigateLandingSubjectDefinitions(),
			static fn( array $subject ) :bool => (bool)( $subject[ 'is_enabled' ] ?? false )
		) );

		$payload = $this->renderInvestigateLandingPage( [
			'subject'    => 'ip',
			'analyse_ip' => '203.0.113.88',
		] );
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'investigate landing preload' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-shell="1" and @data-mode-active-panel="ip"]',
			'Investigate landing mode-shell active panel marker for preload'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-landing-hint="1" and @aria-hidden="true"]',
			'Investigate landing hint is hidden when a panel is pre-opened'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip" and @aria-hidden="false" and @data-investigate-panel-loaded="1"]',
			'Investigate IP panel opens for preloaded lookup'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-investigate-panel-loaded="0"]',
			$enabledSubjectCount - 1,
			'Investigate preload leaves all non-active panels unloaded'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-header="1"]',
			'Investigate IP panel header host marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-header="1"]/following-sibling::*[@data-investigate-panel-tabs="1"]',
			'Investigate IP panel header host is wired into panel header area'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-content="1"]//*[@data-investigate-subject-header="1"]',
			'Investigate IP panel subject header is visible for preloaded lookup'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-panel-content="1"]//*[@data-investigate-subject-title="1" and normalize-space()="203.0.113.88"]',
			'Investigate IP panel subject header title matches lookup value'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-change-subject="1"]',
			'Investigate IP panel exposes change subject action'
		);
	}

}
