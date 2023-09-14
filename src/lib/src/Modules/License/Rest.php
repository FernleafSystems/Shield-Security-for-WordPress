<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

class Rest extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest {

	protected function enumRoutes() :array {
		return [
			'license_check'  => Rest\Route\LicenseCheck::class,
			'license_status' => Rest\Route\LicenseStatus::class,
		];
	}

	protected function isFeatureAvailable() :bool {
		return self::con()->caps->canRestAPILevel1();
	}
}