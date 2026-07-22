<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	Constants,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\MalwareStatus;
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

	public function test_pending_malware_blocks_only_the_pending_malware_notification() :void {
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
		$this->assertSame( 1, $entries[ 'afs_plugin' ][ 'count' ] );
		$this->assertSame( [ $mixedID ], $entries[ 'afs_plugin' ][ 'notification_target_ids' ] );
		$this->assertNotContains( $malwareOnlyID, $entries[ 'afs_plugin' ][ 'notification_target_ids' ] );
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
