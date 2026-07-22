<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Malware\Ops\Record as MalwareRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler as ResultItemsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs as AfsScanController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ScanResultVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $selects
 * @property string   $order_by
 * @property string   $order_dir
 */
class RetrieveItems extends RetrieveBase {
	private const HYDRATION_BATCH_SIZE = 200;

	public const CONTEXT_RESULTS_TABLE = 0;
	public const CONTEXT_AUTOREPAIR = 1;
	public const CONTEXT_LATEST = 2;
	public const CONTEXT_NOT_YET_NOTIFIED = 3;

	public function retrieveResults( int $context ) :ResultsSet {
		$wheresBuilder = new LatestScanResultWheresBuilder();
		$scanSlug = $this->getScanController()->getSlug();
		switch ( $context ) {

			case self::CONTEXT_RESULTS_TABLE:
				$specificWheres = $wheresBuilder->forResultsDisplay( $scanSlug );
				break;

			case self::CONTEXT_AUTOREPAIR:
				$scanSlug = \preg_replace( '/[^a-z0-9_]/i', '', $scanSlug ) ?? '';
				$specificWheres = [
					\sprintf( "`ri`.`scan`='%s'", $scanSlug ),
					"`ri`.`resolved_at`=0",
					"`ri`.`attempt_repair_at`=0",
					"`ri`.`ignored_at`=0"
				];
				break;

			case self::CONTEXT_NOT_YET_NOTIFIED:
				$specificWheres = $wheresBuilder->forNotYetNotified( $scanSlug );
				break;

			case self::CONTEXT_LATEST:
			default:
				$specificWheres = $wheresBuilder->forLatestResults( $scanSlug );
				break;
		}

		return $this->retrieveByWheres( $specificWheres );
	}

	public function retrieveLatestForFindings( array $stateMetaKeys = [] ) :ResultsSet {
		$stateMetaKeys = \array_values( \array_unique( \array_filter( \array_map(
			static fn( $stateMetaKey ): string => \preg_replace( '/[^a-z0-9_]/i', '', (string)$stateMetaKey ) ?? '',
			$stateMetaKeys
		) ) ) );

		$wheres = ( new LatestScanResultWheresBuilder() )->forLatestResults( $this->getScanController()->getSlug() );
		if ( !empty( $stateMetaKeys ) ) {
			$wheres[] = $this->buildStateMetaExistsWhere( $stateMetaKeys );
		}
		return $this->retrieveByWheres( $wheres );
	}

	/**
	 * @return Scans\Base\ResultItem
	 * @throws \Exception
	 */
	public function byID( int $resultItemID ) {
		$WPDB = Services::WpDb();

		$scan = $WPDB->getVar( sprintf( "SELECT `ri`.`scan`
					FROM `%s` as `ri`
					WHERE `ri`.`id` = %s
					LIMIT 1;",
			self::con()->db_con->scan_result_items->getTable(),
			$resultItemID
		) );
		if ( empty( $scan ) ) {
			throw new \Exception( sprintf( __( 'Could not determine scan type from the result item ID %s.', 'wp-simple-firewall' ), $resultItemID ) );
		}
		$this->setScanController( self::con()->comps->scans->getScanCon( $scan ) );

		$query = $this
			->addWheres( [
				sprintf( "`ri`.`id`=%s", $resultItemID )
			] )
			->buildQuery( $this->standardSelectFields() );
		$raw = Services::WpDb()->selectCustom( $query );
		$rawResults = empty( $raw ) ? [] : $raw;

		$resultSet = $this->convertToResultsSet( $rawResults );
		if ( $resultSet->countItems() !== 1 ) {
			throw new \Exception( sprintf( __( 'Scan result with ID %s does not exist.', 'wp-simple-firewall' ), $resultItemID ) );
		}
		$items = $resultSet->getAllItems();
		return \array_shift( $items );
	}

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function byIDs( array $IDs ) {
		$results = [];
		if ( !$this->getScanController()->isRestricted() ) {
			$query = $this
				->addWheres( [
					sprintf( "`ri`.`id` IN (%s)", \implode( ',', $IDs ) )
				] )
				->buildQuery( $this->standardSelectFields() );
			$raw = Services::WpDb()->selectCustom( $query );
			$results = empty( $raw ) ? [] : $raw;
		}

		return $this->convertToResultsSet( $results );
	}

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function retrieveForAutoRepair() {
		return $this->retrieveResults( self::CONTEXT_AUTOREPAIR );
	}

