<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScanActionFromSlug
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan
 */
class ScanActionFromSlug {

	public static function GetAction( string $slug ):Shield\Scans\Base\BaseScanActionVO {
		$VO = null;
		switch ( $slug ) {
			case 'apc':
				$VO = new Shield\Scans\Apc\ScanActionVO();
				break;
			case 'mal':
				$VO = new Shield\Scans\Mal\ScanActionVO();
				break;
			case 'ptg':
				$VO = new Shield\Scans\Ptg\ScanActionVO();
				break;
			case 'ufc':
				$VO = new Shield\Scans\Ufc\ScanActionVO();
				break;
			case 'wcf':
				$VO = new Shield\Scans\Wcf\ScanActionVO();
				break;
			case 'wpv':
				$VO = new Shield\Scans\Wpv\ScanActionVO();
				break;
		}
		$VO->scan = $slug;
		return $VO;
	}
}
