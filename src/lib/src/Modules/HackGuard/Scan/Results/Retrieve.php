<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\{
	ResultItemMeta as ResultItemMetaDB,
	Scans as ScansDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet;
use FernleafSystems\Wordpress\Services\Services;

class Retrieve {

	use ModConsumer;
	use ScanControllerConsumer;

	private $additionalWheres = [];

	/**
	 * @param int $scanResultID
	 * @return Scans\Base\ResultItem
	 * @throws \Exception
	 */
	public function byID( int $scanResultID ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$WPDB = Services::WpDb();

		// Need to determine the scan from the scan result.
		$scan = $WPDB->getVar( sprintf( "SELECT scans.scan
					FROM `%s` as scans
					INNER JOIN `%s` as `sr`
						ON `sr`.scan_ref = `scans`.id AND `sr`.id = %s 
					LIMIT 1;",
			$mod->getDbH_Scans()->getTableSchema()->table,
			$mod->getDbH_ScanResults()->getTableSchema()->table,
			$scanResultID
		) );
		if ( empty( $scan ) ) {
			throw new \Exception( sprintf( 'Could not determine scan type from the scan result ID %s.', $scanResultID ) );
		}
		$this->setScanController( $mod->getScanCon( $scan ) );

		$raw = Services::WpDb()->selectCustom(
			sprintf( $this->getBaseQuery(),
				implode( ',', $this->standardSelectFields() ),
				implode( ' AND ', array_filter( array_merge(
					[
						sprintf( "`sr`.`id`=%s", $scanResultID )
					],
					$this->getAdditionalWheres()
				) ) )
			)
		);
		$rawResults = empty( $raw ) ? [] : $raw;

		$resultSet = $this->convertToResultsSet( $rawResults );
		if ( $resultSet->countItems() !== 1 ) {
			throw new \Exception( sprintf( 'Scan result with ID %s does not exist.', $scanResultID ) );
		}
		$items = $resultSet->getAllItems();
		return array_shift( $items );
	}

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function byIDs( array $IDs ) {
		$results = [];
		if ( !$this->getScanController()->isRestricted() ) {
			$latestID = $this->getLatestScanID();
			if ( $latestID >= 0 ) {
				$raw = Services::WpDb()->selectCustom(
					sprintf( $this->getBaseQuery(),
						implode( ',', $this->standardSelectFields() ),
						implode( ' AND ', array_filter( array_merge(
							[
								sprintf( "`sr`.`id` IN (%s)", implode( ',', $IDs ) )
							],
							$this->getAdditionalWheres()
						) ) )
					)
				);
				if ( !empty( $raw ) ) {
					$results = $raw;
				}
			}
		}

		return $this->convertToResultsSet( $results );
	}

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function retrieveForAutoRepair() {

		$latestID = $this->getLatestScanID();
		if ( $latestID >= 0 ) {
			$results = $this
				->setAdditionalWheres( [
					sprintf( "`sr`.`scan_ref`=%s", $latestID ),
					"`ri`.`attempt_repair_at`=0",
					"`ri`.`item_repaired_at`=0",
					"`ri`.`item_deleted_at`=0",
					"`ri`.ignored_at=0"
				] )
				->retrieve();
		}
		else {
			$results = $this->getScanController()->getNewResultsSet();
		}

		return $results;
	}

	/**
	 * @return Scans\Afs\ResultsSet|Scans\Apc\ResultsSet|Scans\Wpv\ResultsSet
	 */
	public function retrieveLatest( bool $includeIgnored = true ) {

		$latestID = $this->getLatestScanID();
		if ( $latestID >= 0 ) {
			$results = $this
				->setAdditionalWheres( [
					sprintf( "`sr`.`scan_ref`=%s", $latestID ),
					$includeIgnored ? '' : "`ri`.ignored_at = 0",
					"`ri`.`item_repaired_at`=0",
					"`ri`.`item_deleted_at`=0"
				] )
				->retrieve();
		}
		else {
			$results = $this->getScanController()->getNewResultsSet();
		}

		return $results;
	}

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function retrieve() {
		$results = [];
		$raw = Services::WpDb()->selectCustom(
			sprintf( $this->getBaseQuery(),
				implode( ',', $this->standardSelectFields() ),
				implode( ' AND ', array_filter( array_merge(
					[
						"`ri`.`auto_filtered_at`=0",
						"`ri`.`deleted_at`=0"
					],
					$this->getAdditionalWheres()
				) ) )
			)
		);
		if ( !empty( $raw ) ) {
			$results = $raw;
		}

		return $this->convertToResultsSet( $results );
	}

	public function count() :int {
		$count = 0;
		$latestID = $this->getLatestScanID();
		if ( $latestID >= 0 ) {
			$count = (int)Services::WpDb()->getVar(
				sprintf( $this->getBaseCountQuery(),
					implode( ' AND ', array_filter( array_merge(
						[
							sprintf( "`sr`.`scan_ref`=%s", $latestID ),
							"`ri`.`auto_filtered_at`=0",
							"`ri`.`ignored_at` = 0",
							"`ri`.`item_repaired_at`=0",
							"`ri`.`item_deleted_at`=0",
							"`ri`.`deleted_at`=0"
						],
						$this->getAdditionalWheres()
					) ) )
				)
			);
		}
		return $count;
	}

	/**
	 * @param array[] $results
	 * @return ResultsSet|mixed
	 */
	protected function convertToResultsSet( array $results ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$resultsSet = $this->getNewResultsSet();

		$workingScan = empty( $this->getScanController() ) ? '' : $this->getScanController()->getSlug();

		/** @var ScanResultVO[] $scanResults */
		$scanResults = array_map( function ( $r ) {
			return ( new ScanResultVO() )->applyFromArray( $r );
		}, $results );

		$this->addMetaToResults( $scanResults );

		foreach ( $scanResults as $vo ) {

			// we haven't specified a type of scan, so we're collecting all results.
			if ( empty( $workingScan ) ) {
				foreach ( $vo->meta as $scanSlug => $scanMeta ) {
					$scanCon = $mod->getScanCon( $vo->scan );
					$item = $scanCon->getNewResultItem()->applyFromArray( $vo->meta );
					$item->VO = $vo;
					$resultsSet->addItem( $item );
				}
			}
			elseif ( !empty( $vo->scan ) ) {
				$scanCon = $mod->getScanCon( $workingScan );
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
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$resultItemIDs = array_map( function ( $res ) {
			return $res->resultitem_id;
		}, $results );

		/** @var ResultItemMetaDB\Ops\Select $rimSelector */
		$rimSelector = $mod->getDbH_ResultItemMeta()->getQuerySelector();
		/** @var ResultItemMetaDB\Ops\Record[] $metas */
		$metas = $rimSelector->filterByResultItems( $resultItemIDs )->queryWithResult();

		foreach ( $results as $result ) {
			$meta = $result->meta;
			foreach ( $metas as $metaRecord ) {
				if ( $metaRecord->ri_ref == $result->resultitem_id ) {
					$meta[ $metaRecord->meta_key ] = $metaRecord->meta_value;
				}
			}
			$result->meta = $meta;
		}
	}

	private function getLatestScanID() :int {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var ScansDB\Ops\Select $scansSelector */
		$scansSelector = $mod->getDbH_Scans()->getQuerySelector();
		$latest = $scansSelector->getLatestForScan( $this->getScanController()->getSlug() );
		return empty( $latest ) ? -1 : $latest->id;
	}

	private function getBaseQuery() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return sprintf( "SELECT %%s
						FROM `%s` as sr
						INNER JOIN `%s` as `scans`
							ON `sr`.scan_ref = `scans`.id
						INNER JOIN `%s` as `ri`
							ON `sr`.resultitem_ref = `ri`.id
						WHERE %%s
						ORDER BY `sr`.`id` ASC;",
			$mod->getDbH_ScanResults()->getTableSchema()->table,
			$mod->getDbH_Scans()->getTableSchema()->table,
			$mod->getDbH_ResultItems()->getTableSchema()->table
		);
	}

	private function getBaseCountQuery() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return sprintf( "SELECT COUNT(*)
						FROM `%s` as sr
						INNER JOIN `%s` as `scans`
							ON `sr`.scan_ref = `scans`.id
						INNER JOIN `%s` as `ri`
							ON `sr`.resultitem_ref = `ri`.id
						INNER JOIN `%s` as `rim`
							ON `rim`.`ri_ref` = `ri`.id
						WHERE %%s;",
			$mod->getDbH_ScanResults()->getTableSchema()->table,
			$mod->getDbH_Scans()->getTableSchema()->table,
			$mod->getDbH_ResultItems()->getTableSchema()->table,
			$mod->getDbH_ResultItemMeta()->getTableSchema()->table
		);
	}

	private function standardSelectFields() :array {
		return [
			'scans.scan',
			'scans.id as scan_id',
			'sr.id as scanresult_id',
			'ri.id as resultitem_id',
			'ri.item_type',
			'ri.item_id',
			'ri.ignored_at',
			'ri.notified_at',
			'ri.attempt_repair_at',
			'ri.item_repaired_at',
			'ri.item_deleted_at',
			'ri.created_at',
		];
	}

	private function getNewResultsSet() {
		$scanCon = $this->getScanController();
		return empty( $scanCon ) ? new ResultsSet() : $scanCon->getNewResultsSet();
	}

	public function getAdditionalWheres() :array {
		return is_array( $this->additionalWheres ) ? $this->additionalWheres : [];
	}

	public function setAdditionalWheres( array $wheres ) {
		$this->additionalWheres = $wheres;
		return $this;
	}
}