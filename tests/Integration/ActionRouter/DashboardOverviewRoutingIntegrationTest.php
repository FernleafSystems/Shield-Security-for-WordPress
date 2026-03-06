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
	BuiltMetersFixture,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class DashboardOverviewRoutingIntegrationTest extends ShieldIntegrationTestCase {

	use BuiltMetersFixture, PluginAdminRouteRenderAssertions;

	private int $adminUserId;

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->adminUserId = $this->loginAsSecurityAdmin();
		$this->resetBuiltMetersCache();
		$this->setOverallConfigMeterComponents( [] );
	}

	public function tear_down() {
		$this->resetBuiltMetersCache();
		parent::tear_down();
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

		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertHtmlContainsMarker( 'data-needs-attention-status="has-issues"', $html, 'Needs attention strip with issues' );
		$this->assertHtmlContainsMarker( 'shield-needs-attention__zone-card', $html, 'Needs attention zone cards' );
		$this->assertHtmlContainsMarker( 'shield-needs-attention__zone-icon', $html, 'Needs attention zone icon' );
		$this->assertHtmlContainsMarker( 'shield-needs-attention__item-action', $html, 'Needs attention item action' );
		$this->assertHtmlContainsMarker( 'bi bi-arrow-right', $html, 'Needs attention action arrow icon' );
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

		$html = (string)( $this->renderNeedsAttentionQueue()->payload()[ 'render_output' ] ?? '' );
		$this->assertHtmlNotContainsMarker( 'Malware', $html, 'Disabled scan rows' );
		$this->assertHtmlNotContainsMarker( 'Vulnerable Assets', $html, 'Disabled scan rows' );
		$this->assertHtmlNotContainsMarker( 'Abandoned Assets', $html, 'Disabled scan rows' );
	}

	public function test_all_clear_state_includes_all_8_zone_chips() :void {
		$payload = $this->renderNeedsAttentionQueue()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$chips = $renderData[ 'vars' ][ 'zone_chips' ] ?? [];
		$expectedZoneSlugs = \array_keys( self::con()->comps->zones->getZones() );

		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_items' ] ?? true ) );
		$this->assertCount( \count( $expectedZoneSlugs ), $chips );
		$this->assertSame(
			$expectedZoneSlugs,
			\array_column( $chips, 'slug' )
		);
		$this->assertHtmlContainsMarker( 'shield-needs-attention__all-clear-card', $html, 'Needs attention all-clear card' );
		$this->assertHtmlContainsMarker( 'shield-needs-attention__all-clear-shield', $html, 'Needs attention all-clear shield icon' );
		$this->assertHtmlContainsMarker( 'bi bi-check-circle-fill', $html, 'Needs attention all-clear chip icon' );
	}

	public function test_unprotected_maintenance_meter_component_adds_action_item() :void {
		$this->setOverallConfigMeterComponents( [
			[
				'slug'            => 'wp_updates',
				'is_protected'    => false,
				'title'           => 'WordPress Version',
				'title_unprotected' => 'WordPress Version',
				'desc_unprotected'=> 'There is an upgrade available for WordPress.',
				'href_full'       => self::con()->plugin_urls->adminHome(),
				'fix'             => 'Fix',
			],
		] );

		$renderData = $this->renderNeedsAttentionQueue()->payload()[ 'render_data' ] ?? [];
		$zone = $this->getZoneGroupBySlug( $renderData, 'maintenance' );
		$itemKeys = \array_column( $zone[ 'items' ] ?? [], 'key' );

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_items' ] ?? false ) );
		$this->assertContains( 'wp_updates', $itemKeys );
	}

	public function test_last_scan_subtext_omitted_when_no_completed_scan() :void {
		$payload = $this->renderNeedsAttentionQueue()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertSame( '', (string)( $renderData[ 'strings' ][ 'last_scan_subtext' ] ?? '' ) );
		$this->assertHtmlNotContainsMarker( 'Last scan:', $html, 'No scan-subtext state' );
	}

	public function test_operator_mode_landing_grid_cells_are_in_expected_order() :void {
		$payload = $this->processActionPayloadWithAdminBypass( PageOperatorModeLanding::SLUG );
		$renderData = $payload[ 'render_data' ] ?? [];
		$cells = $renderData[ 'vars' ][ 'mode_grid_cells' ] ?? [];

		$this->assertCount( 4, $cells );
		$this->assertSame(
			[ 'actions', 'investigate', 'configure', 'reports' ],
			\array_column( $cells, 'mode' )
		);
		$this->assertSame(
			[ 'status', 'status', 'posture', 'status' ],
			\array_column( $cells, 'indicator_type' )
		);
	}

	public function test_operator_mode_landing_includes_live_monitor_contract_and_markup() :void {
		$payload = $this->processActionPayloadWithAdminBypass( PageOperatorModeLanding::SLUG );
		$renderData = $payload[ 'render_data' ] ?? [];
		$liveMonitor = $renderData[ 'vars' ][ 'live_monitor' ] ?? [];
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertIsArray( $liveMonitor );
		$this->assertArrayHasKey( 'is_collapsed', $liveMonitor );
		$this->assertArrayHasKey( 'title', $liveMonitor );
		$this->assertArrayHasKey( 'activity', $liveMonitor );
		$this->assertArrayHasKey( 'traffic', $liveMonitor );
		$this->assertArrayHasKey( 'loading', $liveMonitor );
		$this->assertSame( 'Recent WP Activity Events', (string)( $liveMonitor[ 'activity' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'minimize', $liveMonitor );
		$this->assertArrayNotHasKey( 'expand', $liveMonitor );
		$this->assertHtmlContainsMarker( 'data-dashboard-live-monitor="1"', $html, 'Live monitor root marker' );
		$this->assertHtmlContainsMarker( 'data-live-monitor-toggle="1"', $html, 'Live monitor toggle marker' );
		$this->assertHtmlContainsMarker( 'data-live-monitor-output="ticker"', $html, 'Live monitor ticker output marker' );
		$this->assertHtmlContainsMarker( 'data-live-monitor-output="traffic"', $html, 'Live monitor traffic output marker' );
		$this->assertHtmlContainsMarker( 'Recent WP Activity Events', $html, 'Live monitor activity lane label' );
		$this->assertSame( 1, \substr_count( $html, 'data-dashboard-live-monitor="1"' ) );
		$this->assertSame( 1, \substr_count( $html, 'data-live-monitor-toggle="1"' ) );
	}
}
