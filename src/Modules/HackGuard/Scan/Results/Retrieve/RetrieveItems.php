<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

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

	public const CONTEXT_RESULTS_TABLE = 0;
	public const CONTEXT_AUTOREPAIR = 1;
	public const CONTEXT_LATEST = 2;
	public const CONTEXT_NOT_YET_NOTIFIED = 3;

	public function retrieveResults( int $context ) {
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

		$results = $this->retrieveByWheres( $specificWheres );
		return empty( $results ) ? $this->getScanController()->getNewResultsSet() : $results;
	}

	public function retrieveLatestForFindings( array $stateMetaKeys = [] ) {
		$results = null;
		$stateMetaKeys = \array_values( \array_unique( \array_filter( \array_map(
			static fn( $stateMetaKey ) :string => \preg_replace( '/[^a-z0-9_]/i', '', (string)$stateMetaKey ) ?? '',
			$stateMetaKeys
		) ) ) );

		$wheres = ( new LatestScanResultWheresBuilder() )->forLatestResults( $this->getScanController()->getSlug() );
		if ( !empty( $stateMetaKeys ) ) {
			$wheres[] = $this->buildStateMetaExistsWhere( $stateMetaKeys );
		}
		$results = $this->retrieveByWheres( $wheres );

		return empty( $results ) ? $this->getScanController()->getNewResultsSet() : $results;
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

	/**
	 * @param array<string,mixed>|null $options
	 */
	public function retrieveForResultsTables( ?array $options = null ) :Scans\Afs\ResultsSet {
		if ( $options === null ) {
			return $this->retrieveResults( self::CONTEXT_RESULTS_TABLE );
		}

		$results = $this->retrieveByWheres(
			( new LatestScanResultWheresBuilder() )->forResultsDisplayWithOptions( $this->getScanController()->getSlug(), $options )
		);

		return empty( $results ) ? $this->getScanController()->getNewResultsSet() : $results;
	}

	/**
	 * @return Scans\Afs\ResultsSet|Scans\Apc\ResultsSet|Scans\Wpv\ResultsSet
	 */
	public function retrieveLatest() {
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

	public function buildQuery( array $selectFields = [] ) :string {

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
	 * @return ResultsSet|mixed
	 */
	protected function convertToResultsSet( array $results ) {
		$con = self::con();
		$resultsSet = $this->getNewResultsSet();
		$workingScan = $this->getScanController();
		$workingScanSlug = empty( $workingScan ) ? '' : $workingScan->getSlug();

		/** @var ScanResultVO[] $scanResults */
		$scanResults = \array_map( function ( $r ) {
			return ( new ScanResultVO() )->applyFromArray( $r );
		}, $results );

		$this->addMetaToResults( $scanResults );

		foreach ( $scanResults as $vo ) {
			$scanCon = empty( $workingScanSlug )
				? $con->comps->scans->getScanCon( $vo->scan )
				: $con->comps->scans->getScanCon( $workingScanSlug );
			if ( !empty( $scanCon ) ) {
				$item = $scanCon->getNewResultItem()->applyFromArray( $vo->meta );
				$item->VO = $vo;
				$resultsSet->addItem( $item );
			}
		}
		return $resultsSet;
	}

	/**
	 * @param ScanResultVO[] $results
	 */
	private function addMetaToResults( array $results ) {
		$offset = 0;
		$length = 200;
		do {
			$resultsSlice = \array_slice( $results, $offset, $length );
			if ( !empty( $resultsSlice ) ) {
				$resultItemIDs = \array_map( function ( $res ) {
					return $res->resultitem_id;
				}, $resultsSlice );

				/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItemMeta\Ops\Select $rimSelector */
				$rimSelector = self::con()->db_con->scan_result_item_meta->getQuerySelector();
				/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItemMeta\Ops\Record[] $metas */
				$metas = $rimSelector->filterByResultItems( $resultItemIDs )->queryWithResult();

				foreach ( $resultsSlice as $result ) {
					$meta = $result->meta;
					foreach ( $metas as $metaRecord ) {
						if ( $metaRecord->ri_ref == $result->resultitem_id ) {
							$meta[ $metaRecord->meta_key ] = $metaRecord->meta_value;
						}
					}
					$result->meta = $meta;
				}
				$offset += $length;
			}
		} while ( !empty( $resultsSlice ) );
	}

	protected function getBaseQuery( bool $joinWithResultMeta = false ) :string {
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

	private function standardSelectFields() :array {
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

	private function getNewResultsSet() {
		$scanCon = $this->getScanController();
		return empty( $scanCon ) ? new ResultsSet() : $scanCon->getNewResultsSet();
	}

	private function retrieveByWheres( array $wheres ) {
		return $this->withMergedWheres( $wheres, function () {
			$query = $this->buildQuery( $this->standardSelectFields() );
			$raw = Services::WpDb()->selectCustom( $query );
			return $this->convertToResultsSet( empty( $raw ) ? [] : $raw );
		} );
	}

	private function buildStateMetaExistsWhere( array $stateMetaKeys ) :string {
		$metaTable = self::con()->db_con->scan_result_item_meta->getTable();
		$exists = \array_map(
			static function ( string $stateMetaKey ) use ( $metaTable ) :string {
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

	public function getSelects() :array {
		return \array_filter( \array_map( '\trim', \is_array( $this->selects ) ? $this->selects : [] ) );
	}

	/**
	 * @return $this
	 */
	public function addSelects( array $selects, bool $merge = true ) {
		$this->selects = $merge ? \array_merge( $this->getSelects(), $selects ) : $selects;
		return $this;
	}
}
