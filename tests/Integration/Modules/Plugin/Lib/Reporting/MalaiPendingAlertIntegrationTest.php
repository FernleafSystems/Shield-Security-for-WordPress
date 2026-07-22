<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	BuildAlertDigestContract,
	Constants,
	ReportGenerator,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\{
	MalwareStatus,
	RetrieveMalwareMalaiStatus
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type ScanReportRow from BuildForScans
 */
class MalaiPendingAlertIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		foreach ( [ 'malware', 'scans', 'scan_results', 'scan_result_items', 'scan_result_item_meta' ] as $dbKey ) {
			$this->requireDb( $dbKey );
		}
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'enable_core_file_integrity_scan',
			'file_scan_areas',
		] );
		$this->enableCapabilities( true );
		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'plugins', 'malware_php' ] )
			->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_fresh_pending_malware_is_withheld_from_all_alert_rows_and_notification_targets() :void {
		$scanID = TestDataFactory::insertCompletedScan( 'afs' );
		$malwareOnlyID = $this->seedFinding( $scanID, 'only.php', MalwareStatus::STATUS_UNKNOWN );
		$mixedID = $this->seedFinding(
			$scanID,
			'mixed.php',
			MalwareStatus::STATUS_UNCLASSIFIED,
			[ 'is_checksumfail' => 1 ]
		);

		$entries = $this->buildEntries();
		$this->assertSame( 0, $entries[ 'afs_malware' ][ 'count' ] );
		$this->assertSame( [], $entries[ 'afs_malware' ][ 'notification_target_ids' ] );
		$this->assertSame( 0, $entries[ 'afs_plugin' ][ 'count' ] );
		$this->assertSame( [], $entries[ 'afs_plugin' ][ 'notification_target_ids' ] );
		$this->assertNotContains( $malwareOnlyID, $entries[ 'afs_plugin' ][ 'notification_target_ids' ] );
		$this->assertNotContains( $mixedID, $entries[ 'afs_plugin' ][ 'notification_target_ids' ] );

		$report = $this->buildAlertReport( $entries );
		$this->assertSame( [], $report->alert_digest[ 'notification_target_ids' ] );
		$this->assertFalse( ( new ReportGenerator() )->persistAlertNotifications( $report ) );
		$this->assertSame( 0, $this->notifiedAt( $malwareOnlyID ) );
		$this->assertSame( 0, $this->notifiedAt( $mixedID ) );
	}

	public function test_expired_pending_results_are_released_and_notified_once() :void {
		$scanID = TestDataFactory::insertCompletedScan( 'afs' );
		$malwareOnlyID = $this->seedFinding( $scanID, 'expired-only.php', MalwareStatus::STATUS_UNKNOWN );
		$mixedID = $this->seedFinding(
			$scanID,
			'expired-mixed.php',
			MalwareStatus::STATUS_UNCLASSIFIED,
			[ 'is_checksumfail' => 1 ]
		);
		$createdAt = Services::Request()->ts() - \HOUR_IN_SECONDS;
		$this->setCreatedAt( $malwareOnlyID, $createdAt );
		$this->setCreatedAt( $mixedID, $createdAt );

		$entries = $this->buildEntries();
		$this->assertSame( 2, $entries[ 'afs_malware' ][ 'count' ] );
		$this->assertEqualsCanonicalizing(
			[ $malwareOnlyID, $mixedID ],
			$entries[ 'afs_malware' ][ 'notification_target_ids' ]
		);
		$this->assertSame( 2, $entries[ 'afs_plugin' ][ 'count' ] );
		$this->assertEqualsCanonicalizing(
			[ $malwareOnlyID, $mixedID ],
			$entries[ 'afs_plugin' ][ 'notification_target_ids' ]
		);

		$report = $this->buildAlertReport( $entries );
		$this->assertEqualsCanonicalizing(
			[ $malwareOnlyID, $mixedID ],
			$report->alert_digest[ 'notification_target_ids' ]
		);
		$this->assertTrue( ( new ReportGenerator() )->persistAlertNotifications( $report ) );
		$this->assertGreaterThan( 0, $this->notifiedAt( $malwareOnlyID ) );
		$this->assertGreaterThan( 0, $this->notifiedAt( $mixedID ) );

		$entries = $this->buildEntries();
		$this->assertSame( [], $entries[ 'afs_malware' ][ 'notification_target_ids' ] );
		$this->assertSame( [], $entries[ 'afs_plugin' ][ 'notification_target_ids' ] );

		self::con()->db_con->malware->getQueryUpdater()->updateById( $this->malwareRecordID( $mixedID ), [
			'malai_status'         => MalwareStatus::STATUS_MALWARE,
			'last_malai_status_at' => Services::Request()->ts(),
		] );
		$entries = $this->buildEntries();
		$this->assertSame( [], $entries[ 'afs_malware' ][ 'notification_target_ids' ] );
		$this->assertSame( [], $entries[ 'afs_plugin' ][ 'notification_target_ids' ] );
	}

	public function test_missing_malware_record_respects_the_one_hour_grace() :void {
		$scanID = TestDataFactory::insertCompletedScan( 'afs' );
		$resultItemID = $this->seedFinding( $scanID, 'missing-record.php', MalwareStatus::STATUS_UNKNOWN );
		$this->assertTrue(
			self::con()->db_con->malware->getQueryDeleter()->deleteById( $this->malwareRecordID( $resultItemID ) )
		);

		$entries = $this->buildEntries();
		$this->assertSame( [], $entries[ 'afs_malware' ][ 'notification_target_ids' ] );

		$this->setCreatedAt( $resultItemID, Services::Request()->ts() - \HOUR_IN_SECONDS );
		$entries = $this->buildEntries();
		$this->assertSame( [ $resultItemID ], $entries[ 'afs_malware' ][ 'notification_target_ids' ] );
	}

	public function test_invalid_result_timestamps_fail_open() :void {
		$scanID = TestDataFactory::insertCompletedScan( 'afs' );
		$zeroID = $this->seedFinding( $scanID, 'zero-created.php', MalwareStatus::STATUS_UNKNOWN );
		$futureID = $this->seedFinding( $scanID, 'future-created.php', MalwareStatus::STATUS_UNKNOWN );
		$this->setCreatedAt( $zeroID, 0 );
		$this->setCreatedAt( $futureID, Services::Request()->ts() + \HOUR_IN_SECONDS );

		$entries = $this->buildEntries();
		$this->assertEqualsCanonicalizing(
			[ $zeroID, $futureID ],
			$entries[ 'afs_malware' ][ 'notification_target_ids' ]
		);
	}

	public function test_pending_to_malware_becomes_a_new_malware_alert() :void {
		$scanID = TestDataFactory::insertCompletedScan( 'afs' );
		$resultItemID = $this->seedFinding( $scanID, 'settled-malware.php', MalwareStatus::STATUS_UNKNOWN );
		$recordID = $this->malwareRecordID( $resultItemID );

		self::con()->db_con->malware->getQueryUpdater()->updateById( $recordID, [
			'malai_status' => MalwareStatus::STATUS_MALWARE,
		] );

		$entries = $this->buildEntries();
		$this->assertSame( [ $resultItemID ], $entries[ 'afs_malware' ][ 'notification_target_ids' ] );
		$this->assertSame( 0, $this->notifiedAt( $resultItemID ) );
	}

	public function test_pending_to_definitive_clean_alerts_only_the_remaining_finding() :void {
		$scanID = TestDataFactory::insertCompletedScan( 'afs' );
		$resultItemID = $this->seedFinding(
			$scanID,
			'settled-clean.php',
			MalwareStatus::STATUS_UNKNOWN,
			[ 'is_checksumfail' => 1 ]
		);
		$recordID = $this->malwareRecordID( $resultItemID );
		self::con()->db_con->malware->getQueryUpdater()->updateById( $recordID, [
			'malai_status'         => MalwareStatus::STATUS_CLEAN,
			'last_malai_status_at' => Services::Request()->ts(),
		] );

		$this->assertSame( 1, ( new RetrieveMalwareMalaiStatus() )->reconcileActiveResults() );
		$entries = $this->buildEntries();

		$this->assertSame( [], $entries[ 'afs_malware' ][ 'notification_target_ids' ] );
		$this->assertSame( [ $resultItemID ], $entries[ 'afs_plugin' ][ 'notification_target_ids' ] );
		$this->assertSame( 0, $this->notifiedAt( $resultItemID ) );
	}

	public function test_settled_positive_verdict_remains_notifiable() :void {
		$scanID = TestDataFactory::insertCompletedScan( 'afs' );
		$resultItemID = $this->seedFinding( $scanID, 'positive.php', MalwareStatus::STATUS_MALWARE );

		$entries = $this->buildEntries();
		$this->assertSame( 1, $entries[ 'afs_malware' ][ 'count' ] );
		$this->assertSame( [ $resultItemID ], $entries[ 'afs_malware' ][ 'notification_target_ids' ] );
	}

	public function test_pending_verdict_does_not_block_when_malai_is_unavailable() :void {
		$this->enableCapabilities( false );
		$scanID = TestDataFactory::insertCompletedScan( 'afs' );
		$resultItemID = $this->seedFinding( $scanID, 'local-only.php', MalwareStatus::STATUS_UNKNOWN );

		$entries = $this->buildEntries();
		$this->assertSame( 1, $entries[ 'afs_malware' ][ 'count' ] );
		$this->assertSame( [ $resultItemID ], $entries[ 'afs_malware' ][ 'notification_target_ids' ] );
	}

	private function enableCapabilities( bool $withMalai ) :void {
		$caps = [
			'scan_pluginsthemes_local',
			'scan_malware_local',
		];
		if ( $withMalai ) {
			$caps[] = 'scan_malware_malai';
		}
		$this->enablePremiumCapabilities( $caps );
	}

	private function seedFinding( int $scanID, string $file, string $status, array $extraMeta = [] ) :int {
		$path = 'wp-content/plugins/fixture/'.$file;
		$malwareRecordID = TestDataFactory::insertMalwareRecord( $path, $file, [
			'malai_status'         => $status,
			'last_malai_status_at' => Services::Request()->ts(),
		] );
		$tracked = TestDataFactory::insertAfsFileScanResultTracked( $scanID, $path, \array_merge( [
			'is_in_plugin'      => 1,
			'is_mal'            => 1,
			'malware_record_id' => $malwareRecordID,
			'ptg_slug'          => 'fixture/fixture.php',
		], $extraMeta ) );

		return $tracked[ 'result_item_id' ];
	}

	private function malwareRecordID( int $resultItemID ) :int {
		$record = self::con()->db_con->scan_result_item_meta->getQuerySelector()
			->filterByResultItemRef( $resultItemID )
			->filterByMetaKey( 'malware_record_id' )
			->first();
		$this->assertNotEmpty( $record );
		return (int)$record->meta_value;
	}

	private function notifiedAt( int $resultItemID ) :int {
		$record = self::con()->db_con->scan_result_items->getQuerySelector()->byId( $resultItemID );
		$this->assertNotEmpty( $record );
		return (int)$record->notified_at;
	}

	private function setCreatedAt( int $resultItemID, int $createdAt ) :void {
		$this->assertTrue( self::con()->db_con->scan_result_items->getQueryUpdater()->updateById(
			$resultItemID,
			[ 'created_at' => $createdAt ]
		) );
	}

	/**
	 * @phpstan-param array<string,ScanReportRow> $entries
	 */
	private function buildAlertReport( array $entries ) :ReportVO {
		$report = new ReportVO();
		$report->areas_data = [
			Constants::REPORT_AREA_SCANS => [
				'scan_results' => \array_values( $entries ),
			],
		];
		$report->alert_digest = ( new BuildAlertDigestContract() )->build( $report );
		return $report;
	}

	/**
	 * @phpstan-return array<string,ScanReportRow>
	 */
	private function buildEntries() :array {
		$report = new ReportVO();
		$report->type = Constants::REPORT_TYPE_ALERT;
		$report->areas = [
			Constants::REPORT_AREA_SCANS => [ 'scan_results' ],
		];
		$data = ( new BuildForScans( $report ) )->build();

		$entries = [];
		foreach ( $data[ 'scan_results' ] as $entry ) {
			$entries[ $entry[ 'slug' ] ] = $entry;
		}
		return $entries;
	}
}
