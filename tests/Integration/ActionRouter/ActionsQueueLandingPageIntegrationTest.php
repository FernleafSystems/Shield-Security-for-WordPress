<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\PageActionsQueueLanding,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	BuiltMetersFixture,
	HtmlDomAssertions,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ActionsQueueLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use BuiltMetersFixture;
	use HtmlDomAssertions;
	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->resetBuiltMetersCache();
		$this->setOverallConfigMeterComponents( [] );
	}

	public function tear_down() {
		$this->resetBuiltMetersCache();
		parent::tear_down();
	}

	private function renderActionsQueueLandingPage() :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_SCANS,
			PluginNavs::SUBNAV_SCANS_OVERVIEW
		);
	}

	private function renderActionsQueueLandingInnerPage() :array {
		return $this->processActionPayloadWithAdminBypass(
			PageActionsQueueLanding::SLUG,
			[
				Constants::NAV_ID     => PluginNavs::NAV_SCANS,
				Constants::NAV_SUB_ID => PluginNavs::SUBNAV_SCANS_OVERVIEW,
			]
		);
	}

	public function test_all_clear_state_renders_page_banner_and_compact_widget_without_duplicate_copy() :void {
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		$innerPayload = $this->renderActionsQueueLandingInnerPage();
		$subtext = (string)( $innerPayload[ 'render_data' ][ 'strings' ][ 'all_clear_subtext' ] ?? '' );
		$this->assertNotSame( '', $subtext, 'Expected all-clear temporal context from queue payload.' );
		$this->assertTrue( (bool)( $innerPayload[ 'render_data' ][ 'flags' ][ 'queue_is_empty' ] ?? false ) );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing' );

		$xpath = $this->createDomXPathFromHtml( $html );
		$this->assertModeShellAndAccentContract( $xpath, 'actions', 'critical', 'Actions' );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-header-density="compact"]',
			'Actions compact header marker'
		);
		$this->assertXPathCount( $xpath, '//*[@data-mode-tiles="1"]', 0, 'Actions landing does not introduce mode tile grid' );
		$this->assertXPathCount( $xpath, '//*[@data-mode-tile="1"]', 0, 'Actions landing does not introduce mode tiles' );
		$this->assertXPathCount( $xpath, '//*[@data-mode-panel="1"]', 0, 'Actions landing does not introduce mode panel shell' );
		$this->assertXPathExists( $xpath, '//*[@data-actions-queue-section="all-clear-context"]', 'All-clear context banner marker' );
		$this->assertXPathExists( $xpath, '//*[@data-needs-attention-all-clear-mode="compact"]', 'Compact all-clear widget mode marker' );
		$this->assertXPathCount( $xpath, '//*[@data-needs-attention-status-strip="1"]', 0, 'Issue status strip hidden in compact all-clear mode' );
		$this->assertXPathCount( $xpath, '//*[@data-needs-attention-widget-copy="all-clear"]', 0, 'No duplicated widget all-clear copy marker' );
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-cta="scan-results"]', 0, 'Scan results CTA hidden when queue is clear' );
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-cta="run-scan"]', 1, 'Run scan CTA shown when queue is clear' );
		$this->assertHtmlContainsMarker( $subtext, $html, 'All-clear temporal context is present in output' );
	}

	public function test_active_items_state_hides_page_banner() :void {
		$this->setOverallConfigMeterComponents( [
			[
				'slug'              => 'wp_updates',
				'is_protected'      => false,
				'title'             => 'WordPress Version',
				'title_unprotected' => 'WordPress Version',
				'desc_unprotected'  => 'There is an upgrade available for WordPress.',
				'href_full'         => self::con()->plugin_urls->adminHome(),
				'fix'               => 'Fix',
			],
		] );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing with active items' );

		$xpath = $this->createDomXPathFromHtml( $html );
		$this->assertModeShellContract( $xpath, 'actions', 'Actions' );
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-section="all-clear-context"]', 0, 'All-clear context banner hidden when queue has items' );
		$this->assertXPathCount( $xpath, '//*[@data-needs-attention-all-clear-mode="compact"]', 0, 'No compact all-clear marker when queue has items' );
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-cta="scan-results"]', 1, 'Scan results CTA shown when queue has items' );
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-cta="run-scan"]', 1, 'Run scan CTA shown when queue has items' );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-needs-attention-status-strip="1" and @data-needs-attention-status="has-issues"]',
			'Queue issue-state marker when items exist'
		);
	}

	public function test_scan_result_issue_state_hides_page_banner() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing with scan-result items' );

		$xpath = $this->createDomXPathFromHtml( $html );
		$this->assertModeShellContract( $xpath, 'actions', 'Actions' );
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-section="all-clear-context"]', 0, 'All-clear context banner hidden when scan-result issues exist' );
		$this->assertXPathCount( $xpath, '//*[@data-needs-attention-all-clear-mode="compact"]', 0, 'No compact all-clear marker when scan-result issues exist' );
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-cta="scan-results"]', 1, 'Scan results CTA shown when scan-result issues exist' );
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-cta="run-scan"]', 1, 'Run scan CTA shown when scan-result issues exist' );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-needs-attention-status-strip="1" and @data-needs-attention-status="has-issues"]',
			'Queue issue-state marker when scan-result items exist'
		);
	}
}
