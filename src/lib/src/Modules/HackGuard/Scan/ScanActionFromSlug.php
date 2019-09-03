<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScanActionFromSlug
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class ScanActionFromSlug {

	/**
	 * @param string $sScanSlug
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 */
	public function getAction( $sScanSlug ) {
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
