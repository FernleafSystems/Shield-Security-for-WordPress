<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\{
	Init\SetScanCompleted,
	ScanStatus
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	AutoReportCoordinator,
	BuildAlertDigestContract,
	Constants,
	ReportGenerator,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard\Scan\Support\AfsAssetChangeIntegrationSupport;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CompiledReportAssetFixture;
use FernleafSystems\Wordpress\Services\Services;

class ScheduledAlertNotificationIntegrationTest extends ShieldIntegrationTestCase {

	use LocalEmailCapture;
	use AfsAssetChangeIntegrationSupport;

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'reports' );
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_items' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		try {
			RuntimeTestState::requireDbHandler( 'file_locker', true );
		}
		catch ( \Exception $e ) {
			$this->markTestSkipped( "DB handler 'file_locker' could not be loaded: ".$e->getMessage() );
		}

		$this->loginAsSecurityAdmin();
		$this->enablePremiumCapabilities( [
			'scan_file_areas',
			'scan_pluginsthemes_local',
			'scan_vulnerabilities',
			'scan_file_locker',
		] );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'enable_core_file_integrity_scan',
			'enable_wpvuln_scan',
			'enabled_scan_apc',
			'file_scan_areas',
			'file_locker',
			'frequency_alert',
			'frequency_info',
			'block_send_email_address',
		] );

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'enable_wpvuln_scan', 'Y' )
			->optSet( 'enabled_scan_apc', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes' ] )
			->optSet( 'file_locker', [ 'wpconfig' ] )
			->optSet( 'frequency_alert', 'daily' )
			->optSet( 'frequency_info', 'disabled' )
			->optSet( 'block_send_email_address', 'security-alerts@example.test' )
			->store();
		self::con()->comps->asset_coordinator->deleteState();
		\wp_clear_scheduled_hook( self::con()->prefix( 'asset_coordinator' ) );
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->startLocalEmailCapture();
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			self::con()->comps->file_locker->clearLocks();
			self::con()->comps->asset_coordinator->deleteState();
			\wp_clear_scheduled_hook( self::con()->prefix( 'asset_coordinator' ) );
		}
		$this->stopLocalEmailCapture();
		parent::tear_down();
	}

	public function test_auto_alert_report_creates_record_email_events_and_marks_digest_targets() :void {
		$this->captureShieldEvents();
		$tracked = $this->seedPluginVulnerability();
		$this->resetScanResultCountMemoization();

		( new AutoReportCoordinator() )->run();

		$this->assertSame( 1, $this->countAlertReports() );
		$report = $this->latestAlertReport();
		$this->assertSame( Constants::REPORT_TYPE_ALERT, $report->type );
		$this->assertSame( 'daily', $report->interval_length );
		$this->assertTrue( (bool)$report->protected );
		$this->assertGreaterThan( 0, (int)$report->interval_start_at );
		$this->assertGreaterThan( (int)$report->interval_start_at, (int)$report->interval_end_at );

		$generatedEvents = $this->getCapturedEventsByKey( 'report_generated_alert' );
		$this->assertCount( 1, $generatedEvents );
		$this->assertSame( 'daily', $generatedEvents[ 0 ][ 'meta' ][ 'audit_params' ][ 'interval' ] ?? null );

		$sentEvents = $this->getCapturedEventsByKey( 'report_sent' );
		$this->assertCount( 1, $sentEvents );
		$this->assertSame( 'email', $sentEvents[ 0 ][ 'meta' ][ 'audit_params' ][ 'medium' ] ?? null );

		$this->assertCount( 1, $this->capturedMails() );
		$mail = $this->lastCapturedMail();
		$this->assertContains( 'security-alerts@example.test', (array)( $mail[ 'to' ] ?? [] ) );
		$this->assertSame( 'text/html', $mail[ 'content_ty' ] ?? null );
		$this->assertNotSame( '', (string)( $mail[ 'subject' ] ?? '' ) );

		$this->assertGreaterThan(
			0,
			(int)self::con()->db_con->scan_result_items->getQuerySelector()
				->byId( (int)$tracked[ 'result_item_id' ] )->notified_at
		);
	}

	public function test_auto_alert_report_suppresses_duplicate_report_in_same_completed_interval() :void {
		$this->captureShieldEvents();
		$this->seedPluginVulnerability( 'duplicate-window-first' );
		$this->resetScanResultCountMemoization();

		( new AutoReportCoordinator() )->run();

		$this->assertSame( 1, $this->countAlertReports() );
		$this->assertCount( 1, $this->capturedMails() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'report_generated_alert' ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'report_sent' ) );

		$this->seedPluginVulnerability( 'duplicate-window-second' );
		$this->resetScanResultCountMemoization();

		( new AutoReportCoordinator() )->run();

		$this->assertSame( 1, $this->countAlertReports() );
		$this->assertCount( 1, $this->capturedMails() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'report_generated_alert' ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'report_sent' ) );
	}

	public function test_auto_alert_report_skips_empty_or_already_notified_alert_digest() :void {
		$this->captureShieldEvents();
		$tracked = $this->seedPluginVulnerability( 'already-notified' );
		self::con()->db_con->scan_result_items->getQueryUpdater()->updateById(
			(int)$tracked[ 'result_item_id' ],
			[ 'notified_at' => Services::Request()->ts() - 60 ]
		);
		$this->resetScanResultCountMemoization();

		( new AutoReportCoordinator() )->run();

		$this->assertSame( 0, $this->countAlertReports() );
		$this->assertSame( [], $this->capturedMails() );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'report_generated_alert' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'report_sent' ) );
	}

	/**
	 * @dataProvider provideActiveScanStatuses
	 */
	public function test_active_scan_blocks_all_automatic_reports_until_terminal_reentry( string $status ) :void {
		self::con()->opts->optSet( 'frequency_info', 'daily' )->store();
		$this->captureShieldEvents();
		$tracked = $this->seedPluginVulnerability( 'active-'.$status );
		$activeScanId = $this->insertActiveScan( 'afs', $status );
		$remainingScanId = $this->insertActiveScan( 'apc', ScanStatus::QUEUED );
		$this->resetScanResultCountMemoization();

		( new AutoReportCoordinator() )->run();

		$this->assertAutomaticReportsBlocked( (int)$tracked[ 'result_item_id' ] );

		self::con()->db_con->scans->getQueryUpdater()->updateById( $activeScanId, [
			'status'      => ScanStatus::COMPLETED,
			'finished_at' => Services::Request()->ts(),
		] );
		( new AutoReportCoordinator() )->run();
		$this->assertAutomaticReportsBlocked( (int)$tracked[ 'result_item_id' ] );

		self::con()->db_con->scan_result_items->getQueryUpdater()->updateById(
			(int)$tracked[ 'result_item_id' ],
			[
				'resolved_at'       => Services::Request()->ts(),
				'resolution_reason' => 'clean_rescan',
			]
		);
		$finalTracked = $this->seedPluginVulnerability( 'final-'.$status );
		$this->resetScanResultCountMemoization();

		self::con()->db_con->scans->getQueryUpdater()->updateById( $remainingScanId, [
			'status'      => ScanStatus::COMPLETED,
			'finished_at' => Services::Request()->ts(),
		] );
		CompiledReportAssetFixture::ensureReady( self::con()->getRootDir() );
		( new AutoReportCoordinator() )->run();

		$this->assertSame( 1, $this->countAutomaticReports( Constants::REPORT_TYPE_ALERT ) );
		$this->assertSame( 1, $this->countAutomaticReports( Constants::REPORT_TYPE_INFO ) );
		$this->assertCount( 2, $this->capturedMails() );
		$this->assertSame(
			0,
			(int)self::con()->db_con->scan_result_items->getQuerySelector()
				->byId( (int)$tracked[ 'result_item_id' ] )->notified_at
		);
		$this->assertGreaterThan(
			0,
			(int)self::con()->db_con->scan_result_items->getQuerySelector()
				->byId( (int)$finalTracked[ 'result_item_id' ] )->notified_at
		);
	}

	public function provideActiveScanStatuses() :array {
		return [
			'queued'   => [ ScanStatus::QUEUED ],
			'building' => [ ScanStatus::BUILDING ],
			'built'    => [ ScanStatus::BUILT ],
			'running'  => [ ScanStatus::RUNNING ],
		];
	}

	public function test_retryable_asset_work_blocks_automatic_reports_until_queue_is_cleared() :void {
		self::con()->opts->optSet( 'frequency_info', 'daily' )->store();
		$this->captureShieldEvents();
		$tracked = $this->seedPluginVulnerability( 'retryable-asset' );
		$this->assertTrue( self::con()->comps->asset_coordinator->enqueueAsset(
			'plugin',
			self::con()->base_file,
			60
		) );
		$this->resetScanResultCountMemoization();

		( new AutoReportCoordinator() )->run();

		$this->assertAutomaticReportsBlocked( (int)$tracked[ 'result_item_id' ] );

		self::con()->comps->asset_coordinator->deleteState();
		CompiledReportAssetFixture::ensureReady( self::con()->getRootDir() );
		( new AutoReportCoordinator() )->run();

		$this->assertSame( 1, $this->countAutomaticReports( Constants::REPORT_TYPE_ALERT ) );
		$this->assertSame( 1, $this->countAutomaticReports( Constants::REPORT_TYPE_INFO ) );
		$this->assertCount( 2, $this->capturedMails() );
	}

	public function test_malformed_coordinator_state_fails_closed_until_state_is_removed() :void {
		self::con()->opts->optSet( 'frequency_info', 'daily' )->store();
		$this->captureShieldEvents();
		$tracked = $this->seedPluginVulnerability( 'malformed-coordinator' );
		$this->persistCoordinatorState( 'malformed-state' );
		$this->resetScanResultCountMemoization();

		( new AutoReportCoordinator() )->run();

		$this->assertAutomaticReportsBlocked( (int)$tracked[ 'result_item_id' ] );

		self::con()->comps->asset_coordinator->deleteState();
		CompiledReportAssetFixture::ensureReady( self::con()->getRootDir() );
		( new AutoReportCoordinator() )->run();

		$this->assertSame( 1, $this->countAutomaticReports( Constants::REPORT_TYPE_ALERT ) );
		$this->assertSame( 1, $this->countAutomaticReports( Constants::REPORT_TYPE_INFO ) );
		$this->assertCount( 2, $this->capturedMails() );
	}

	public function test_coordinator_read_failure_fails_closed_without_report_side_effects() :void {
		global $wpdb;

		self::con()->opts->optSet( 'frequency_info', 'daily' )->store();
		$this->captureShieldEvents();
		$tracked = $this->seedPluginVulnerability( 'coordinator-read-failure' );
		$property = \is_multisite() ? 'sitemeta' : 'options';
		$originalTable = $wpdb->{$property};
		$wpdb->{$property} = '';
		try {
			( new AutoReportCoordinator() )->run();
		}
		finally {
			$wpdb->{$property} = $originalTable;
		}

		$this->assertAutomaticReportsBlocked( (int)$tracked[ 'result_item_id' ] );
	}

	public function test_persist_alert_notifications_updates_only_digest_targets() :void {
		$pluginSlug = self::con()->base_file;

		$afsScanId = TestDataFactory::insertCompletedScan( 'afs' );
		$afsNew = TestDataFactory::insertAfsFileScanResultTracked( $afsScanId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		$afsOutstanding = TestDataFactory::insertAfsFileScanResultTracked( $afsScanId, 'wp-content/plugins/'.\dirname( $pluginSlug ).'/legacy.php', [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		self::con()->db_con->scan_result_items->getQueryUpdater()->updateById(
			(int)$afsOutstanding[ 'result_item_id' ],
			[ 'notified_at' => Services::Request()->ts() - 60 ]
		);

		$wpvScanId = TestDataFactory::insertCompletedScan( 'wpv' );
		$wpvNew = TestDataFactory::insertScanResultItemTracked( $wpvScanId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );

		$apcScanId = TestDataFactory::insertCompletedScan( 'apc' );
		$apcNew = TestDataFactory::insertScanResultItemTracked( $apcScanId, [
			'item_id'      => $pluginSlug,
			'is_abandoned' => 1,
		] );

		$fileLockId = TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', Services::Request()->ts() );

		self::con()->comps->file_locker->clearLocks();
		$this->resetScanResultCountMemoization();

		$generator = new ReportGenerator();
		$report = $this->buildAlertReport();
		$report->record = $generator->buildAndStore( $report );

		$expectedTargetIds = [
			(int)$afsNew[ 'result_item_id' ],
			(int)$wpvNew[ 'result_item_id' ],
			(int)$apcNew[ 'result_item_id' ],
		];
		\sort( $expectedTargetIds );

		$actualTargetIds = $report->alert_digest[ 'notification_target_ids' ];
		\sort( $actualTargetIds );

		$this->assertSame( $expectedTargetIds, $actualTargetIds );
		$this->assertTrue( $generator->persistAlertNotifications( $report ) );

		foreach ( $expectedTargetIds as $id ) {
			$this->assertGreaterThan(
				0,
				(int)self::con()->db_con->scan_result_items->getQuerySelector()->byId( $id )->notified_at
			);
		}

		$this->assertGreaterThan(
			0,
			(int)self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$afsOutstanding[ 'result_item_id' ] )->notified_at
		);
		$this->assertSame(
			0,
			(int)self::con()->db_con->file_locker->getQuerySelector()->byId( $fileLockId )->notified_at
		);
	}

	public function test_rebuilding_alert_digest_after_persistence_leaves_only_outstanding_items() :void {
		$pluginSlug = self::con()->base_file;

		$afsScanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResultTracked( $afsScanId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );

		$wpvScanId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItemTracked( $wpvScanId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );

		$this->resetScanResultCountMemoization();

		$generator = new ReportGenerator();
		$report = $this->buildAlertReport();
		$report->record = $generator->buildAndStore( $report );
		$this->assertTrue( $generator->persistAlertNotifications( $report ) );

		$this->resetScanResultCountMemoization();

		$rebuild = $this->buildAlertReport();
		$rebuild->areas_data = [
			Constants::REPORT_AREA_SCANS => ( new BuildForScans( $rebuild ) )->build(),
		];
		$rebuild->alert_digest = ( new BuildAlertDigestContract() )->build( $rebuild );

		$this->assertFalse( $rebuild->alert_digest[ 'has_new_items' ] );
		$this->assertSame( [], $rebuild->alert_digest[ 'notification_target_ids' ] );
		$this->assertGreaterThan( 0, $rebuild->alert_digest[ 'summary' ][ 'outstanding_total' ] );
		$this->assertSame( 0, $rebuild->alert_digest[ 'summary' ][ 'new_total' ] );
	}

	/**
	 * @dataProvider provideAfsAssetScopes
	 */
	public function test_rediscovered_notified_afs_asset_finding_keeps_identity_and_is_not_new(
		string $assetType
	) :void {
		$scenario = $this->afsAssetScenario( $assetType );
		$notifiedAt = Services::Request()->ts() - 60;

		$initialScanId = TestDataFactory::insertCompletedScan( 'afs' );
		$tracked = $this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'path_full' ] );
		self::con()->db_con->scan_result_items->getQueryUpdater()->updateById(
			(int)$tracked[ 'result_item_id' ],
			[ 'notified_at' => $notifiedAt ]
		);
		$stale = $this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'stale_path_full' ] );

		$replacementScanId = $this->insertAfsScan(
			$scenario[ 'scope_type' ],
			$scenario[ 'scope_key' ],
			[ $this->afsIntegrityCoverageFamily( $assetType ) ]
		);
		$this->storeAfsObservation( $replacementScanId, $scenario );
		$this->assertTrue( ( new SetScanCompleted() )->run( $replacementScanId ) );

		$resultItem = self::con()->db_con->scan_result_items->getQuerySelector()
			->byId( (int)$tracked[ 'result_item_id' ] );
		$this->assertNotEmpty( $resultItem );
		$this->assertSame( $notifiedAt, (int)$resultItem->notified_at );
		$this->assertSame( 0, (int)$resultItem->resolved_at );
		$this->assertSame( 1, $this->countAfsResultItemsForPath( $scenario[ 'path_full' ] ) );
		$this->assertSame( 1, $this->countAfsScanResultLinks( $replacementScanId, (int)$tracked[ 'result_item_id' ] ) );
		$staleItem = self::con()->db_con->scan_result_items->getQuerySelector()
			->byId( (int)$stale[ 'result_item_id' ] );
		$this->assertNotEmpty( $staleItem );
		$this->assertGreaterThan( 0, (int)$staleItem->resolved_at );
		$this->assertSame( 'asset_replaced', (string)$staleItem->resolution_reason );

		$report = $this->buildAlertReport();
		$report->areas_data = [
			Constants::REPORT_AREA_SCANS => ( new BuildForScans( $report ) )->build(),
		];
		$report->alert_digest = ( new BuildAlertDigestContract() )->build( $report );

		$this->assertFalse( $report->alert_digest[ 'has_new_items' ] );
		$this->assertSame( [], $report->alert_digest[ 'notification_target_ids' ] );
		$this->assertGreaterThan( 0, $report->alert_digest[ 'summary' ][ 'outstanding_total' ] );
		$this->assertSame( 0, $report->alert_digest[ 'summary' ][ 'new_total' ] );
	}

	public function provideAfsAssetScopes() :array {
		return [
			'plugin' => [ 'plugin' ],
			'theme'  => [ 'theme' ],
			'core'   => [ 'core' ],
		];
	}

	private function afsIntegrityCoverageFamily( string $assetType ) :string {
		return [
			'plugin' => ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
			'theme'  => ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY,
			'core'   => ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY,
		][ $assetType ];
	}

	private function buildAlertReport() :ReportVO {
		$carbon = Services::Request()->carbon( true )->subDay();

		$report = new ReportVO();
		$report->type = Constants::REPORT_TYPE_ALERT;
		$report->interval = 'daily';
		$report->title = 'Alert :: Daily :: Auto-Generated';
		$report->start_at = ( clone $carbon )->startOfDay()->timestamp;
		$report->end_at = ( clone $carbon )->endOfDay()->timestamp;
		$report->areas = [
			Constants::REPORT_AREA_SCANS => [ 'scan_results' ],
		];

		return $report;
	}

	private function pluginMainPathFragment( string $pluginSlug ) :string {
		return \ltrim( \wp_normalize_path( $pluginSlug ), '/' );
	}

	/**
	 * @return array{scan_result_id:int,result_item_id:int,meta_ids:list<int>}
	 */
	private function seedPluginVulnerability( string $suffix = 'primary' ) :array {
		$scanId = TestDataFactory::insertCompletedScan( 'wpv' );
		return TestDataFactory::insertScanResultItemTracked( $scanId, [
			'item_id'       => self::con()->base_file,
			'is_vulnerable' => 1,
			'fixture_key'   => $suffix,
		] );
	}

	private function insertActiveScan( string $scanSlug, string $status ) :int {
		$now = Services::Request()->ts();
		$dbh = self::con()->db_con->scans;
		$record = $dbh->getRecord();
		$record->scan = $scanSlug;
		$record->status = $status;
		$record->scope_type = 'full';
		$record->scope_key = '';
		$record->run_trigger = 'manual';
		$record->started_at = $now;
		$record->last_process_at = $now;
		$record->ready_at = $now;
		$record->finished_at = 0;
		$dbh->getQueryInserter()->insert( $record );
		return (int)Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' );
	}

	/**
	 * @param mixed $state
	 */
	private function persistCoordinatorState( $state ) :void {
		$key = self::con()->prefix( 'asset_coordinator_state' );
		if ( \is_multisite() ) {
			\update_site_option( $key, $state );
		}
		else {
			\update_option( $key, $state, false );
		}
	}

	private function assertAutomaticReportsBlocked( int $resultItemId ) :void {
		$this->assertSame( 0, $this->countAutomaticReports( Constants::REPORT_TYPE_ALERT ) );
		$this->assertSame( 0, $this->countAutomaticReports( Constants::REPORT_TYPE_INFO ) );
		$this->assertSame( [], $this->capturedMails() );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'report_generated_alert' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'report_generated' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'report_sent' ) );
		$this->assertSame(
			0,
			(int)self::con()->db_con->scan_result_items->getQuerySelector()->byId( $resultItemId )->notified_at
		);
	}

	private function countAutomaticReports( string $type ) :int {
		return self::con()->db_con->reports->getQuerySelector()
			->filterByType( $type )
			->filterByInterval( 'daily' )
			->count();
	}

	private function countAlertReports() :int {
		return $this->countAutomaticReports( Constants::REPORT_TYPE_ALERT );
	}

	private function latestAlertReport() {
		return self::con()->db_con->reports->getQuerySelector()
			->filterByType( Constants::REPORT_TYPE_ALERT )
			->filterByInterval( 'daily' )
			->setOrderBy( 'id' )
			->first();
	}
}
