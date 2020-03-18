<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileProtect;

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
		$aScans = [];
		/** @var EntryVO $oEntry */
		foreach ( $aResults as $oEntry ) {
			$aScans[ $oEntry->scan ] = 1;
		}
		return array_keys( $aScans );
	}

	/**
	 * @param string $sScan
	 * @return int
	 */
	public function countForScan( $sScan ) {
		return $this->reset()
					->filterByScan( $sScan )
					->count();
	}
}