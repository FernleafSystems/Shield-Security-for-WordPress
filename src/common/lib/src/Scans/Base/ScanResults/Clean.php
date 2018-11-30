<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ScanResults;

use FernleafSystems\Wordpress\Plugin\Shield;

class Clean {

	use Shield\Databases\Base\HandlerConsumer;

	/**
	 * @var Shield\Scans\Base\BaseResultsSet
	 */
	private $oWorkingResultsSet;

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oRS
	 * @return $this
	 */
	public function deleteResults( $oRS ) {
		/** @var Shield\Databases\Scanner\Delete $oDel */
		$oDel = $this->getDbHandler()->getQueryDeleter();
		foreach ( $oRS->getAllItems() as $oItem ) {
			$oDel->reset()
				 ->filterByHash( $oItem->hash )
				 ->query();
		}
		return $this;
	}

	/**
	 * @return Shield\Scans\Base\BaseResultsSet
	 */
	public function getWorkingResultsSet() {
		return $this->oWorkingResultsSet;
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oWorkingResultsSet
	 * @return $this
	 */
	public function setWorkingResultsSet( $oWorkingResultsSet ) {
		$this->oWorkingResultsSet = $oWorkingResultsSet;
		return $this;
	}
}