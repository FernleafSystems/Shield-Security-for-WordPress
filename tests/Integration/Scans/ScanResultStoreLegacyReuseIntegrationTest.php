<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler as ResultItemsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Store;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ScanResultStoreLegacyReuseIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->truncateShieldTables();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
	}

	public function testStoreReusesUnresolvedBlankLegacyResultItemInRealSchema() :void {
		$scanID = TestDataFactory::insertCompletedScan( 'afs' );
		$pathFragment = 'wp-admin/admin.php';
		$notifiedAt = 1700000123;
		$legacyResultItemID = $this->insertLegacyBlankResultItem( $pathFragment, $notifiedAt );

		( new Store() )->store( $this->newQueueItem( $scanID ), [
			[
				'path_fragment'   => $pathFragment,
				'is_in_core'      => 1,
				'is_checksumfail' => 1,
			],
		] );

		$resultItem = self::con()->db_con->scan_result_items->getQuerySelector()->byId( $legacyResultItemID );
		$this->assertNotEmpty( $resultItem );
		$this->assertSame( 'afs', (string)$resultItem->scan );
		$this->assertSame( 'core', (string)$resultItem->asset_type );
		$this->assertSame( 'core', (string)$resultItem->asset_key );
		$this->assertSame( $notifiedAt, (int)$resultItem->notified_at );
		$this->assertSame( 0, (int)$resultItem->resolved_at );

		$this->assertSame( 1, $this->countResultItemsForPath( $pathFragment ) );
		$this->assertSame( 1, $this->countScanResultLinks( $scanID, $legacyResultItemID ) );
		$this->assertSame(
			1,
			( new RetrieveCount() )
				->setScanController( $this->newAfsScanController() )
				->count( RetrieveCount::CONTEXT_RESULTS_DISPLAY )
		);
	}

	private function insertLegacyBlankResultItem( string $pathFragment, int $notifiedAt ) :int {
		$dbh = self::con()->db_con->scan_result_items;
		$record = $dbh->getRecord();
		$record->scan = '';
		$record->item_type = ResultItemsHandler::ITEM_TYPE_FILE;
		$record->item_id = $pathFragment;
		$record->asset_type = '';
		$record->asset_key = '';
		$record->ignored_at = 0;
		$record->notified_at = $notifiedAt;
		$record->auto_filtered_at = 0;
		$record->attempt_repair_at = 0;
		$record->last_seen_at = $notifiedAt - 60;
		$record->resolved_at = 0;
		$record->resolution_reason = '';
		$record->item_repaired_at = 0;
		$record->item_deleted_at = 0;

		$this->assertTrue( $dbh->getQueryInserter()->insert( $record ) );
		return (int)$GLOBALS[ 'wpdb' ]->insert_id;
	}

	private function newQueueItem( int $scanID ) :QueueItemVO {
		$queueItem = new QueueItemVO();
		$queueItem->scan_id = $scanID;
		$queueItem->qitem_id = 0;
		$queueItem->scan = 'afs';
		return $queueItem;
	}

	private function newAfsScanController() :object {
		return new class {
			public function getSlug() :string {
				return 'afs';
			}
		};
	}

	private function countResultItemsForPath( string $pathFragment ) :int {
		global $wpdb;
		return (int)$wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*)
				FROM `".self::con()->db_con->scan_result_items->getTable()."`
				WHERE `item_type`=%s
				  AND `item_id`=%s",
			ResultItemsHandler::ITEM_TYPE_FILE,
			$pathFragment
		) );
	}

	private function countScanResultLinks( int $scanID, int $resultItemID ) :int {
		global $wpdb;
		return (int)$wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*)
				FROM `".self::con()->db_con->scan_results->getTable()."`
				WHERE `scan_ref`=%d
				  AND `resultitem_ref`=%d",
			$scanID,
			$resultItemID
		) );
	}
}
