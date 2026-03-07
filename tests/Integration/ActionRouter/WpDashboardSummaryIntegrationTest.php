<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	ActionResponse
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\WpDashboardSummary;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class WpDashboardSummaryIntegrationTest extends ShieldIntegrationTestCase {

	private const WIDGET_CACHE_KEY = 'dashboard-widget-v3-vars';
	private const LEGACY_WIDGET_CACHE_KEY = 'dashboard-widget-v2-vars';

	private int $adminUserId;

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->adminUserId = $this->loginAsSecurityAdmin();
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
			'scan_vulnerabilities',
		] );

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'enable_wpvuln_scan', 'Y' )
			->optSet( 'enabled_scan_apc', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			->store();

		Transient::Delete( self::con()->prefix( self::WIDGET_CACHE_KEY ) );
		Transient::Delete( self::con()->prefix( self::LEGACY_WIDGET_CACHE_KEY ) );
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		Transient::Delete( self::con()->prefix( self::WIDGET_CACHE_KEY ) );
		Transient::Delete( self::con()->prefix( self::LEGACY_WIDGET_CACHE_KEY ) );
		\delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderSummary( $refresh = true ) :ActionResponse {
		return $this->processor()->processAction( WpDashboardSummary::SLUG, [
			'refresh' => $refresh,
		] );
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

	public function test_render_returns_v2_template_and_non_empty_output() :void {
		$payload = $this->renderSummary()->payload();

		$this->assertSame( '/admin/admin_dashboard_widget_v2.twig', (string)( $payload[ 'render_template' ] ?? '' ) );
		$this->assertNotSame( '', \trim( (string)( $payload[ 'render_output' ] ?? '' ) ) );
	}

	public function test_config_score_uses_zone_posture_contract() :void {
		$vars = $this->renderSummary()->payload()[ 'render_data' ][ 'vars' ] ?? [];

		$percentage = (int)( $vars[ 'config_progress' ][ 'percentage' ] ?? -1 );
		$this->assertGreaterThanOrEqual( 0, $percentage );
		$this->assertLessThanOrEqual( 100, $percentage );
		$this->assertSame( $percentage, (int)( $vars[ 'config_progress' ][ 'totals' ][ 'percentage' ] ?? -1 ) );
		$this->assertSame(
			$percentage > 80 ? 'good' : ( $percentage > 40 ? 'warning' : 'critical' ),
			(string)( $vars[ 'config_traffic' ] ?? '' )
		);
	}

	public function test_action_total_and_severity_reflect_scan_findings() :void {
		$this->assertTrue( self::con()->comps->scans->AFS()->isEnabledMalwareScanPHP() );
		$this->assertTrue( self::con()->comps->scans->WPV()->isEnabled() );

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultMeta( $wpvId, 'is_vulnerable' );

		$vars = $this->renderSummary()->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertGreaterThanOrEqual( 2, (int)( $vars[ 'action_total' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $vars[ 'action_traffic' ] ?? '' ) );
	}

	public function test_all_clear_state_when_no_action_items_exist() :void {
		$payload = $this->renderSummary()->payload();
		$vars = $payload[ 'render_data' ][ 'vars' ] ?? [];

		$this->assertTrue( (bool)( $vars[ 'is_all_clear' ] ?? false ) );
		$this->assertSame( 0, (int)( $vars[ 'action_total' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $vars[ 'action_traffic' ] ?? '' ) );
	}

	public function test_operational_issue_is_included_in_attention_rows() :void {
		$this->setPluginUpdateAvailable();
		$vars = $this->renderSummary()->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertGreaterThanOrEqual( 1, (int)( $vars[ 'action_total' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $vars[ 'action_traffic' ] ?? '' ) );
		$this->assertFalse( (bool)( $vars[ 'is_all_clear' ] ?? true ) );
	}

	public function test_non_plugin_admin_hides_internal_links() :void {
		$subscriberId = self::factory()->user->create( [
			'role' => 'subscriber',
		] );
		\wp_set_current_user( $subscriberId );

		$payload = $this->renderSummary()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'show_internal_links' ] ?? true ) );
		$this->assertStringNotContainsString( 'href="'.self::con()->plugin_urls->adminHome().'"', $html, 'Subscriber dashboard links' );
		$this->assertStringNotContainsString(
			'href="'.self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ).'"',
			$html,
			'Subscriber scans links'
		);
	}

	public function test_refresh_parameter_controls_cache_bypass() :void {
		$first = $this->renderSummary( true )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 0, (int)( $first[ 'action_total' ] ?? -1 ) );

		$this->setPluginUpdateAvailable();
		$cached = $this->renderSummary( false )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 0, (int)( $cached[ 'action_total' ] ?? -1 ) );

		$refreshed = $this->renderSummary( true )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertGreaterThanOrEqual( 1, (int)( $refreshed[ 'action_total' ] ?? 0 ) );
	}

	public function test_refresh_false_string_does_not_bypass_cache() :void {
		$first = $this->renderSummary( true )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 0, (int)( $first[ 'action_total' ] ?? -1 ) );

		$this->setPluginUpdateAvailable();
		$cached = $this->renderSummary( 'false' )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 0, (int)( $cached[ 'action_total' ] ?? -1 ) );

		$refreshed = $this->renderSummary( 'true' )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertGreaterThanOrEqual( 1, (int)( $refreshed[ 'action_total' ] ?? 0 ) );
	}

	public function test_legacy_v2_transient_is_removed_during_v3_regeneration() :void {
		$legacyKey = self::con()->prefix( self::LEGACY_WIDGET_CACHE_KEY );
		$v3Key = self::con()->prefix( self::WIDGET_CACHE_KEY );

		Transient::Set( $legacyKey, [ 'legacy' => true ], 30 );
		Transient::Delete( $v3Key );
		$this->assertNotEmpty( Transient::Get( $legacyKey ) );

		$this->renderSummary( false );
		$this->assertEmpty( Transient::Get( $legacyKey ) );
	}
}
