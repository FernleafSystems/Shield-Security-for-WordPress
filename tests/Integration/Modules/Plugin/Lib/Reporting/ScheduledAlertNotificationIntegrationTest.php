<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	BuildAlertDigestContract,
	Constants,
	ReportGenerator,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class ScheduledAlertNotificationIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'reports' );
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->requireDb( 'file_locker' );

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
		] );

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'enable_wpvuln_scan', 'Y' )
			->optSet( 'enabled_scan_apc', 'Y' )
			->optSet( 'file_scan_areas', [ 'plugins' ] )
			->optSet( 'file_locker', [ 'wpconfig' ] )
			->store();
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			self::con()->comps->file_locker->clearLocks();
		}
		parent::tear_down();
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
}
