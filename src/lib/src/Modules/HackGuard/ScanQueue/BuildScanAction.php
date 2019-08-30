<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class BuildScanAction
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class BuildScanAction {

	use Shield\Modules\ModConsumer;

	/**
	 * @param string $sSlug
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 * @throws \Exception
	 */
	public function build( $sSlug ) {
		$oAction = $this->getNewScanActionVO( $sSlug );

		// Build the action definition:

		$sClass = $oAction->getScanNamespace().'BuildScanAction';
		/** @var Shield\Scans\Base\BaseBuildScanAction $oBuilder */
		$oBuilder = new $sClass();
		$oBuilder->setMod( $this->getMod() )
				 ->setScanActionVO( $oAction )
				 ->build();
		return $oAction;
	}

	/**
	 * @param $sScanSlug
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 */
	private function getNewScanActionVO( $sScanSlug ) {
		$oVO = null;
		switch ( $sScanSlug ) {
			case 'apc':
				$oVO = new Shield\Scans\Apc\ScanActionVO();
				break;
			case 'mal':
				$oVO = new Shield\Scans\Mal\ScanActionVO();
				break;
			case 'ptg':
				$oVO = new Shield\Scans\Ptg\ScanActionVO();
				break;
			case 'ufc':
				$oVO = new Shield\Scans\Ufc\ScanActionVO();
				break;
			case 'wcf':
				$oVO = new Shield\Scans\Wcf\ScanActionVO();
				break;
			case 'wpv':
				$oVO = new Shield\Scans\Wpv\ScanActionVO();
				break;
		}
		$oVO->scan = $sScanSlug;
		return $oVO;
	}
}
