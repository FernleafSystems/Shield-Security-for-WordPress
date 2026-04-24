<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	ActionResponse,
	Exceptions\UserAuthRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\WpDashboardSummary;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class WpDashboardSummaryIntegrationTest extends ShieldIntegrationTestCase {

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

		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		\delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderSummary() :ActionResponse {
		return $this->processor()->processAction( WpDashboardSummary::SLUG );
	}

	private function renderSummaryPayload() :array {
		$payload = $this->renderSummary()->payload();

		$this->assertArrayHasKey( 'render_data', $payload );
		$this->assertIsArray( $payload[ 'render_data' ] );
		$this->assertArrayHasKey( 'render_template', $payload );

		return $payload;
	}

	private function renderSummaryData() :array {
		return $this->renderSummaryPayload()[ 'render_data' ];
	}

	private function rowsByKey( array $renderData ) :array {
		$this->assertArrayHasKey( 'vars', $renderData );
		$this->assertIsArray( $renderData[ 'vars' ] );
		$this->assertArrayHasKey( 'rows', $renderData[ 'vars' ] );
		$this->assertIsArray( $renderData[ 'vars' ][ 'rows' ] );

		$rowsByKey = [];
		foreach ( $renderData[ 'vars' ][ 'rows' ] as $row ) {
			$this->assertIsArray( $row );
			$this->assertArrayHasKey( 'key', $row );
			$rowsByKey[ (string)$row[ 'key' ] ] = $row;
		}

		return $rowsByKey;
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

	private function ignoreCurrentMaintenanceIssues() :void {
		self::con()->opts
			->optSet(
				MaintenanceIssueStateProvider::OPT_KEY,
				( new MaintenanceIssueStateProvider() )->currentIssueIdentifiersByKey()
			)
			->store();
	}

	public function test_render_returns_actions_queue_widget_template_and_payload_contract() :void {
		$payload = $this->renderSummaryPayload();
		$renderData = $payload[ 'render_data' ];

		$this->assertIsString( $payload[ 'render_template' ] );
		$this->assertNotSame( '', $payload[ 'render_template' ] );
		$this->assertArrayHasKey( 'actions_queue', $renderData[ 'hrefs' ] );
		$this->assertIsString( $renderData[ 'hrefs' ][ 'actions_queue' ] );
		$this->assertNotSame( '', $renderData[ 'hrefs' ][ 'actions_queue' ] );
		$this->assertIsBool( $renderData[ 'flags' ][ 'show_internal_links' ] );
	}

	public function test_all_clear_renders_green_widget_contract_when_no_items_exist() :void {
		$this->ignoreCurrentMaintenanceIssues();

		$renderData = $this->renderSummaryData();

		$this->assertFalse( $renderData[ 'flags' ][ 'has_items' ] );
		$this->assertSame( 'good', $renderData[ 'vars' ][ 'shield_status' ] );
		$this->assertSame( 0, $renderData[ 'vars' ][ 'summary' ][ 'total_items' ] );
		$this->assertSame( [], $renderData[ 'vars' ][ 'rows' ] );
	}

	public function test_scan_findings_render_critical_rows_from_shared_queue_builder() :void {
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultMeta( $wpvId, 'is_vulnerable' );

		$renderData = $this->renderSummaryData();
		$rowsByKey = $this->rowsByKey( $renderData );

		$this->assertTrue( $renderData[ 'flags' ][ 'has_items' ] );
		$this->assertSame( 'critical', $renderData[ 'vars' ][ 'shield_status' ] );
		$this->assertArrayHasKey( 'malware', $rowsByKey );
		$this->assertArrayHasKey( 'vulnerable_assets', $rowsByKey );
		$this->assertSame( 2, $rowsByKey[ 'malware' ][ 'count' ] );
		$this->assertSame( 1, $rowsByKey[ 'vulnerable_assets' ][ 'count' ] );
		$this->assertSame( 'critical', $rowsByKey[ 'malware' ][ 'severity' ] );
		$this->assertArrayNotHasKey( 'icon_class', $rowsByKey[ 'malware' ] );
	}

	public function test_operational_issue_renders_warning_maintenance_only_state() :void {
		$this->setPluginUpdateAvailable();

		$renderData = $this->renderSummaryData();
		$rows = $renderData[ 'vars' ][ 'rows' ];

		$this->assertTrue( $renderData[ 'flags' ][ 'has_items' ] );
		$this->assertSame( 'warning', $renderData[ 'vars' ][ 'shield_status' ] );
		$this->assertSame( [ 'maintenance' ], \array_column( $rows, 'key' ) );
		$this->assertGreaterThan( 0, $rows[ 0 ][ 'count' ] );
	}

	public function test_wp_admin_without_security_admin_session_can_render_without_internal_links() :void {
		\wp_set_current_user( $this->adminUserId );
		$this->setSecurityAdminContext( false );

		$forceNotPluginAdmin = static fn() :bool => false;
		\add_filter( self::con()->prefix( 'is_plugin_admin' ), $forceNotPluginAdmin, \PHP_INT_MAX );

		try {
			$renderData = $this->renderSummaryData();

			$this->assertFalse( $renderData[ 'flags' ][ 'show_internal_links' ] );
			$this->assertArrayHasKey( 'actions_queue', $renderData[ 'hrefs' ] );
			$this->assertIsString( $renderData[ 'hrefs' ][ 'actions_queue' ] );
			$this->assertNotSame( '', $renderData[ 'hrefs' ][ 'actions_queue' ] );
		}
		finally {
			\remove_filter( self::con()->prefix( 'is_plugin_admin' ), $forceNotPluginAdmin, \PHP_INT_MAX );
		}
	}

	public function test_non_admin_user_cannot_render_dashboard_summary() :void {
		$subscriberId = self::factory()->user->create( [
			'role' => 'subscriber',
		] );
		\wp_set_current_user( $subscriberId );

		$this->expectException( UserAuthRequiredException::class );

		$this->renderSummary();
	}
}
