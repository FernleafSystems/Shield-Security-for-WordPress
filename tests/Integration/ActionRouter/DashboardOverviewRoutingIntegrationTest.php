<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	ActionResponse,
	Actions\Render\Components\Widgets\NeedsAttentionQueue,
	Actions\Render\PageAdminPlugin,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Dashboard\DashboardViewPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class DashboardOverviewRoutingIntegrationTest extends ShieldIntegrationTestCase {

	private int $adminUserId;

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->adminUserId = $this->loginAsSecurityAdmin();
		delete_user_meta( $this->adminUserId, DashboardViewPreference::META_KEY );
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderDashboardOverviewHtml() :string {
		$filter = self::con()->prefix( 'bypass_is_plugin_admin' );
		add_filter( $filter, '__return_true', 1000 );
		try {
			$response = $this->processor()->processAction( PageAdminPlugin::SLUG, [
				Constants::NAV_ID     => PluginNavs::NAV_DASHBOARD,
				Constants::NAV_SUB_ID => PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
			] );
			return (string)( $response->payload()[ 'render_output' ] ?? '' );
		}
		finally {
			remove_filter( $filter, '__return_true', 1000 );
		}
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

	public function test_unset_preference_renders_simple_overview_marker() :void {
		delete_user_meta( $this->adminUserId, DashboardViewPreference::META_KEY );

		$html = $this->renderDashboardOverviewHtml();
		$this->assertHtmlContainsMarker( 'dashboard-overview-simple', $html, 'Simple dashboard overview' );
	}

	public function test_advanced_preference_renders_advanced_overview_marker() :void {
		update_user_meta( $this->adminUserId, DashboardViewPreference::META_KEY, DashboardViewPreference::VIEW_ADVANCED );

		$html = $this->renderDashboardOverviewHtml();
		$this->assertHtmlNotContainsMarker( 'dashboard-overview-simple', $html, 'Advanced dashboard overview' );
		$this->assertHtmlContainsMarker( 'scan-strip', $html, 'Advanced dashboard overview' );
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
		$renderData = $this->renderNeedsAttentionQueue()->payload()[ 'render_data' ] ?? [];
		$chips = $renderData[ 'vars' ][ 'zone_chips' ] ?? [];

		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_items' ] ?? true ) );
		$this->assertCount( 8, $chips );
		$this->assertSame(
			[ 'scans', 'firewall', 'ips', 'login', 'users', 'spam', 'headers', 'secadmin' ],
			\array_column( $chips, 'slug' )
		);
	}

	public function test_last_scan_subtext_omitted_when_no_completed_scan() :void {
		$payload = $this->renderNeedsAttentionQueue()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertSame( '', (string)( $renderData[ 'strings' ][ 'last_scan_subtext' ] ?? '' ) );
		$this->assertHtmlNotContainsMarker( 'Last completed scan', $html, 'No scan-subtext state' );
	}
}
