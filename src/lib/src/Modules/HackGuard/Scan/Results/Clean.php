<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class Clean
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ScanResults
 */
class Clean {

	use Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;

	/**
	 * @return $this
	 */
	public function removeStaleResults() {
		$sScan = $this->getScanController()->getSlug();
		if ( !empty( $sScan ) ) {
			/** @var Shield\Databases\Scanner\Delete $oDel */
			$oDel = $this->getDbHandler()->getQueryDeleter();
			$oDel->forScan( $sScan );
		}
		return $this;
	}
}