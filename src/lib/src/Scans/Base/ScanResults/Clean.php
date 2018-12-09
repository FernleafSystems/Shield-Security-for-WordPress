<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ScanResults;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class Clean
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ScanResults
 */
class Clean {

	use Shield\Databases\Base\HandlerConsumer,
		Shield\Scans\Base\ScannerProfileConsumer;

	/**
	 * @var Shield\Scans\Base\BaseResultsSet
	 */
	private $oWorkingResultsSet;

	/**
	 * @return $this
	 */
	public function deleteAllForScan() {
		$sScan = $this->getScannerProfile()->scan_slug;
		if ( !empty( $sScan ) ) {
			/** @var Shield\Databases\Scanner\Delete $oDel */
			$oDel = $this->getDbHandler()->getQueryDeleter();
			$oDel->filterByScan( $sScan )->query();
		}
		return $this;
	}

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