<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItemMeta as ResultItemMetaDB;
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

	public function retrieveResults( int $context ) {
		$results = null;

		$latestID = $this->getLatestScanID();
		if ( $latestID >= 0 ) {

			$this->addWheres( [
				sprintf( "`sr`.`scan_ref`=%s", $latestID ),
				"`ri`.`item_repaired_at`=0",
				"`ri`.`item_deleted_at`=0",
				"`ri`.`deleted_at`=0",
			] );

			switch ( $context ) {

				case self::CONTEXT_RESULTS_TABLE:
					$specificWheres = [
						"`ri`.`ignored_at`=0"
					];
					break;

				case self::CONTEXT_AUTOREPAIR:
					$specificWheres = [
						"`ri`.`attempt_repair_at`=0",
						"`ri`.`ignored_at`=0"
					];
					break;

				case self::CONTEXT_LATEST:
				default:
					$specificWheres = [];
					break;
			}

			$results = $this
				->addWheres( $specificWheres )
				->retrieve();
		}

		return empty( $results ) ? $this->getScanController()->getNewResultsSet() : $results;
	}

	/**
	 * @return Scans\Base\ResultItem
	 * @throws \Exception
	 */
	public function byID( int $scanResultID ) {
		$mod = $this->mod();
		$WPDB = Services::WpDb();

		// Need to determine the scan from the scan result.
		$scan = $WPDB->getVar( sprintf( "SELECT `scans`.`scan`
					FROM `%s` as `scans`
					INNER JOIN `%s` as `sr`
						ON `sr`.`scan_ref` = `scans`.`id` AND `sr`.`id` = %s 
					LIMIT 1;",
			$this->mod()->getDbH_Scans()->getTableSchema()->table,
			$this->mod()->getDbH_ScanResults()->getTableSchema()->table,
			$scanResultID
		) );
		if ( empty( $scan ) ) {
			throw new \Exception( sprintf( 'Could not determine scan type from the scan result ID %s.', $scanResultID ) );
		}
		$this->setScanController( $mod->getScansCon()->getScanCon( $scan ) );

		$query = $this
			->addWheres( [
				sprintf( "`sr`.`id`=%s", $scanResultID )
			] )
			->buildQuery( $this->standardSelectFields() );
		$raw = Services::WpDb()->selectCustom( $query );
		$rawResults = empty( $raw ) ? [] : $raw;

		$resultSet = $this->convertToResultsSet( $rawResults );
		if ( $resultSet->countItems() !== 1 ) {
			throw new \Exception( sprintf( 'Scan result with ID %s does not exist.', $scanResultID ) );
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
			$latestID = $this->getLatestScanID();
			if ( $latestID >= 0 ) {
				$query = $this
					->addWheres( [
						sprintf( "`sr`.`id` IN (%s)", \implode( ',', $IDs ) )
					] )
					->buildQuery( $this->standardSelectFields() );
				$raw = Services::WpDb()->selectCustom( $query );
				$results = empty( $raw ) ? [] : $raw;
			}
		}

		return $this->convertToResultsSet( $results );
	}

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function retrieveForAutoRepair() {
		return $this->retrieveResults( self::CONTEXT_AUTOREPAIR );
	}

	public function retrieveForResultsTables() :Scans\Afs\ResultsSet {
		return $this->retrieveResults( self::CONTEXT_RESULTS_TABLE );
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
		$query = $this
			->addWheres( [
				"`ri`.`auto_filtered_at`=0",
				"`ri`.`deleted_at`=0"
			] )
			->buildQuery( $this->standardSelectFields() );
		$raw = Services::WpDb()->selectCustom( $query );
		return $this->convertToResultsSet( empty( $raw ) ? [] : $raw );
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
		$resultsSet = $this->getNewResultsSet();

		$workingScan = empty( $this->getScanController() ) ? '' : $this->getScanController()->getSlug();

		/** @var ScanResultVO[] $scanResults */
		$scanResults = \array_map( function ( $r ) {
			return ( new ScanResultVO() )->applyFromArray( $r );
		}, $results );

		$this->addMetaToResults( $scanResults );

		$scansCon = self::con()->getModule_HackGuard()->getScansCon();
		foreach ( $scanResults as $vo ) {

			// we haven't specified a type of scan, so we're collecting all results.
			if ( empty( $workingScan ) ) {
				foreach ( $vo->meta as $scanSlug => $scanMeta ) {
					$item = $scansCon->getScanCon( $vo->scan )
									 ->getNewResultItem()
									 ->applyFromArray( $vo->meta );
					$item->VO = $vo;
					$resultsSet->addItem( $item );
				}
			}
			elseif ( !empty( $vo->scan ) ) {
				$item = $scansCon->getScanCon( $workingScan )
								 ->getNewResultItem()
								 ->applyFromArray( $vo->meta );
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

				/** @var ResultItemMetaDB\Ops\Select $rimSelector */
				$rimSelector = $this->mod()->getDbH_ResultItemMeta()->getQuerySelector();
				/** @var ResultItemMetaDB\Ops\Record[] $metas */
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
		$mod = $this->mod();
		return sprintf( "SELECT %%s
						FROM `%s` as sr
						INNER JOIN `%s` as `scans`
							ON `sr`.scan_ref = `scans`.id
						INNER JOIN `%s` as `ri`
							ON `sr`.resultitem_ref = `ri`.id
						%s
						WHERE %%s
						%s
						%s
						%s;",
			$mod->getDbH_ScanResults()->getTableSchema()->table,
			$mod->getDbH_Scans()->getTableSchema()->table,
			$mod->getDbH_ResultItems()->getTableSchema()->table,
			$joinWithResultMeta ?
				sprintf( 'INNER JOIN `%s` as %s ON %s.`ri_ref` = `ri`.id',
					$mod->getDbH_ResultItemMeta()->getTableSchema()->table,
					self::ABBR_RESULTITEMMETA,
					self::ABBR_RESULTITEMMETA
				) : '',
			empty( $this->order_by ) ? 'ORDER BY `sr`.`id` ASC' : sprintf( 'ORDER BY %s %s', $this->order_by, $this->order_dir ),
			empty( $this->limit ) ? '' : sprintf( 'LIMIT %s', (int)$this->limit ),
			empty( $this->offset ) ? '' : sprintf( 'OFFSET %s', (int)$this->offset )
		);
	}

	private function standardSelectFields() :array {
		return [
			'`scans`.`scan`',
			'`scans`.`created_at` as `scan_created_at`',
			'`scans`.`id` as `scan_id`',
			'`sr`.`id` as `scanresult_id`',
			'`ri`.`id` as `resultitem_id`',
			'`ri`.`item_type`',
			'`ri`.`item_id`',
			'`ri`.`ignored_at`',
			'`ri`.`notified_at`',
			'`ri`.`attempt_repair_at`',
			'`ri`.`item_repaired_at`',
			'`ri`.`item_deleted_at`',
			'`ri`.`created_at`',
		];
	}

	private function getNewResultsSet() {
		$scanCon = $this->getScanController();
		return empty( $scanCon ) ? new ResultsSet() : $scanCon->getNewResultsSet();
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