	public function retrieveActiveProblems() :ResultsSet {
		return $this->retrieveByWheres(
			( new LatestScanResultWheresBuilder() )->forActiveProblems( $this->getScanController()->getSlug() )
		);
	}

	/**
	 * @param array<string,mixed>|null $options
	 */
	public function retrieveForResultsTables( ?array $options = null ): Scans\Afs\ResultsSet {
		if ( $options === null ) {
			$results = $this->retrieveResults( self::CONTEXT_RESULTS_TABLE );
		}
		else {
			$results = $this->retrieveByWheres(
				( new LatestScanResultWheresBuilder() )->forResultsDisplayWithOptions( $this->getScanController()
				                                                                            ->getSlug(), $options )
			);
		}
		if ( !$results instanceof Scans\Afs\ResultsSet ) {
			throw new \UnexpectedValueException( 'AFS results retrieval produced an invalid result set.' );
		}
		return $results;
	}

	public function retrieveLatest() :ResultsSet {
		return $this->retrieveResults( self::CONTEXT_LATEST );
	}

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function retrieve() {
		return $this->retrieveByWheres( [
			"`ri`.`auto_filtered_at`=0",
		] );
	}

	public function buildQuery( array $selectFields = [] ): string {

		$hasResultMeta = false;
		foreach ( $this->getWheres() as $where ) {
			if ( \strpos( $where, self::ABBR_RESULTITEMMETA ) !== false ) {
				$hasResultMeta = true;
				break;
			}
		}

		return sprintf(
			$this->getBaseQuery( $hasResultMeta ),
			\implode( ',', $selectFields ),
			\implode( ' AND ', $this->getWheres() )
		);
	}

