<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

class BuildForScans extends BuildBase {

	public function build() :array {
		$con = self::con();
		$data = [];

		foreach ( $con->getModule_HackGuard()->getScansCon()->getAllScanCons() as $scanCon ) {
			$res$scanCon->getAllResults()
		}

		return $data;
	}
}