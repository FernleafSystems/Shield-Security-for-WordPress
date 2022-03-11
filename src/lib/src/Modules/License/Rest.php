<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Rest\Route\{
	LicenseCheck,
	LicenseStatus
};

class Rest extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest {

	protected function enumRoutes() :array {
		return [
			'license_check'  => LicenseCheck::class,
			'license_status' => LicenseStatus::class,
		];
	}
}