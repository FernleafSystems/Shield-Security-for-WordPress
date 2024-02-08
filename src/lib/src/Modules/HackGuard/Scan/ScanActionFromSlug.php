<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\{
	Afs,
	Apc,
	Wpv
};

class ScanActionFromSlug {

	/**
	 * @return Afs\ScanActionVO|Apc\ScanActionVO|Wpv\ScanActionVO
	 */
	public static function GetAction( string $slug ) {
		$VO = null;
		switch ( $slug ) {
			case Controller\Afs::SCAN_SLUG:
				$VO = new Afs\ScanActionVO();
				break;
			case Controller\Apc::SCAN_SLUG:
				$VO = new Apc\ScanActionVO();
				break;
			case Controller\Wpv::SCAN_SLUG:
				$VO = new Wpv\ScanActionVO();
				break;
		}
		$VO->scan = $slug;
		return $VO;
	}
}
