<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	AutoReportCoordinator,
	BuildAlertDigestContract,
	Constants,
	ReportGenerator,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class ScheduledAlertNotificationIntegrationTest extends ShieldIntegrationTestCase {

	use LocalEmailCapture;

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'reports' );
		$this->requireDb( 'scans' );
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
			->optSet( 'file_scan_areas', [ 'plugins' ] )
			->optSet( 'file_locker', [ 'wpconfig' ] )
			->optSet( 'frequency_alert', 'daily' )
			->optSet( 'frequency_info', 'disabled' )
			->optSet( 'block_send_email_address', 'security-alerts@example.test' )
			->store();
		$this->startLocalEmailCapture();
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			self::con()->comps->file_locker->clearLocks();
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
		$this->assertSame( 'Alert', $generatedEvents[ 0 ][ 'meta' ][ 'audit_params' ][ 'type' ] ?? null );
		$this->assertSame( 'daily', $generatedEvents[ 0 ][ 'meta' ][ 'audit_params' ][ 'interval' ] ?? null );

		$sentEvents = $this->getCapturedEventsByKey( 'report_sent' );
		$this->assertCount( 1, $sentEvents );
		$this->assertSame( 'Alert', $sentEvents[ 0 ][ 'meta' ][ 'audit_params' ][ 'type' ] ?? null );
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

		$actualTargetIds = (array)( $report->alert_digest[ 'notification_target_ids' ] ?? [] );
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

	private function countAlertReports() :int {
		return self::con()->db_con->reports->getQuerySelector()
			->filterByType( Constants::REPORT_TYPE_ALERT )
			->filterByInterval( 'daily' )
			->count();
	}

	private function latestAlertReport() {
		return self::con()->db_con->reports->getQuerySelector()
			->filterByType( Constants::REPORT_TYPE_ALERT )
			->filterByInterval( 'daily' )
			->setOrderBy( 'id' )
			->first();
	}
}
