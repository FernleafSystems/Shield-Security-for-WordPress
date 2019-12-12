<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ResultsDelete
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results
 */
class ResultsDelete {

	use ScanControllerConsumer;

	/**
	 * @param Scans\Base\BaseResultsSet $oResultsToDelete
	 * @return bool
	 */
	public function delete( $oResultsToDelete ) {
		$aHashes = array_map(
			function ( $oItem ) {
				/** @var Scans\Base\BaseResultItem $oItem */
				return $oItem->hash;
			},
			$oResultsToDelete->getAllItems()
		);
		/** @var Databases\Scanner\Delete $oDel */
		$oDel = $this->getScanController()
					 ->getScanResultsDbHandler()
					 ->getQueryDeleter();
		return $oDel->filterByHashes( $aHashes )
					->query();
	}

	/**
	 * @return $this
	 */
	public function deleteAllForScan() {
		/** @var Databases\Scanner\Delete $oDel */
		$oDel = $this->getScanController()
					 ->getScanResultsDbHandler()
					 ->getQueryDeleter();
		$oDel->forScan( $this->getScanController()->getSlug() );
		return $this;
	}
}
