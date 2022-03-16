<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Request;

class LicenseStatus extends Base {

	protected function getRequestProcessorClass() :string {
		return Request\LicenseStatus::class;
	}
}