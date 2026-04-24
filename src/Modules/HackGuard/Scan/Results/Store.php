<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ResultItemMeta\Ops as ResultItemMetaDB,
	ResultItems\Ops as ResultItemsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Store {

	use PluginControllerConsumer;

	public function store( QueueItemVO $queueItem, array $results ) {
		if ( empty( $results ) ) {
			return;
		}

		$dbCon = self::con()->db_con;

		$dbhResItemMetas = $dbCon->scan_result_item_meta;

		$scanCon = self::con()->comps->scans->getScanCon( $queueItem->scan );
		$scanResults = \array_values( \array_reduce(
			\array_map(
				static fn( array $result ) => $scanCon->buildScanResult( $result ),
				$results
			),
			function ( array $carry, ResultItemsDB\Record $scanResult ) :array {
				$carry[ $this->resultKey( $scanResult ) ] = $scanResult;
				return $carry;
			},
			[]
		) );

		$existingResultRecords = $this->loadExistingResultItems( $queueItem->scan, $scanResults );
		$updatedResultIDs = [];
		$resultItemIDs = [];
		$metaRows = [];

		foreach ( $scanResults as $scanResult ) {
			$key = $this->resultKey( $scanResult );
			/** @var ?ResultItemsDB\Record $resultRecord */
			$resultRecord = $existingResultRecords[ $key ] ?? null;
			if ( $resultRecord === null ) {
				$dbCon->scan_result_items->getQueryInserter()->insert( $scanResult );
				$scanResult->id = $this->lastInsertID();
				$resultRecord = $scanResult;
				$existingResultRecords[ $key ] = $resultRecord;
			}
			else {
				$dbCon->scan_result_items->getQueryUpdater()->updateRecord( $resultRecord, [
					'scan'              => $scanResult->scan,
					'asset_type'        => $scanResult->asset_type,
					'asset_key'         => $scanResult->asset_key,
					'auto_filtered_at'  => $scanResult->auto_filtered_at,
					'last_seen_at'      => $scanResult->last_seen_at,
					'resolved_at'       => $scanResult->resolved_at,
					'resolution_reason' => $scanResult->resolution_reason,
				] );
				$updatedResultIDs[] = (int)$resultRecord->id;
			}

			foreach ( $scanResult->meta as $metaKey => $metaValue ) {
				$metaRows[] = [
					'ri_ref'     => $resultRecord->id,
					'meta_key'   => $metaKey,
					'meta_value' => \is_scalar( $metaValue ) ? $metaValue : \wp_json_encode( $metaValue ),
				];
			}

			$resultItemIDs[] = (int)$resultRecord->id;
		}

		$updatedResultIDs = \array_values( \array_unique( \array_filter( \array_map( '\intval', $updatedResultIDs ) ) ) );
		if ( !empty( $updatedResultIDs ) ) {
			/** @var ResultItemMetaDB\Delete $metaDeleter */
			$metaDeleter = $dbhResItemMetas->getQueryDeleter();
			$metaDeleter->filterByResultItems( $updatedResultIDs )->query();
		}

		foreach ( $metaRows as $metaRow ) {
			/** @var ResultItemMetaDB\Insert $metaInserter */
			$metaInserter = $dbhResItemMetas->getQueryInserter();
			$metaInserter->setInsertData( $metaRow )->query();
		}

		$resultItemIDs = \array_values( \array_unique( \array_filter( \array_map( '\intval', $resultItemIDs ) ) ) );
		$observedResultItemIDs = $this->loadObservedResultItemIDs( $queueItem->scan_id, $resultItemIDs );
		foreach ( \array_diff( $resultItemIDs, $observedResultItemIDs ) as $resultItemID ) {
			$dbCon->scan_results->getQueryInserter()
								->setInsertData( [
									'scan_ref'       => $queueItem->scan_id,
									'resultitem_ref' => $resultItemID,
								] )
								->query();
		}
	}

	/**
	 * @param ResultItemsDB\Record[] $scanResults
	 * @return array<string,ResultItemsDB\Record>
	 */
	private function loadExistingResultItems( string $scanSlug, array $scanResults ) :array {
		$pairWheres = \array_values( \array_unique( \array_map(
			fn( ResultItemsDB\Record $scanResult ) :string => sprintf(
				"(`item_type`='%s' AND `item_id`='%s')",
				esc_sql( (string)$scanResult->item_type ),
				esc_sql( (string)$scanResult->item_id )
			),
			$scanResults
		) ) );
		if ( empty( $pairWheres ) ) {
			return [];
		}

		$rows = Services::WpDb()->selectCustom(
			sprintf( "SELECT *
						FROM `%s`
						WHERE `resolved_at`=0
						  AND (%s)
						  AND (
							`scan`='%s'
							OR (
								`scan`=''
								AND `asset_type`=''
								AND `asset_key`=''
								AND `item_repaired_at`=0
								AND `item_deleted_at`=0
							)
						  );",
				self::con()->db_con->scan_result_items->getTable(),
				\implode( ' OR ', $pairWheres ),
				esc_sql( $scanSlug )
			)
		) ?: [];

		$records = [];
		foreach ( $rows as $row ) {
			$record = new ResultItemsDB\Record( $row );
			$key = $this->resultKey( $record );
			if ( (string)$record->scan === $scanSlug || !isset( $records[ $key ] ) ) {
				$records[ $key ] = $record;
			}
		}
		return $records;
	}

	private function loadObservedResultItemIDs( int $scanID, array $resultItemIDs ) :array {
		if ( empty( $resultItemIDs ) ) {
			return [];
		}

		return \array_values( \array_unique( \array_filter( \array_map(
			static fn( array $record ) :int => (int)$record[ 'resultitem_ref' ],
			Services::WpDb()->selectCustom(
				sprintf( "SELECT `resultitem_ref`
							FROM `%s`
							WHERE `scan_ref`=%d
							  AND `resultitem_ref` IN (%s);",
					self::con()->db_con->scan_results->getTable(),
					$scanID,
					\implode( ',', \array_map( '\intval', $resultItemIDs ) )
				)
			) ?: []
		) ) ) );
	}

	private function resultKey( ResultItemsDB\Record $scanResult ) :string {
		return (string)$scanResult->item_type."\n".(string)$scanResult->item_id;
	}

	private function lastInsertID() :int {
		global $wpdb;
		return (int)( \is_object( $wpdb ) && isset( $wpdb->insert_id ) ?
			$wpdb->insert_id
			: Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
	}
}
