<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	BuildAlertDigestContract,
	Constants,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard\Scan\Support\AfsAssetChangeIntegrationSupport;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class AssetChangeCompletionIntegrationTest extends ShieldIntegrationTestCase {

	use AfsAssetChangeIntegrationSupport;

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_items' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->loginAsSecurityAdmin();
		$this->enablePremiumCapabilities( [
			'scan_file_areas',
			'scan_pluginsthemes_local',
		] );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'enable_core_file_integrity_scan',
			'file_scan_areas',
		] );

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes' ] )
			->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
		}
		parent::tear_down();
	}

	public function test_asset_change_completion_invalidates_warm_scan_result_count_cache() :void {
		$scenario = $this->afsAssetScenario( 'plugin' );
		$initialScanId = TestDataFactory::insertCompletedScan( 'afs' );
		$this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'path_full' ] );
		$stale = $this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'stale_path_full' ] );

		$this->resetScanResultCountMemoization();
		$this->assertSame( 2, self::con()->comps->scans->getScanResultsCount()->countPluginFiles() );

		$replacementScanId = $this->insertAfsScan( $scenario[ 'scope_type' ], $scenario[ 'scope_key' ] );
		$this->storeAfsObservation( $replacementScanId, $scenario );
		$this->assertTrue( ( new SetScanCompleted() )->run( $replacementScanId ) );
		$staleItem = self::con()->db_con->scan_result_items->getQuerySelector()
			->byId( (int)$stale[ 'result_item_id' ] );

		$this->assertSame( 'asset_replaced', (string)$staleItem->resolution_reason );
		$this->assertSame( 1, self::con()->comps->scans->getScanResultsCount()->countPluginFiles() );
	}

	public function test_asset_change_completion_invalidates_warm_afs_display_results_cache() :void {
		$scenario = $this->afsAssetScenario( 'plugin' );
		$initialScanId = TestDataFactory::insertCompletedScan( 'afs' );
		$this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'path_full' ] );
		$this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'stale_path_full' ] );
		$stalePathFragment = TestDataFactory::afsFileItemIdFromPath( $scenario[ 'stale_path_full' ] );

		$this->resetScanResultCountMemoization();
		$this->assertSame( 2, self::con()->comps->scans->AFS()->getResultsForDisplay()->countItems() );

		$replacementScanId = $this->insertAfsScan( $scenario[ 'scope_type' ], $scenario[ 'scope_key' ] );
		$this->storeAfsObservation( $replacementScanId, $scenario );
		$this->assertTrue( ( new SetScanCompleted() )->run( $replacementScanId ) );

		$report = $this->buildAlertReport();
		$report->areas_data = [
			Constants::REPORT_AREA_SCANS => ( new BuildForScans( $report ) )->build(),
		];
		$report->alert_digest = ( new BuildAlertDigestContract() )->build( $report );
		$scanRow = $this->scanResultsRow( $report, $scenario[ 'digest_slug' ] );

		$this->assertSame( 1, $scanRow[ 'count' ] );
		$this->assertNotContains( $stalePathFragment, \array_column( $scanRow[ 'items' ], 'label' ) );
		foreach ( $report->alert_digest[ 'rows' ] as $digestRow ) {
			$this->assertNotContains( $stalePathFragment, \array_column( $digestRow[ 'new_items' ], 'label' ) );
			$this->assertNotContains( $stalePathFragment, \array_column( $digestRow[ 'outstanding_items' ], 'label' ) );
		}
	}

	public function test_core_asset_change_completion_resolves_only_modified_or_missing_core_rows() :void {
		$core = $this->afsAssetScenario( 'core' );
		$initialScanId = TestDataFactory::insertCompletedScan( 'afs' );
		$checksum = $this->seedAfsFinding( $initialScanId, $core, \path_join( ABSPATH, WPINC.'/asset-checksum.php' ), [
			'is_in_core'      => 1,
			'is_checksumfail' => 1,
		] );
		$missing = $this->seedAfsFinding( $initialScanId, $core, \path_join( ABSPATH, WPINC.'/asset-missing.php' ), [
			'is_in_core'  => 1,
			'is_missing'  => 1,
		] );
		$unrecognised = $this->seedAfsFinding( $initialScanId, $core, \path_join( ABSPATH, WPINC.'/asset-unrecognised.php' ), [
			'is_in_core'       => 1,
			'is_unrecognised'  => 1,
		] );
		$malware = $this->seedAfsFinding( $initialScanId, $core, \path_join( ABSPATH, WPINC.'/asset-malware.php' ), [
			'is_in_core' => 1,
			'is_mal'     => 1,
		] );

		$replacementScanId = $this->insertAfsScan( 'core', 'core' );
		$this->assertTrue( ( new SetScanCompleted() )->run( $replacementScanId ) );

		foreach ( [ $checksum, $missing ] as $resolved ) {
			$item = self::con()->db_con->scan_result_items->getQuerySelector()
				->byId( (int)$resolved[ 'result_item_id' ] );
			$this->assertGreaterThan( 0, (int)$item->resolved_at );
			$this->assertSame( 'asset_replaced', (string)$item->resolution_reason );
		}
		foreach ( [ $unrecognised, $malware ] as $active ) {
			$item = self::con()->db_con->scan_result_items->getQuerySelector()
				->byId( (int)$active[ 'result_item_id' ] );
			$this->assertSame( 0, (int)$item->resolved_at );
			$this->assertSame( '', (string)$item->resolution_reason );
		}
	}

	/**
	 * @dataProvider provideCleanRescanCompletionScopes
	 */
	public function test_scan_completion_uses_clean_rescan_for_non_asset_change_runs(
		string $scopeType,
		string $scopeKey,
		string $runTrigger
	) :void {
		$scenario = $this->afsAssetScenario( 'plugin' );
		$initialScanId = TestDataFactory::insertCompletedScan( 'afs' );
		$stale = $this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'stale_path_full' ] );
		$scanScopeKey = $scopeKey === '{plugin}' ? $scenario[ 'scope_key' ] : $scopeKey;

		$scanId = $this->insertAfsScan( $scopeType, $scanScopeKey, $runTrigger );
		$this->assertTrue( ( new SetScanCompleted() )->run( $scanId ) );
		$item = self::con()->db_con->scan_result_items->getQuerySelector()
			->byId( (int)$stale[ 'result_item_id' ] );

		$this->assertGreaterThan( 0, (int)$item->resolved_at );
		$this->assertSame( 'clean_rescan', (string)$item->resolution_reason );
	}

	public function provideCleanRescanCompletionScopes() :array {
		return [
			'full manual'    => [ 'full', '', 'manual' ],
			'scoped manual'  => [ 'plugin', '{plugin}', 'manual' ],
		];
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

	private function scanResultsRow( ReportVO $report, string $slug ) :array {
		foreach ( $report->areas_data[ Constants::REPORT_AREA_SCANS ][ 'scan_results' ] as $row ) {
			if ( $row[ 'slug' ] === $slug ) {
				return $row;
			}
		}

		$this->fail( \sprintf( 'Scan result row missing for slug: %s', $slug ) );
		return [];
	}
}
