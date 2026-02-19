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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\BuiltMetersFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class DashboardOverviewRoutingIntegrationTest extends ShieldIntegrationTestCase {

	use BuiltMetersFixture;

	private int $adminUserId;

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->adminUserId = $this->loginAsSecurityAdmin();
		delete_user_meta( $this->adminUserId, DashboardViewPreference::META_KEY );
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
		$this->assertHtmlContainsMarker( 'data-dashboard-view="simple"', $html, 'Simple dashboard panel marker' );
		$this->assertHtmlContainsMarker( 'data-dashboard-view="advanced"', $html, 'Advanced dashboard panel marker' );
		$this->assertHtmlContainsMarker( 'dashboard-overview-panel--simple is-active', $html, 'Simple dashboard active panel state' );
		$this->assertHtmlNotContainsMarker( 'dashboard-overview-panel--advanced is-active', $html, 'Advanced dashboard inactive panel state' );
		$this->assertHtmlContainsMarker( 'dashboard-overview-simple', $html, 'Simple dashboard overview' );
		$this->assertHtmlContainsMarker( 'dashboard-view-switch is-simple', $html, 'Simple dashboard toggle state' );
		$this->assertHtmlContainsMarker( 'dashboard-view-switch__toggle', $html, 'Simple dashboard toggle control' );
		$this->assertHtmlContainsMarker( 'dashboard-view-switch__label--simple is-active', $html, 'Simple dashboard active toggle label' );
		$this->assertHtmlNotContainsMarker( 'dashboard-view-switch__label--advanced is-active', $html, 'Advanced dashboard inactive toggle label' );
		$this->assertHtmlContainsMarker( 'Simple View', $html, 'Simple dashboard toggle left label' );
		$this->assertHtmlContainsMarker( 'Advanced View', $html, 'Simple dashboard toggle target label' );
	}

	public function test_advanced_preference_renders_advanced_overview_marker() :void {
		update_user_meta( $this->adminUserId, DashboardViewPreference::META_KEY, DashboardViewPreference::VIEW_ADVANCED );

		$html = $this->renderDashboardOverviewHtml();
		$this->assertHtmlContainsMarker( 'data-dashboard-view="simple"', $html, 'Simple dashboard panel marker' );
		$this->assertHtmlContainsMarker( 'data-dashboard-view="advanced"', $html, 'Advanced dashboard panel marker' );
		$this->assertHtmlContainsMarker( 'dashboard-overview-panel--advanced is-active', $html, 'Advanced dashboard active panel state' );
		$this->assertHtmlNotContainsMarker( 'dashboard-overview-panel--simple is-active', $html, 'Simple dashboard inactive panel state' );
		$this->assertHtmlContainsMarker( 'dashboard-overview-simple', $html, 'Simple dashboard overview marker in combined render' );
		$this->assertHtmlContainsMarker( 'scan-strip', $html, 'Advanced dashboard overview' );
		$this->assertHtmlContainsMarker( 'dashboard-view-switch is-advanced', $html, 'Advanced dashboard toggle state' );
		$this->assertHtmlContainsMarker( 'dashboard-view-switch__toggle', $html, 'Advanced dashboard toggle control' );
		$this->assertHtmlContainsMarker( 'dashboard-view-switch__label--advanced is-active', $html, 'Advanced dashboard active toggle label' );
		$this->assertHtmlNotContainsMarker( 'dashboard-view-switch__label--simple is-active', $html, 'Simple dashboard inactive toggle label' );
		$this->assertHtmlContainsMarker( 'Simple View', $html, 'Advanced dashboard toggle target label' );
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
		$this->assertHtmlContainsMarker( 'shield-needs-attention__status-strip has-issues', $html, 'Needs attention strip with issues' );
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
		$zone = $this->getZoneGroupBySlug( $renderData, 'scans' );
		$itemKeys = \array_column( $zone[ 'items' ] ?? [], 'label' );

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_items' ] ?? false ) );
		$this->assertContains( 'WordPress Version', $itemKeys );
	}

	public function test_last_scan_subtext_omitted_when_no_completed_scan() :void {
		$payload = $this->renderNeedsAttentionQueue()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertSame( '', (string)( $renderData[ 'strings' ][ 'last_scan_subtext' ] ?? '' ) );
		$this->assertHtmlNotContainsMarker( 'Last scan:', $html, 'No scan-subtext state' );
	}
}
