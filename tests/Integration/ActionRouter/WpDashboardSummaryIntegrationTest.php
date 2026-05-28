<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	ActionResponse,
	Exceptions\UserAuthRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\{
	MaintenanceIssueStateProvider,
	WpDashboardSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class WpDashboardSummaryIntegrationTest extends ShieldIntegrationTestCase {

	private int $adminUserId;
	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'admin_access_key',
			MaintenanceIssueStateProvider::OPT_KEY,
		] );

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
			->optSet(
				MaintenanceIssueStateProvider::OPT_KEY,
				( new MaintenanceIssueStateProvider() )->currentIssueIdentifiersByKey()
			)
			->store();

		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		\delete_site_transient( 'update_plugins' );
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
		}
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
		$this->assertArrayHasKey( 'render_output', $payload );
		$this->assertIsString( $payload[ 'render_output' ] );

		return $payload;
	}

	private function renderSummaryPayloadWithPluginAdminBypass() :array {
		$filter = self::con()->prefix( 'bypass_is_plugin_admin' );
		\add_filter( $filter, '__return_true', 1000 );

		try {
			return $this->renderSummaryPayload();
		}
		finally {
			\remove_filter( $filter, '__return_true', 1000 );
		}
	}

	private function renderSummaryData() :array {
		return $this->renderSummaryPayload()[ 'render_data' ];
	}

	private function expectedActionsQueueHref() :string {
		$entry = PluginNavs::defaultEntryForMode( PluginNavs::MODE_ACTIONS );
		return self::con()->plugin_urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] );
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
		$payload = $this->renderSummaryPayloadWithPluginAdminBypass();
		$renderData = $payload[ 'render_data' ];

		$this->assertSame( '/wpadmin/components/widget/dashboard_actions_queue.twig', $payload[ 'render_template' ] );
		$this->assertFalse( $renderData[ 'flags' ][ 'is_security_admin_restricted' ] );
		$this->assertTrue( $renderData[ 'flags' ][ 'show_issue_count' ] );
		$this->assertTrue( $renderData[ 'flags' ][ 'show_issue_details' ] );
		$this->assertSame( $this->expectedActionsQueueHref(), $renderData[ 'hrefs' ][ 'cta' ] );
		$this->assertArrayNotHasKey( 'summary', $renderData[ 'vars' ] );
	}

	public function test_all_clear_renders_green_widget_contract_when_no_items_exist() :void {
		$this->ignoreCurrentMaintenanceIssues();

		$renderData = $this->renderSummaryData();

		$this->assertFalse( $renderData[ 'flags' ][ 'has_items' ] );
		$this->assertSame( 'good', $renderData[ 'vars' ][ 'shield_status' ] );
		$this->assertSame( 0, $renderData[ 'vars' ][ 'issue_count' ] );
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
		$this->assertSame( 3, $renderData[ 'vars' ][ 'issue_count' ] );
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
		$this->assertGreaterThan( 0, $renderData[ 'vars' ][ 'issue_count' ] );
		$this->assertSame( [ 'maintenance' ], \array_column( $rows, 'key' ) );
		$this->assertGreaterThan( 0, $rows[ 0 ][ 'count' ] );
	}

	public function test_wp_admin_without_security_admin_feature_renders_actions_queue_link() :void {
		self::con()->opts
			->optSet( 'admin_access_key', '' )
			->store();
		\wp_set_current_user( $this->adminUserId );
		$this->setSecurityAdminContext( false );

		$forceNotPluginAdmin = static fn() :bool => false;
		\add_filter( self::con()->prefix( 'is_plugin_admin' ), $forceNotPluginAdmin, \PHP_INT_MAX );

		try {
			$payload = $this->renderSummaryPayload();
			$renderData = $payload[ 'render_data' ];
			$expectedHref = $this->expectedActionsQueueHref();
			$xpath = $this->dashboardWidgetXPath( $payload[ 'render_output' ] );

			$this->assertFalse( $renderData[ 'flags' ][ 'is_security_admin_restricted' ] );
			$this->assertTrue( $renderData[ 'flags' ][ 'show_issue_count' ] );
			$this->assertTrue( $renderData[ 'flags' ][ 'show_issue_details' ] );
			$this->assertArrayHasKey( 'cta', $renderData[ 'hrefs' ] );
			$this->assertSame( $expectedHref, $renderData[ 'hrefs' ][ 'cta' ] );
			$this->assertSame(
				1,
				$xpath->query(
					'//a[@href='.$this->xpathLiteral( $expectedHref ).' and contains(concat(" ", normalize-space(@class), " "), " shield-dashboard-widget__cta ")]'
				)->length
			);
		}
		finally {
			\remove_filter( self::con()->prefix( 'is_plugin_admin' ), $forceNotPluginAdmin, \PHP_INT_MAX );
		}
	}

	public function test_wp_admin_without_security_admin_session_receives_restricted_widget_contract() :void {
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );

		self::con()->opts
			->optSet( 'admin_access_key', \wp_hash_password( 'dashboard-widget-pin' ) )
			->store();
		\wp_set_current_user( $this->adminUserId );
		$this->setSecurityAdminContext( false );

		$forceNotPluginAdmin = static fn() :bool => false;
		\add_filter( self::con()->prefix( 'is_plugin_admin' ), $forceNotPluginAdmin, \PHP_INT_MAX );

		try {
			$payload = $this->renderSummaryPayload();
			$renderData = $payload[ 'render_data' ];
			$expectedHref = self::con()->plugin_urls->adminHome();
			$xpath = $this->dashboardWidgetXPath( $payload[ 'render_output' ] );

			$this->assertTrue( $renderData[ 'flags' ][ 'has_items' ] );
			$this->assertTrue( $renderData[ 'flags' ][ 'is_security_admin_restricted' ] );
			$this->assertFalse( $renderData[ 'flags' ][ 'show_issue_count' ] );
			$this->assertFalse( $renderData[ 'flags' ][ 'show_issue_details' ] );
			$this->assertSame( $expectedHref, $renderData[ 'hrefs' ][ 'cta' ] );
			$this->assertSame( 'warning', $renderData[ 'vars' ][ 'shield_status' ] );
			$this->assertSame( 0, $renderData[ 'vars' ][ 'issue_count' ] );
			$this->assertSame( [], $renderData[ 'vars' ][ 'rows' ] );
			$this->assertArrayNotHasKey( 'summary', $renderData[ 'vars' ] );
			$this->assertSame(
				1,
				$xpath->query( '//a[@href='.$this->xpathLiteral( $expectedHref ).']' )->length
			);
			$this->assertSame(
				0,
				$xpath->query(
					'//*[contains(concat(" ", normalize-space(@class), " "), " shield-dashboard-widget__count ")]'
				)->length
			);
			$this->assertSame(
				0,
				$xpath->query(
					'//*[contains(concat(" ", normalize-space(@class), " "), " shield-dashboard-widget__row ")]'
				)->length
			);
		}
		finally {
			\remove_filter( self::con()->prefix( 'is_plugin_admin' ), $forceNotPluginAdmin, \PHP_INT_MAX );
		}
	}

	private function dashboardWidgetXPath( string $html ) :\DOMXPath {
		$dom = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		try {
			$dom->loadHTML( '<?xml encoding="utf-8" ?><div>'.$html.'</div>', \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD );
		}
		finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}
		return new \DOMXPath( $dom );
	}

	private function xpathLiteral( string $value ) :string {
		return \strpos( $value, "'" ) !== false
			? 'concat(\''.implode( '\',"\'",\'', \explode( "'", $value ) ).'\')'
			: '\''.$value.'\'';
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
