<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class Clean
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ScanResults
 */
class Clean {

	use Shield\Databases\Base\HandlerConsumer;
	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @var Shield\Scans\Base\BaseResultsSet
	 */
	private $oWorkingResultsSet;

	/**
	 * @return $this
	 */
	public function deleteAllForScan() {
		$sScan = $this->getScanActionVO()->scan;
		if ( !empty( $sScan ) ) {
			/** @var Shield\Databases\Scanner\Delete $oDel */
			$oDel = $this->getDbHandler()->getQueryDeleter();
			$oDel->forScan( $sScan );
		}
		return $this;
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oRS
	 * @return $this
	 */
	public function deleteResults( $oRS ) {
		$aHashes = array_map(
			function ( $oItem ) {
				/** @var Shield\Scans\Base\BaseResultItem $oItem */
				return $oItem->hash;
			},
			$oRS->getAllItems()
		);
		/** @var Shield\Databases\Scanner\Delete $oDel */
		$oDel = $this->getDbHandler()->getQueryDeleter();
		$oDel->filterByHashes( $aHashes )
			 ->query();
		return $this;
	}
}