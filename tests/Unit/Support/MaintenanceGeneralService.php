<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class MaintenanceGeneralService extends UnitTestGeneral {

	public function hasCoreUpdate() :bool {
		return false;
	}

	public function getOption( $sKey, $mDefault = false, $bIgnoreWPMS = false ) {
		return $mDefault;
	}
}
