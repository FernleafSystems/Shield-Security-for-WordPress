<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	ActionResponse,
	Actions\ScanResultsTableAction,
	Actions\Render\ScanResultsLagWarning,
	Actions\Render\Components\Widgets\MaintenanceIssueStateProvider,
	Actions\Render\Components\Widgets\NeedsAttentionQueue,
	Actions\Render\PluginAdminPages\PageOperatorModeLanding,
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants as ReportingConstants;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

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
		$this->requireDb( 'reports' );

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

	private function renderDashboardOverviewPayload() :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_DASHBOARD,
			PluginNavs::SUBNAV_DASHBOARD_OVERVIEW
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

	private function pluginMainPathFragment( string $pluginSlug ) :string {
		return TestDataFactory::pathFragmentFromAbsolutePath( WP_PLUGIN_DIR.'/'.$pluginSlug );
	}

	/**
	 * @return list<string>
	 */
	private function getInstalledPluginFiles() :array {
		$pluginFiles = \array_values( \array_map(
			static fn( $file ) :string => (string)$file,
			\array_keys( Services::WpPlugins()->getPlugins() )
		) );
		\natsort( $pluginFiles );
		return \array_values( $pluginFiles );
	}

	/**
	 * @return list<string>
	 */
	private function requireAtLeastInactivePlugins( int $minimum ) :array {
		$inactivePlugins = \array_values( \array_diff(
			$this->getInstalledPluginFiles(),
			Services::WpPlugins()->getActivePlugins()
		) );

		if ( \count( $inactivePlugins ) < $minimum ) {
			$this->markTestSkipped( 'Not enough inactive plugins are available for this integration fixture.' );
		}

		return \array_slice( $inactivePlugins, 0, $minimum );
	}

	private function insertUnfinishedScan( string $scanSlug, string $status = 'queued', int $readyAt = 0 ) :int {
		$dbh = self::con()->db_con->scans;
		$record = $dbh->getRecord();
		$record->scan = $scanSlug;
		$record->status = $status;
		$record->ready_at = $readyAt;
		$dbh->getQueryInserter()->insert( $record );
		return (int)Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' );
	}

	public function test_counter_combinations_produce_expected_item_counts_and_severities() :void {
		$this->enablePremiumCapabilities( [
			'scan_file_areas',
			'scan_pluginsthemes_local',
		] );
		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp', 'plugins' ] )
			->store();

		$pluginSlug = self::con()->base_file;
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );
		TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );

		$payload = $this->renderNeedsAttentionQueue()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_items' ] ?? false ) );
		$this->assertSame( 'critical', (string)( $renderData[ 'vars' ][ 'overall_severity' ] ?? '' ) );

		$zone = $this->getZoneGroupBySlug( $renderData, 'scans' );
		$this->assertSame( 'critical', (string)( $zone[ 'severity' ] ?? '' ) );
		$this->assertSame( 3, (int)( $zone[ 'total_issues' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $renderData[ 'vars' ][ 'summary' ][ 'severity' ] ?? '' ) );
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

	public function test_scan_result_counts_refresh_after_item_action_without_cleaning_unrelated_stale_rows() :void {
		$this->enablePremiumCapabilities( [
			'scan_file_areas',
			'scan_pluginsthemes_local',
		] );
		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'plugins' ] )
			->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$active = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		$stale = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin'    => 1,
			'ptg_slug'        => $pluginSlug,
			'is_checksumfail' => 1,
		] );

		$initialRenderData = $this->renderNeedsAttentionQueue()->payload()[ 'render_data' ] ?? [];
		$initialZone = $this->getZoneGroupBySlug( $initialRenderData, 'scans' );
		$this->assertSame( 1, (int)( $initialZone[ 'total_issues' ] ?? 0 ) );

		$actionPayload = $this->processor()->processAction( ScanResultsTableAction::SLUG, [
			'sub_action' => 'ignore',
			'rids'       => [ (int)$active[ 'result_item_id' ] ],
		] )->payload();

		$this->assertTrue( $actionPayload[ 'success' ] ?? false );
		$this->assertTrue( $actionPayload[ 'table_reload' ] ?? false );
		$this->assertFalse( $actionPayload[ 'page_reload' ] ?? true );

		$refreshedRenderData = $this->renderNeedsAttentionQueue()->payload()[ 'render_data' ] ?? [];
		$refreshedZone = $this->getZoneGroupBySlug( $refreshedRenderData, 'scans' );
		$this->assertSame( 1, (int)( $refreshedZone[ 'total_issues' ] ?? -1 ) );

		$activeItem = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$active[ 'result_item_id' ] );
		$this->assertNotEmpty( $activeItem );
		$this->assertGreaterThan( 0, (int)( $activeItem->ignored_at ?? 0 ) );

		$staleItem = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$stale[ 'result_item_id' ] );
		$this->assertNotEmpty( $staleItem );
		$this->assertSame( 0, (int)( $staleItem->resolved_at ?? 0 ) );
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
	}

	public function test_zone_chips_always_cover_all_security_zones() :void {
		$payload = $this->renderNeedsAttentionQueue()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];
		$chips = $renderData[ 'vars' ][ 'zone_chips' ] ?? [];
		$expectedZoneSlugs = \array_keys( self::con()->comps->zones->getZones() );

		$this->assertCount( \count( $expectedZoneSlugs ), $chips );
		$this->assertSame(
			$expectedZoneSlugs,
			\array_column( $chips, 'slug' )
		);
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

	public function test_dashboard_runtime_warning_replaces_summary_subtext_while_scans_are_in_flight() :void {
		$this->insertUnfinishedScan( 'afs', 'queued' );

		$widgetPayload = $this->renderNeedsAttentionQueue()->payload();
		$widgetData = $widgetPayload[ 'render_data' ] ?? [];
		$dashboardPayload = $this->processActionPayloadWithAdminBypass( PageOperatorModeLanding::SLUG );
		$dashboardData = $dashboardPayload[ 'render_data' ] ?? [];
		$warning = ( new ScanResultsLagWarning() )->getText();

		$this->assertNotSame( '', $warning );
		$this->assertSame( $warning, (string)( $widgetData[ 'strings' ][ 'status_strip_subtext' ] ?? '' ) );
		$this->assertSame( $warning, (string)( $widgetData[ 'vars' ][ 'summary' ][ 'subtext' ] ?? '' ) );
		$this->assertSame( $warning, (string)( $dashboardData[ 'strings' ][ 'subtitle' ] ?? '' ) );
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
		$this->assertRouteRenderOutputHealthy(
			$this->renderDashboardOverviewPayload(),
			'dashboard overview route'
		);
	}

	public function test_operator_mode_landing_reports_lane_exposes_count_and_latest_report_badges() :void {
		TestDataFactory::insertReport( 'Daily Report', [
			'type'       => ReportingConstants::REPORT_TYPE_INFO,
			'created_at' => \time() - HOUR_IN_SECONDS,
		] );
		TestDataFactory::insertReport( 'Alert Report', [
			'type'       => ReportingConstants::REPORT_TYPE_ALERT,
			'created_at' => \time() - 2*HOUR_IN_SECONDS,
		] );

		$renderData = $this->processActionPayloadWithAdminBypass( PageOperatorModeLanding::SLUG )[ 'render_data' ] ?? [];
		$reportsLane = $this->getLaneByMode( $renderData, PluginNavs::MODE_REPORTS );
		$badges = $reportsLane[ 'indicator_badges' ] ?? [];

		$this->assertSame( 'status', $reportsLane[ 'indicator_type' ] ?? '' );
		$this->assertCount( 3, $badges );
		$this->assertSame( '2 reports', $badges[ 0 ][ 'text' ] ?? '' );
		$this->assertStringStartsWith( 'Last report: ', $badges[ 1 ][ 'text' ] ?? '' );
		$this->assertStringStartsWith( 'Last alert: ', $badges[ 2 ][ 'text' ] ?? '' );
		$this->assertSame( 'info', $badges[ 0 ][ 'severity' ] ?? '' );
		$this->assertSame( 'warning', $badges[ 2 ][ 'severity' ] ?? '' );
		$this->assertNotSame( '', (string)( $badges[ 1 ][ 'title' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $badges[ 2 ][ 'title' ] ?? '' ) );
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

	public function test_operator_mode_landing_actions_queue_rows_include_seeded_scan_counts_and_maintenance_row() :void {
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
			'scan_pluginsthemes_local',
			'scan_vulnerabilities',
		] );
		$themeSlug = \wp_get_theme()->get_stylesheet();

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'enable_wpvuln_scan', 'Y' )
			->optSet( 'enabled_scan_apc', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes', 'malware_php' ] )
			->store();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_in_core' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => self::con()->base_file,
			'is_in_plugin' => 1,
			'ptg_slug'     => self::con()->base_file,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => $themeSlug,
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultMeta( $wpvId, 'is_vulnerable' );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultMeta( $apcId, 'is_abandoned' );

		$this->setPluginUpdateAvailable();
		$this->resetScanResultCountMemoization();

		$payload = $this->processActionPayloadWithAdminBypass( PageOperatorModeLanding::SLUG );
		$renderData = $payload[ 'render_data' ] ?? [];
		$rows = $this->getActionsQueueRows( $renderData );
		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );
		$rowsByKey = [];
		foreach ( $rows as $row ) {
			if ( \is_array( $row ) && !empty( $row[ 'key' ] ) ) {
				$rowsByKey[ (string)$row[ 'key' ] ] = $row;
			}
		}

		foreach ( [ 'malware', 'vulnerable_assets', 'wp_files', 'plugin_files', 'theme_files', 'abandoned', 'maintenance' ] as $key ) {
			$this->assertArrayHasKey( $key, $rowsByKey );
		}
		foreach ( [ 'malware', 'vulnerable_assets', 'wp_files', 'plugin_files', 'theme_files', 'abandoned' ] as $scanKey ) {
			$this->assertSame( 1, (int)( $rowsByKey[ $scanKey ][ 'count' ] ?? 0 ) );
		}
		$this->assertGreaterThan( 0, (int)( $rowsByKey[ 'maintenance' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'actions', (string)( $renderData[ 'vars' ][ 'actions_lane' ][ 'mode' ] ?? '' ) );
		$this->assertNull( $renderData[ 'vars' ][ 'actions_all_clear' ] ?? null );
		$this->assertSame(
			[ 'investigate', 'configure', 'reports' ],
			\array_column( $renderData[ 'vars' ][ 'secondary_lanes' ] ?? [], 'mode' )
		);
		$descriptionNode = $this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " operator-mode-landing__featured-desc ")]',
			'Dashboard featured Actions Queue card should keep the standard description when actionable rows exist'
		);
		$this->assertSame(
			\trim( (string)( $renderData[ 'vars' ][ 'actions_lane' ][ 'description' ] ?? '' ) ),
			\trim( (string)$descriptionNode->textContent )
		);
	}

	public function test_operator_mode_landing_hides_ignored_only_plugin_dashboard_row() :void {
		$this->enablePremiumCapabilities( [
			'scan_file_areas',
			'scan_pluginsthemes_local',
		] );

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'plugins' ] )
			->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$activePluginSlug = self::con()->base_file;
		$ignoredPluginSlug = $this->requireAtLeastInactivePlugins( 1 )[ 0 ];

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->pluginMainPathFragment( $activePluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $activePluginSlug,
		] );
		$ignored = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->pluginMainPathFragment( $ignoredPluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $ignoredPluginSlug,
		] );
		TestDataFactory::markScanResultItemIgnored( (int)$ignored[ 'result_item_id' ] );
		$this->resetScanResultCountMemoization();

		$renderData = $this->processActionPayloadWithAdminBypass( PageOperatorModeLanding::SLUG )[ 'render_data' ] ?? [];
		$rows = $this->getActionsQueueRows( $renderData );
		$rowsByKey = [];
		foreach ( $rows as $row ) {
			if ( \is_array( $row ) && !empty( $row[ 'key' ] ) ) {
				$rowsByKey[ (string)$row[ 'key' ] ] = $row;
			}
		}

		$this->assertArrayHasKey( 'plugin_files', $rowsByKey );
		$this->assertArrayNotHasKey( 'plugin_files_ignored', $rowsByKey );
		$this->assertSame( 1, (int)( $rowsByKey[ 'plugin_files' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $rowsByKey[ 'plugin_files' ][ 'severity' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $renderData[ 'vars' ][ 'actions_lane' ][ 'indicator_severity' ] ?? '' ) );
	}

	public function test_operator_mode_landing_omits_healthy_file_locker_and_zero_maintenance_rows() :void {
		$this->requireDb( 'file_locker' );
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );
		$optionsSnapshot = $this->snapshotSelectedOptions( [ MaintenanceIssueStateProvider::OPT_KEY ] );

		try {
			self::con()->opts
				->optSet( 'file_locker', [ 'wpconfig' ] )
				->optSet(
					MaintenanceIssueStateProvider::OPT_KEY,
					( new MaintenanceIssueStateProvider() )->currentIssueIdentifiersByKey()
				)
				->store();

			TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php' );
			self::con()->comps->file_locker->clearLocks();

			$payload = $this->processActionPayloadWithAdminBypass( PageOperatorModeLanding::SLUG );
			$renderData = $payload[ 'render_data' ] ?? [];
			$rows = $this->getActionsQueueRows( $renderData );
			$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );

			$this->assertSame( [], $rows );
			$this->assertSame( 'good', (string)( $renderData[ 'vars' ][ 'actions_lane' ][ 'indicator_severity' ] ?? '' ) );
			$this->assertSame(
				[ 'scans', 'maintenance' ],
				\array_column( $renderData[ 'vars' ][ 'actions_all_clear' ][ 'zone_chips' ] ?? [], 'slug' )
			);
			$descriptionNode = $this->assertXPathExists(
				$xpath,
				'//*[contains(concat(" ", normalize-space(@class), " "), " operator-mode-landing__featured-desc ")]',
				'Dashboard featured Actions Queue card should render the moved all-clear explanation copy'
			);
			$this->assertSame(
				\trim( (string)( $renderData[ 'vars' ][ 'actions_all_clear' ][ 'subtitle' ] ?? '' ) ),
				\trim( (string)$descriptionNode->textContent )
			);
			$this->assertXPathCount(
				$xpath,
				'//*[contains(concat(" ", normalize-space(@class), " "), " operator-mode-landing__featured ")]//*[contains(concat(" ", normalize-space(@class), " "), " shield-needs-attention__chip ")]',
				2,
				'Dashboard featured Actions Queue card should render the moved clear-state zone chips'
			);
			$this->assertXPathCount(
				$xpath,
				'//*[contains(concat(" ", normalize-space(@class), " "), " operator-mode-landing__featured ")]//*[contains(concat(" ", normalize-space(@class), " "), " operator-mode-landing__queue-row ")]',
				0,
				'Dashboard featured Actions Queue card should not render queue rows in the clear state'
			);
		}
		finally {
			$this->restoreSelectedOptions( $optionsSnapshot );
		}
	}

}
