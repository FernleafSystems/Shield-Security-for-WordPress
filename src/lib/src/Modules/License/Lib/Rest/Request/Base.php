<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;

abstract class Base extends Process {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$licHandler = $mod->getLicenseHandler();
		return [
			'license' => $licHandler->hasValidWorkingLicense() ? $this->getLicenseDetails() : false
		];
	}

	protected function getLicenseDetails() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getLicenseHandler()->getLicense()->getRawData();
	}
}