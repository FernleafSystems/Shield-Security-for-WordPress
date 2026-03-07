<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	ActionResponse,
	Actions\Render\Components\Widgets\NeedsAttentionQueue,
	Actions\Render\PluginAdminPages\PageOperatorModeLanding,
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class DashboardOverviewRoutingIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use PluginAdminRouteRenderAssertions;

	private int $adminUserId;

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->adminUserId = $this->loginAsSecurityAdmin();
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		\delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	private function setPluginUpdateAvailable() :void {
		$updates = new \stdClass();
		$updates->response = [
			self::con()->base_file => (object)[
				'plugin'      => self::con()->base_file,
				'new_version' => self::con()->cfg->version().'.1',
			],
		];
		\set_site_transient( 'update_plugins', $updates );
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderDashboardOverviewHtml() :string {
		$payload = $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_DASHBOARD,
			PluginNavs::SUBNAV_DASHBOARD_OVERVIEW
		);
		return (string)( $payload[ 'render_output' ] ?? '' );
	}

	public function test_dashboard_sidebar_renders_separate_logo_assets_and_version_beneath_logo() :void {
		$html = $this->renderDashboardOverviewHtml();
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[contains(@class,"sidebar-logo-link")]',
			'Sidebar logo link marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//img[contains(@class,"sidebar-logo-banner")]',
			'Sidebar banner logo marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//img[contains(@class,"sidebar-logo-icon")]',
			'Sidebar icon logo marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(@class,"logo-container")]//*[contains(@class,"shield-sidebar-version") and normalize-space()!=""]',
			'Sidebar version beneath logo marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(@class,"shield-sidebar-footer")]',
			0,
			'Sidebar footer version removed'
		);
	}

	public function test_dashboard_overview_includes_labelled_super_search_and_offcanvas_shells() :void {
		$xpath = $this->createDomXPathFromHtml( $this->renderDashboardOverviewHtml() );

		$this->assertXPathExists(
			$xpath,
			'//*[@id="AptoOffcanvas" and @aria-labelledby="AptoOffcanvasLabel"]',
			'Shared offcanvas root labelled-by contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="AptoOffcanvasLabel" and contains(@class,"offcanvas-title")]',
			'Shared offcanvas title id contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="ModalSuperSearchBox" and @aria-labelledby="ModalSuperSearchTitle" and @aria-modal="true"]',
			'Super search modal labelled-by contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="ModalSuperSearchTitle" and contains(@class,"visually-hidden") and normalize-space()!=""]',
			'Super search modal hidden title'
		);
		$this->assertXPathExists(
			$xpath,
			'//label[@for="ModalSuperSearchInput" and contains(@class,"visually-hidden")]',
			'Super search input hidden label'
		);
		$this->assertXPathExists(
			$xpath,
			'//input[@id="ModalSuperSearchInput" and contains(@class,"search-text")]',
			'Super search input id contract'
		);
	}

	private function renderNeedsAttentionQueue() :ActionResponse {
		return $this->processor()->processAction( NeedsAttentionQueue::SLUG );
	}

	private function getZoneGroupBySlug( array $renderData, string $slug ) :array {
		$zoneGroups = $renderData[ 'vars' ][ 'zone_groups' ] ?? [];
		$matches = \array_values( \array_filter(
			\is_array( $zoneGroups ) ? $zoneGroups : [],
			fn( $zone ) => \is_array( $zone ) && (string)( $zone[ 'slug' ] ?? '' ) === $slug
		) );

		$this->assertCount(
			1,
			$matches,
			\sprintf( 'Expected exactly one "%s" zone group.', $slug )
		);

		return $matches[ 0 ] ?? [];
	}

	private function getZoneItemKeys( array $renderData ) :array {
		$zoneGroups = \is_array( $renderData[ 'vars' ][ 'zone_groups' ] ?? null ) ? $renderData[ 'vars' ][ 'zone_groups' ] : [];
		$itemKeys = [];

		foreach ( $zoneGroups as $zoneGroup ) {
			if ( !\is_array( $zoneGroup ) ) {
				continue;
			}
			foreach ( $zoneGroup[ 'items' ] ?? [] as $item ) {
				if ( !\is_array( $item ) ) {
					continue;
				}
				$key = (string)( $item[ 'key' ] ?? '' );
				if ( $key !== '' ) {
					$itemKeys[] = $key;
				}
			}
		}

		return $itemKeys;
	}

	private function getLaneByMode( array $renderData, string $mode ) :array {
		$lanes = \is_array( $renderData[ 'vars' ][ 'lanes' ] ?? null ) ? $renderData[ 'vars' ][ 'lanes' ] : [];
		$matches = \array_values( \array_filter(
			$lanes,
			static fn( array $lane ) :bool => (string)( $lane[ 'mode' ] ?? '' ) === $mode
		) );
		$this->assertCount( 1, $matches, \sprintf( 'Expected exactly one "%s" lane.', $mode ) );
		return $matches[ 0 ] ?? [];
	}

	private function getActionsQueueRows( array $renderData ) :array {
		$rows = $renderData[ 'vars' ][ 'actions_queue_rows' ] ?? [];
		$this->assertIsArray( $rows );
		return $rows;
	}

	public function test_counter_combinations_produce_expected_item_counts_and_severities() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_plugin' );

		$payload = $this->renderNeedsAttentionQueue()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_items' ] ?? false ) );
		$this->assertSame( 'critical', (string)( $renderData[ 'vars' ][ 'overall_severity' ] ?? '' ) );

		$zone = $this->getZoneGroupBySlug( $renderData, 'scans' );
		$this->assertSame( 'critical', (string)( $zone[ 'severity' ] ?? '' ) );
		$this->assertSame( 3, (int)( $zone[ 'total_issues' ] ?? 0 ) );
		$this->assertSame( 3, (int)( $renderData[ 'vars' ][ 'total_items' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $renderData[ 'vars' ][ 'summary' ][ 'severity' ] ?? '' ) );
		$this->assertSame( 3, (int)( $renderData[ 'vars' ][ 'summary' ][ 'total_items' ] ?? 0 ) );
	}

	public function test_scan_result_counts_refresh_after_memoization_reset() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );

		$initialRenderData = $this->renderNeedsAttentionQueue()->payload()[ 'render_data' ] ?? [];
		$initialZone = $this->getZoneGroupBySlug( $initialRenderData, 'scans' );
		$this->assertSame( 1, (int)( $initialZone[ 'total_issues' ] ?? 0 ) );

		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );

		$staleRenderData = $this->renderNeedsAttentionQueue()->payload()[ 'render_data' ] ?? [];
		$staleZone = $this->getZoneGroupBySlug( $staleRenderData, 'scans' );
		$this->assertSame( 1, (int)( $staleZone[ 'total_issues' ] ?? 0 ) );

		$this->resetScanResultCountMemoization();

		$refreshedRenderData = $this->renderNeedsAttentionQueue()->payload()[ 'render_data' ] ?? [];
		$refreshedZone = $this->getZoneGroupBySlug( $refreshedRenderData, 'scans' );
		$this->assertSame( 2, (int)( $refreshedZone[ 'total_issues' ] ?? 0 ) );
	}

	public function test_disabled_malware_wpv_apc_do_not_inject_rows() :void {
		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'N' )
			->optSet( 'enable_wpvuln_scan', 'N' )
			->optSet( 'enabled_scan_apc', 'N' )
			->store();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultMeta( $wpvId, 'is_vulnerable' );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultMeta( $apcId, 'is_abandoned' );

		$renderData = $this->renderNeedsAttentionQueue()->payload()[ 'render_data' ] ?? [];
		$itemKeys = $this->getZoneItemKeys( $renderData );
		$this->assertNotContains( 'malware', $itemKeys );
		$this->assertNotContains( 'vulnerable_assets', $itemKeys );
		$this->assertNotContains( 'abandoned', $itemKeys );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_items' ] ?? true ) );
		$this->assertSame( 0, (int)( $renderData[ 'vars' ][ 'total_items' ] ?? -1 ) );
	}

	public function test_all_clear_state_includes_all_8_zone_chips() :void {
		$payload = $this->renderNeedsAttentionQueue()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];
		$chips = $renderData[ 'vars' ][ 'zone_chips' ] ?? [];
		$expectedZoneSlugs = \array_keys( self::con()->comps->zones->getZones() );

		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_items' ] ?? true ) );
		$this->assertCount( \count( $expectedZoneSlugs ), $chips );
		$this->assertSame(
			$expectedZoneSlugs,
			\array_column( $chips, 'slug' )
		);
		$this->assertSame( 'good', (string)( $renderData[ 'vars' ][ 'summary' ][ 'severity' ] ?? '' ) );
		$this->assertSame( 0, (int)( $renderData[ 'vars' ][ 'summary' ][ 'total_items' ] ?? -1 ) );
	}

	public function test_operational_issue_adds_action_item() :void {
		$this->setPluginUpdateAvailable();

		$renderData = $this->renderNeedsAttentionQueue()->payload()[ 'render_data' ] ?? [];
		$zone = $this->getZoneGroupBySlug( $renderData, 'maintenance' );
		$itemKeys = \array_column( $zone[ 'items' ] ?? [], 'key' );

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_items' ] ?? false ) );
		$this->assertContains( 'wp_plugins_updates', $itemKeys );
	}

	public function test_last_scan_subtext_omitted_when_no_completed_scan() :void {
		$payload = $this->renderNeedsAttentionQueue()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];

		$this->assertSame( '', (string)( $renderData[ 'strings' ][ 'last_scan_subtext' ] ?? '' ) );
		$this->assertSame( '', (string)( $renderData[ 'vars' ][ 'summary' ][ 'subtext' ] ?? '' ) );
	}

	public function test_operator_mode_landing_lanes_are_in_expected_order() :void {
		$payload = $this->processActionPayloadWithAdminBypass( PageOperatorModeLanding::SLUG );
		$renderData = $payload[ 'render_data' ] ?? [];
		$lanes = $renderData[ 'vars' ][ 'lanes' ] ?? [];

		$this->assertCount( 4, $lanes );
		$this->assertSame(
			[ 'actions', 'investigate', 'configure', 'reports' ],
			\array_column( $lanes, 'mode' )
		);
		$this->assertSame(
			[ 'status', 'status', 'posture', 'status' ],
			\array_column( $lanes, 'indicator_type' )
		);
		$this->assertNotSame( '', (string)( $renderData[ 'strings' ][ 'title' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $renderData[ 'strings' ][ 'subtitle' ] ?? '' ) );
		$this->assertContains(
			(string)( $renderData[ 'vars' ][ 'shield_status' ] ?? '' ),
			[ 'good', 'warning', 'critical' ]
		);
		$this->assertStringStartsWith(
			'bi bi-shield',
			(string)( $renderData[ 'vars' ][ 'shield_icon_class' ] ?? '' )
		);
	}

	public function test_operator_mode_landing_exposes_live_monitor_contract() :void {
		$payload = $this->processActionPayloadWithAdminBypass( PageOperatorModeLanding::SLUG );
		$renderData = $payload[ 'render_data' ] ?? [];
		$liveMonitor = $renderData[ 'vars' ][ 'live_monitor' ] ?? [];
		$actionsLane = $this->getLaneByMode( $renderData, PluginNavs::MODE_ACTIONS );

		$this->assertIsArray( $liveMonitor );
		$this->assertArrayHasKey( 'is_collapsed', $liveMonitor );
		$this->assertArrayHasKey( 'title', $liveMonitor );
		$this->assertArrayHasKey( 'activity', $liveMonitor );
		$this->assertArrayHasKey( 'traffic', $liveMonitor );
		$this->assertArrayHasKey( 'loading', $liveMonitor );
		$this->assertArrayNotHasKey( 'minimize', $liveMonitor );
		$this->assertArrayNotHasKey( 'expand', $liveMonitor );
		$this->assertContains( $actionsLane[ 'indicator_severity' ] ?? '', [ 'good', 'warning', 'critical' ] );
		$this->assertNotSame( '', \trim( (string)( $payload[ 'render_output' ] ?? '' ) ) );
	}

	public function test_operator_mode_landing_actions_queue_rows_use_scan_counts_and_maintenance_totals() :void {
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
			'scan_pluginsthemes_local',
			'scan_vulnerabilities',
		] );

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'enable_wpvuln_scan', 'Y' )
			->optSet( 'enabled_scan_apc', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes', 'malware_php' ] )
			->store();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_in_core' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_in_plugin' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_in_theme' );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultMeta( $wpvId, 'is_vulnerable' );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultMeta( $apcId, 'is_abandoned' );

		$this->setPluginUpdateAvailable();
		$this->resetScanResultCountMemoization();

		$renderData = $this->processActionPayloadWithAdminBypass( PageOperatorModeLanding::SLUG )[ 'render_data' ] ?? [];
		$rows = $this->getActionsQueueRows( $renderData );

		$this->assertSame(
			[
				'malware',
				'vulnerable_assets',
				'wp_files',
				'plugin_files',
				'theme_files',
				'abandoned',
				'maintenance',
			],
			\array_column( $rows, 'key' )
		);
		$this->assertSame( [ 1, 1, 1, 1, 1, 1, 1 ], \array_column( $rows, 'count' ) );
		$this->assertSame( 'actions', (string)( $renderData[ 'vars' ][ 'actions_lane' ][ 'mode' ] ?? '' ) );
		$this->assertSame(
			[ 'investigate', 'configure', 'reports' ],
			\array_column( $renderData[ 'vars' ][ 'secondary_lanes' ] ?? [], 'mode' )
		);
	}

	public function test_dashboard_overview_renders_featured_actions_card_with_inline_orb_and_three_side_cards() :void {
		$xpath = $this->createDomXPathFromHtml( $this->renderDashboardOverviewHtml() );

		$this->assertXPathExists(
			$xpath,
			'//*[contains(@class,"operator-mode-landing__featured")]',
			'Featured actions queue card'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(@class,"operator-mode-landing__featured")]//*[contains(@class,"shield-orb")]',
			'Shield orb merged into featured card header'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(@class,"operator-mode-landing__side-card")]',
			3,
			'Three secondary mode cards'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(@class,"dashboard-live-monitor")]',
			'Live monitor remains rendered'
		);
	}
}
