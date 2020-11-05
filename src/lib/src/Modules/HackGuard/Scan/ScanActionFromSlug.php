<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanActionFromSlug {

	public static function GetAction( string $slug ) :Scans\Base\BaseScanActionVO {
		$VO = null;
		switch ( $slug ) {
			case Controller\Apc::SCAN_SLUG:
				$VO = new Scans\Apc\ScanActionVO();
				break;
			case Controller\Mal::SCAN_SLUG:
				$VO = new Scans\Mal\ScanActionVO();
				break;
			case Controller\Ptg::SCAN_SLUG:
				$VO = new Scans\Ptg\ScanActionVO();
				break;
			case Controller\Ufc::SCAN_SLUG:
				$VO = new Scans\Ufc\ScanActionVO();
				break;
			case Controller\Wcf::SCAN_SLUG:
				$VO = new Scans\Wcf\ScanActionVO();
				break;
			case Controller\Wpv::SCAN_SLUG:
				$VO = new Scans\Wpv\ScanActionVO();
				break;
		}
		$VO->scan = $slug;
		return $VO;
	}
}
