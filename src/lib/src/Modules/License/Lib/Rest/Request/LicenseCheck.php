<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;

class LicenseCheck extends Process {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$licHandler = $mod->getLicenseHandler();
		$licHandler->verify( true );
		return [
			'license_found' => $licHandler->hasValidWorkingLicense()
		];
	}
}