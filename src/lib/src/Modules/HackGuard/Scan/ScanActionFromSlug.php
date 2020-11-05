<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ScanActionFromSlug
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan
 */
class ScanActionFromSlug {

	public static function GetAction( string $slug ) :Scans\Base\BaseScanActionVO {
		$VO = null;
		switch ( $slug ) {
			case 'apc':
				$VO = new Scans\Apc\ScanActionVO();
				break;
			case 'mal':
				$VO = new Scans\Mal\ScanActionVO();
				break;
			case 'ptg':
				$VO = new Scans\Ptg\ScanActionVO();
				break;
			case 'ufc':
				$VO = new Scans\Ufc\ScanActionVO();
				break;
			case 'wcf':
				$VO = new Scans\Wcf\ScanActionVO();
				break;
			case 'wpv':
				$VO = new Scans\Wpv\ScanActionVO();
				break;
		}
		$VO->scan = $slug;
		return $VO;
	}
}
