<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;

	/**
	 * @return string|null
	 */
	public function getCurrentScan() {
		return $this->reset()
					->setResultsAsVo( true )
					->setColumnsToSelect( [ 'scan' ] )
					->filterByStarted()
					->filterByNotFinished()
					->queryVar();
	}

	/**
	 * @return string[]
	 */
	public function getInitiatedScans() {
		return $this->getDistinctForColumn( 'scan' );
	}

	/**
	 * @return array[]
	 */
	public function getUnfinishedScans() {
		$aResults = $this->reset()
						 ->setResultsAsVo( true )
						 ->setColumnsToSelect( [ 'scan' ] )
						 ->filterByNotFinished()
						 ->query();
		$scans = [];
		/** @var EntryVO $entry */
		foreach ( $aResults as $entry ) {
			$scans[ $entry->scan ] = 1;
		}
		return array_keys( $scans );
	}

	public function countForScan( string $scan ) :int {
		return $this->reset()
					->filterByScan( $scan )
					->count();
	}
}