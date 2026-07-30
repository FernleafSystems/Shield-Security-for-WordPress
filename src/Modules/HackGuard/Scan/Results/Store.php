<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ResultItemMeta\Ops as ResultItemMetaDB,
	ResultItems\Ops as ResultItemsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
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
		$fullAfsAction = $this->buildFullAfsAction( $queueItem );
		$protectedCandidateIDs = [];
		if ( $fullAfsAction instanceof ScanActionVO ) {
			foreach ( $scanResults as $scanResult ) {
				$resultRecord = $existingResultRecords[ $this->resultKey( $scanResult ) ] ?? null;
				if ( $resultRecord instanceof ResultItemsDB\Record
					 && $this->isIneligibleMalwareOnlyResult( $scanResult, $fullAfsAction ) ) {
					$protectedCandidateIDs[] = (int)$resultRecord->id;
				}
			}
		}
		$existingMetas = $this->loadResultItemMetas( $protectedCandidateIDs );
		$updatedResultIDs = [];
		$resultItemIDs = [];
		$metaRows = [];

		foreach ( $scanResults as $scanResult ) {
			$preserveExistingFacet = false;
			$key = $this->resultKey( $scanResult );
			/** @var ?ResultItemsDB\Record $resultRecord */
			$resultRecord = $existingResultRecords[ $key ] ?? null;
			if ( $resultRecord === null ) {
				if ( !$dbCon->scan_result_items->getQueryInserter()->insert( $scanResult ) ) {
					throw new \RuntimeException( 'Scan result item insert failed.' );
				}
				$scanResult->id = $this->lastInsertID();
				if ( $scanResult->id < 1 ) {
					throw new \RuntimeException( 'Scan result item insert ID was invalid.' );
				}
				$resultRecord = $scanResult;
				$existingResultRecords[ $key ] = $resultRecord;
			}
			else {
				$existingMeta = $existingMetas[ (int)$resultRecord->id ] ?? null;
				$preserveExistingFacet = \is_array( $existingMeta )
					&& $fullAfsAction instanceof ScanActionVO
					&& $this->isIneligibleMalwareOnlyResult( $scanResult, $fullAfsAction )
					&& $this->hasNonMalwareFinding( $existingMeta );
				if ( !$dbCon->scan_result_items->getQueryUpdater()->updateRecord( $resultRecord, [
					'scan'              => $scanResult->scan,
					'asset_type'        => $preserveExistingFacet ? $resultRecord->asset_type : $scanResult->asset_type,
					'asset_key'         => $preserveExistingFacet ? $resultRecord->asset_key : $scanResult->asset_key,
					'auto_filtered_at'  => $scanResult->auto_filtered_at,
					'last_seen_at'      => $scanResult->last_seen_at,
					'resolved_at'       => $preserveExistingFacet ? $resultRecord->resolved_at : $scanResult->resolved_at,
					'resolution_reason' => $preserveExistingFacet ? $resultRecord->resolution_reason : $scanResult->resolution_reason,
				] ) ) {
					throw new \RuntimeException( 'Scan result item update failed.' );
				}
				if ( $preserveExistingFacet ) {
					$this->replaceMalwareFacetMeta( (int)$resultRecord->id, $scanResult->meta );
				}
				else {
					$updatedResultIDs[] = (int)$resultRecord->id;
				}
			}

			if ( empty( $preserveExistingFacet ) ) {
				foreach ( $scanResult->meta as $metaKey => $metaValue ) {
					$metaRows[] = [
						'ri_ref'     => $resultRecord->id,
						'meta_key'   => $metaKey,
						'meta_value' => \is_scalar( $metaValue ) ? $metaValue : \wp_json_encode( $metaValue ),
					];
				}
			}

			$resultItemIDs[] = (int)$resultRecord->id;
		}

		$updatedResultIDs = \array_values( \array_unique( \array_filter( \array_map( '\intval', $updatedResultIDs ) ) ) );
		if ( !empty( $updatedResultIDs ) ) {
			/** @var ResultItemMetaDB\Delete $metaDeleter */
			$metaDeleter = $dbhResItemMetas->getQueryDeleter();
			$metaDeleter->filterByResultItems( $updatedResultIDs )->query();
			if ( $metaDeleter->getLastQueryResult() === false ) {
				throw new \RuntimeException( 'Scan result metadata delete failed.' );
			}
		}

		if ( !$this->bulkInsertRows( $dbhResItemMetas->getTable(), [ 'ri_ref', 'meta_key', 'meta_value' ], $metaRows ) ) {
			throw new \RuntimeException( 'Scan result metadata insert failed.' );
		}

		$resultItemIDs = \array_values( \array_unique( \array_filter( \array_map( '\intval', $resultItemIDs ) ) ) );
		$observedResultItemIDs = $this->loadObservedResultItemIDs( $queueItem->scan_id, $resultItemIDs );
		$observationRows = [];
		$createdAt = Services::Request()->ts();
		foreach ( \array_diff( $resultItemIDs, $observedResultItemIDs ) as $resultItemID ) {
			$observationRows[] = [
				'scan_ref'       => $queueItem->scan_id,
				'resultitem_ref' => $resultItemID,
				'created_at'     => $createdAt,
			];
		}
		if ( !$this->bulkInsertRows( $dbCon->scan_results->getTable(), [ 'scan_ref', 'resultitem_ref', 'created_at' ], $observationRows ) ) {
			throw new \RuntimeException( 'Scan result observation insert failed.' );
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

	/**
	 * @param list<int> $resultItemIDs
	 * @return array<int,array<string,mixed>>
	 */
	private function loadResultItemMetas( array $resultItemIDs ) :array {
		$resultItemIDs = \array_values( \array_unique( \array_filter( \array_map( '\intval', $resultItemIDs ) ) ) );
		if ( empty( $resultItemIDs ) ) {
			return [];
		}

		global $wpdb;
		$rows = Services::WpDb()->selectCustom( \sprintf(
			"SELECT `ri_ref`, `meta_key`, `meta_value`
				FROM `%s`
				WHERE `ri_ref` IN (%s);",
			self::con()->db_con->scan_result_item_meta->getTable(),
			\implode( ',', $resultItemIDs )
		) );
		if ( !\is_array( $rows )
			 || ( \is_object( $wpdb ) && (string)( $wpdb->last_error ?? '' ) !== '' ) ) {
			throw new \RuntimeException( 'Scan result metadata read failed.' );
		}

		$metas = [];
		foreach ( $rows as $row ) {
			$resultItemID = (int)( $row[ 'ri_ref' ] ?? 0 );
			$metaKey = (string)( $row[ 'meta_key' ] ?? '' );
			if ( $resultItemID > 0 && $metaKey !== '' ) {
				$metas[ $resultItemID ][ $metaKey ] = $row[ 'meta_value' ] ?? '';
			}
		}
		return $metas;
	}

	private function buildFullAfsAction( QueueItemVO $queueItem ) :?ScanActionVO {
		if ( $queueItem->scan !== 'afs' || $queueItem->scope_type !== 'full' ) {
			return null;
		}

		return ( new ScanActionVO() )->applyFromArray( \array_merge(
			$queueItem->meta,
			[
				'scan'       => $queueItem->scan,
				'scope_type' => $queueItem->scope_type,
				'scope_key'  => $queueItem->scope_key,
			]
		) );
	}

	private function isIneligibleMalwareOnlyResult( ResultItemsDB\Record $result, ScanActionVO $action ) :bool {
		$meta = \is_array( $result->meta ) ? $result->meta : [];
		$assetType = (string)$result->asset_type;
		$assetKey = (string)$result->asset_key;
		$assetVersion = (string)( $meta[ 'asset_version' ] ?? '' );
		return $this->isTruthy( $meta[ 'is_mal' ] ?? null )
			   && !$this->hasNonMalwareFinding( $meta )
			   && \in_array( $assetType, [ 'plugin', 'theme' ], true )
			   && $this->isValidExactString( $assetKey )
			   && $this->isValidExactString( $assetVersion )
			   && !$action->isAssetSnapshotComparisonEligible( $assetType, $assetKey, $assetVersion );
	}

	private function hasNonMalwareFinding( array $meta ) :bool {
		foreach ( [ 'is_unrecognised', 'is_missing', 'is_checksumfail', 'is_unidentified' ] as $metaKey ) {
			if ( $this->isTruthy( $meta[ $metaKey ] ?? null ) ) {
				return true;
			}
		}
		return false;
	}

	private function replaceMalwareFacetMeta( int $resultItemID, array $meta ) :void {
		$table = self::con()->db_con->scan_result_item_meta->getTable();
		foreach ( [ 'is_mal', 'malware_record_id' ] as $metaKey ) {
			if ( Services::WpDb()->doSql( \sprintf(
				"DELETE FROM `%s`
					WHERE `ri_ref`=%d
					  AND `meta_key`='%s';",
				$table,
				$resultItemID,
				$metaKey
			) ) === false ) {
				throw new \RuntimeException( 'Scan result malware metadata delete failed.' );
			}

			if ( \array_key_exists( $metaKey, $meta ) ) {
				$metaValue = \is_scalar( $meta[ $metaKey ] ) ? $meta[ $metaKey ] : \wp_json_encode( $meta[ $metaKey ] );
				if ( Services::WpDb()->doSql( \sprintf(
					"INSERT INTO `%s` (`ri_ref`,`meta_key`,`meta_value`)
						VALUES ('%d','%s','%s');",
					$table,
					$resultItemID,
					$metaKey,
					esc_sql( (string)$metaValue )
				) ) === false ) {
					throw new \RuntimeException( 'Scan result malware metadata insert failed.' );
				}
			}
		}
	}

	private function isValidExactString( string $value ) :bool {
		return \trim( $value ) !== '' && \strpos( $value, "\0" ) === false;
	}

	private function isTruthy( $value ) :bool {
		return $value !== '' && $value !== '0' && $value !== 0 && $value !== false && $value !== null;
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

	private function bulkInsertRows( string $table, array $columns, array $rows ) :bool {
		if ( empty( $rows ) ) {
			return true;
		}

		$values = [];
		foreach ( $rows as $row ) {
			$values[] = "('".\implode( "','", \array_map(
				static fn( string $column ) :string => esc_sql( (string)$row[ $column ] ),
				$columns
			) )."')";
		}

		return Services::WpDb()->doSql(
			sprintf( "INSERT INTO `%s` (`%s`) VALUES %s;",
				$table,
				\implode( '`,`', $columns ),
				\implode( ',', $values )
			)
		) !== false;
	}

	private function lastInsertID() :int {
		global $wpdb;
		return (int)( \is_object( $wpdb ) && isset( $wpdb->insert_id ) ?
			$wpdb->insert_id
			: Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
	}
}
