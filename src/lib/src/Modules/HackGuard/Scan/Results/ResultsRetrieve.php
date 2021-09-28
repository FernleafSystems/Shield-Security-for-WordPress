<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet;
use FernleafSystems\Wordpress\Services\Services;

class ResultsRetrieve {

	use ScanControllerConsumer;

	/**
	 * @param int $scanResultID
	 * @return Scans\Base\ResultItem
	 * @throws \Exception
	 */
	public function byID( int $scanResultID ) {
		$rawResults = [];
		if ( !$this->getScanController()->isRestricted() ) {
			$raw = Services::WpDb()->selectCustom(
				sprintf( $this->getBaseQuery(),
					implode( ',', $this->standardSelectFields() ),
					sprintf( "`sr`.`id`=%s", $scanResultID )
				)
			);
			if ( !empty( $raw ) ) {
				$rawResults = $raw;
			}
		}

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
	public function retrieve( bool $includeIgnored = true ) {
		$results = [];
		if ( !$this->getScanController()->isRestricted() ) {
			$latestID = $this->getLatestScanID();
			if ( $latestID >= 0 ) {
				$wheres = array_filter( [
					sprintf( "`sr`.`scan_ref`=%s", $latestID ),
					$includeIgnored ? '' : "`ri`.ignored_at = 0"
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
					$includeIgnored ? '' : "`ri`.ignored_at = 0"
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
			$item = $scanCon->getNewResultItem()->applyFromArray( $vo->meta );
			$item->VO = $vo;
			$resultsSet->addItem( $item );
		}
		return $resultsSet;
	}

	private function getLatestScanID() :int {
		$scanCon = $this->getScanController();
		/** @var ModCon $mod */
		$mod = $scanCon->getMod();
		/** @var ScansDB\Ops\Select $scansSelector */
		$scansSelector = $mod->getDbH_Scans()->getQuerySelector();
		$latest = $scansSelector->getLatestForScan( $scanCon->getSlug() );
		return empty( $latest ) ? -1 : $latest->id;
	}

	private function getBaseQuery() :string {
		/** @var ModCon $mod */
		$mod = $this->getScanController()->getMod();
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
			'scans.id as scan_id',
			'scans.scan',
			'sr.id as scanresult_id',
			'ri.id as resultitem_id',
			'ri.hash',
			'ri.item_type',
			'ri.item_id',
			'ri.meta',
			'ri.created_at',
		];
	}
}
