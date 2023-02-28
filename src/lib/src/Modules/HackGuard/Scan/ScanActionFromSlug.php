<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanActionFromSlug {

	/**
	 * @return Scans\Afs\ScanActionVO|Scans\Apc\ScanActionVO|Scans\Wpv\ScanActionVO
	 */
	public static function GetAction( string $slug ) {
		$VO = null;
		switch ( $slug ) {
			case Controller\Afs::SCAN_SLUG:
				$VO = new Scans\Afs\ScanActionVO();
				break;
			case Controller\Apc::SCAN_SLUG:
				$VO = new Scans\Apc\ScanActionVO();
				break;
			case Controller\Wpv::SCAN_SLUG:
				$VO = new Scans\Wpv\ScanActionVO();
				break;
		}
		$VO->scan = $slug;
		return $VO;
	}
}
