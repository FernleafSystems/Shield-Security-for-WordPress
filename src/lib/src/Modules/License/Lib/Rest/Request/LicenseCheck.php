<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;

class LicenseCheck extends Base {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getLicenseHandler()->verify( true );
		return $this->getLicenseDetails();
	}
}