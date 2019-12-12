<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanActionFromSlug;

/**
 * Class BuildScanAction
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class BuildScanAction {

	use Shield\Modules\ModConsumer;

	/**
	 * @param string $sSlug
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 * @throws \Exception
	 */
	public function build( $sSlug ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$oAction = $oMod->getScanCon( $sSlug )->getScanActionVO();

		// Build the action definition:

		$sClass = $oAction->getScanNamespace().'BuildScanAction';
		/** @var Shield\Scans\Base\BaseBuildScanAction $oBuilder */
		$oBuilder = new $sClass();
		$oBuilder->setMod( $oMod )
				 ->setScanActionVO( $oAction )
				 ->build();
		return $oAction;
	}
}
