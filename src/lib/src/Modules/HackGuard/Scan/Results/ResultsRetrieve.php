<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet;
use FernleafSystems\Wordpress\Services\Services;

class ResultsRetrieve {

	use ModConsumer;
	use ScanControllerConsumer;

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
				sprintf( "`sr`.`id`=%s", $scanResultID )
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
						sprintf( "`sr`.`id` IN (%s)", implode( ',', $IDs ) )
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
		$results = [];

		$latestID = $this->getLatestScanID();
		if ( $latestID >= 0 ) {
			$wheres = array_filter( [
				sprintf( "`sr`.`scan_ref`=%s", $latestID ),
				"`ri`.`attempt_repair_at`=0",
				"`ri`.`item_repaired_at`=0",
				"`ri`.`item_deleted_at`=0",
				"`ri`.ignored_at=0"
			] );
			$raw = Services::WpDb()->selectCustom(
				sprintf( $this->getBaseQuery(),
					implode( ', ', $this->standardSelectFields() ),
					implode( ' AND ', $wheres )
				)
			);
			if ( !empty( $raw ) ) {
				$results = $raw;
			}
		}

		return $this->convertToResultsSet( $results );
	}

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function retrieve( bool $includeIgnored = true ) {
		$results = [];
		if ( !$this->getScanController()->isRestricted() ) {

			$latestID = $this->getLatestScanID();
			if ( $latestID >= 0 ) {
				$wheres = array_filter( [
					sprintf( "`sr`.`scan_ref`=%s", $latestID ),
					$includeIgnored ? '' : "`ri`.ignored_at = 0",
					"`ri`.`deleted_at`=0"
				] );
				$raw = Services::WpDb()->selectCustom(
					sprintf( $this->getBaseQuery(),
						implode( ',', $this->standardSelectFields() ),
						implode( ' AND ', $wheres )
					)
				);
				if ( !empty( $raw ) ) {
					$results = $raw;
				}
			}
		}

		return $this->convertToResultsSet( $results );
	}

	public function count( bool $includeIgnored = true ) :int {
		$count = 0;
		if ( !$this->getScanController()->isRestricted() ) {
			$latestID = $this->getLatestScanID();
			if ( $latestID >= 0 ) {
				$wheres = array_filter( [
					sprintf( "`sr`.`scan_ref`=%s", $latestID ),
					$includeIgnored ? '' : "`ri`.ignored_at = 0",
					"`ri`.`deleted_at`=0"
				] );
				$count = (int)Services::WpDb()->getVar(
					sprintf( $this->getBaseQuery(),
						'COUNT(*)',
						implode( ' AND ', $wheres )
					)
				);
			}
		}
		return $count;
	}

	/**
	 * @param array[] $results
	 * @return ResultsSet|mixed
	 */
	protected function convertToResultsSet( array $results ) {
		$scanCon = $this->getScanController();
		$resultsSet = $scanCon->getNewResultsSet();
		foreach ( $results as $result ) {
			$vo = ( new ScanResultVO() )->applyFromArray( $result );
			$item = $scanCon->getNewResultItem()->applyFromArray( $vo->meta[ $scanCon->getSlug() ] );
			$item->VO = $vo;
			$resultsSet->addItem( $item );
		}
		return $resultsSet;
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

	private function standardSelectFields() :array {
		return [
			'scans.scan',
			'scans.id as scan_id',
			'sr.id as scanresult_id',
			'ri.id as resultitem_id',
			'ri.hash',
			'ri.item_type',
			'ri.item_id',
			'ri.meta',
			'ri.ignored_at',
			'ri.notified_at',
			'ri.attempt_repair_at',
			'ri.item_repaired_at',
			'ri.item_deleted_at',
			'ri.created_at',
		];
	}
}