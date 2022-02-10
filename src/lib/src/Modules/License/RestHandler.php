<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Rest\Route\LicenseCheck;

class RestHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\RestHandler {

	protected function enumRoutes() :array {
		return [
			'license_check' => LicenseCheck::class,
		];
	}
}