	/**
	 * @param array[] $results
	 */
	protected function convertToResultsSet( array $results ) :ResultsSet {
		$con = self::con();
		$workingScan = $this->getScanController();
		$workingScanSlug = empty( $workingScan ) ? '' : $workingScan->getSlug();

		/** @var ScanResultVO[] $scanResults */
		$scanResults = \array_map( fn( $r ) => ( new ScanResultVO() )->applyFromArray( $r ), $results );

		$this->addMetaToResults( $scanResults );
		$malwareRecords = $this->loadMalwareRecords( $scanResults );

		$resultsSet = $this->getNewResultsSet();
		foreach ( $scanResults as $vo ) {
			$scanSlug = empty( $workingScanSlug ) ? (string)$vo->scan : $workingScanSlug;
			$scanCon = $con->comps->scans->getScanCon( $scanSlug );
			if ( !empty( $scanCon ) ) {
				$itemData = $this->buildResultItemData( $vo, $scanSlug );
				$vo->meta = $itemData;
				$item = $scanCon->getNewResultItem()->applyFromArray( $itemData );
				$item->VO = $vo;
				if ( $item instanceof Scans\Afs\ResultItem ) {
					$item->setMalwareRecord( $malwareRecords[ $item->malware_record_id ] ?? null );
				}
				$resultsSet->addItem( $item );
			}
		}
		return $resultsSet;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildResultItemData( ScanResultVO $vo, string $scanSlug ) :array {
		$itemData = \is_array( $vo->meta ) ? $vo->meta : [];

		if ( $scanSlug === AfsScanController::SCAN_SLUG && $vo->item_type === ResultItemsHandler::ITEM_TYPE_FILE ) {
			unset( $itemData[ 'path_full' ], $itemData[ 'path_fragment' ], $itemData[ 'file_path' ] );
			$vo->item_id = Services::WpFs()->getPathRelativeToAbsPath( (string)$vo->item_id );
			$itemData[ 'path_fragment' ] = $vo->item_id;
		}

		return $itemData;
	}

	/**
	 * @param ScanResultVO[] $results
	 */
	private function addMetaToResults( array $results ) :void {
		$offset = 0;
		$length = self::HYDRATION_BATCH_SIZE;
		do {
			$resultsSlice = \array_slice( $results, $offset, $length );
			if ( !empty( $resultsSlice ) ) {
				$resultItemIDs = \array_map( static fn( $res ) => $res->resultitem_id, $resultsSlice );

				/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItemMeta\Ops\Select $rimSelector */
				$rimSelector = self::con()->db_con->scan_result_item_meta->getQuerySelector();
				/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItemMeta\Ops\Record[] $metas */
				$metas = $rimSelector->filterByResultItems( $resultItemIDs )->queryWithResult();

				$metasByResultID = [];
				foreach ( $metas as $metaRecord ) {
					$metasByResultID[ $metaRecord->ri_ref ][ $metaRecord->meta_key ] = $metaRecord->meta_value;
				}

				foreach ( $resultsSlice as $result ) {
					$meta = $result->meta;
					$meta = \array_merge( $meta, $metasByResultID[ $result->resultitem_id ] ?? [] );
					$result->meta = $meta;
				}
				$offset += $length;
			}
		} while ( !empty( $resultsSlice ) );
	}

	/**
	 * @param ScanResultVO[] $results
	 * @return array<int,MalwareRecord>
	 */
	private function loadMalwareRecords( array $results ) :array {
		$recordIDs = [];
		foreach ( $results as $result ) {
			$recordID = (int)( $result->meta[ 'malware_record_id' ] ?? 0 );
			if ( $result->scan === AfsScanController::SCAN_SLUG && $recordID > 0 ) {
				$recordIDs[ $recordID ] = $recordID;
			}
		}

		$recordsByID = [];
		foreach ( \array_chunk( \array_values( $recordIDs ), self::HYDRATION_BATCH_SIZE ) as $recordIDChunk ) {
			/** @var MalwareRecord[] $records */
			$records = self::con()->db_con->malware
				->getQuerySelector()
				->addWhereIn( 'id', $recordIDChunk )
				->queryWithResult();
			foreach ( $records as $record ) {
				$recordsByID[ (int)$record->id ] = $record;
			}
		}
		return $recordsByID;
	}

	protected function getBaseQuery( bool $joinWithResultMeta = false ): string {
		$dbCon = self::con()->db_con;
		return sprintf( "SELECT %%s
						FROM `%s` as `ri`
						%s
						WHERE %%s
						%s
						%s
						%s;",
			$dbCon->scan_result_items->getTable(),
			$joinWithResultMeta ?
				sprintf( 'INNER JOIN `%s` as %s ON %s.`ri_ref` = `ri`.id',
					$dbCon->scan_result_item_meta->getTable(),
					self::ABBR_RESULTITEMMETA,
					self::ABBR_RESULTITEMMETA
				) : '',
			empty( $this->order_by ) ? 'ORDER BY `ri`.`id` ASC' : sprintf( 'ORDER BY %s %s', $this->order_by, $this->order_dir ),
			empty( $this->limit ) ? '' : sprintf( 'LIMIT %s', (int)$this->limit ),
			empty( $this->offset ) ? '' : sprintf( 'OFFSET %s', (int)$this->offset )
		);
	}

	private function standardSelectFields(): array {
		return [
			'`ri`.`scan`',
			'0 as `scan_created_at`',
			'0 as `scan_id`',
			'`ri`.`id` as `resultitem_id`',
			'`ri`.`item_type`',
			'`ri`.`item_id`',
			'`ri`.`asset_type`',
			'`ri`.`asset_key`',
			'`ri`.`ignored_at`',
			'`ri`.`notified_at`',
			'`ri`.`attempt_repair_at`',
			'`ri`.`last_seen_at`',
			'`ri`.`resolved_at`',
			'`ri`.`resolution_reason`',
			'`ri`.`created_at`',
		];
	}

	private function getNewResultsSet() :ResultsSet {
		$scanCon = $this->getScanController();
		return empty( $scanCon ) ? new ResultsSet() : $scanCon->getNewResultsSet();
	}

	private function retrieveByWheres( array $wheres ) :ResultsSet {
		return $this->withMergedWheres( $wheres, function () {
			$query = $this->buildQuery( $this->standardSelectFields() );
			$raw = Services::WpDb()->selectCustom( $query );
			return $this->convertToResultsSet( empty( $raw ) ? [] : $raw );
		} );
	}

	private function buildStateMetaExistsWhere( array $stateMetaKeys ): string {
		$metaTable = self::con()->db_con->scan_result_item_meta->getTable();
		$exists = \array_map(
			static function ( string $stateMetaKey ) use ( $metaTable ): string {
				return \sprintf(
					"EXISTS (SELECT 1 FROM `%s` AS `rim_state` WHERE `rim_state`.`ri_ref`=`ri`.`id` AND `rim_state`.`meta_key`='%s' AND `rim_state`.`meta_value`!='' AND `rim_state`.`meta_value`!='0')",
					$metaTable,
					$stateMetaKey
				);
			},
			\array_values( \array_filter( $stateMetaKeys ) )
		);

		return \sprintf( '(%s)', \implode( ' OR ', $exists ) );
	}
}
