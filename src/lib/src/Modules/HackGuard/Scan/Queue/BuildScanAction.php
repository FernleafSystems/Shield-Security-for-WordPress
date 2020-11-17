<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;

/**
 * Class BuildScanAction
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class BuildScanAction {

	use Shield\Modules\ModConsumer;

	/**
	 * @param string $slug
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 * @throws \Exception
	 */
	public function build( $slug ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$oAction = $mod->getScanCon( $slug )->getScanActionVO();

		// Build the action definition:

		$sClass = $oAction->getScanNamespace().'BuildScanAction';
		/** @var Shield\Scans\Base\BaseBuildScanAction $oBuilder */
		$oBuilder = new $sClass();
		$oBuilder->setMod( $mod )
				 ->setScanActionVO( $oAction )
				 ->build();
		return $oAction;
	}
}
