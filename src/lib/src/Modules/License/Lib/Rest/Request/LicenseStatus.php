<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Rest\Request;

class LicenseStatus extends Base {

	protected function process() :array {
		return $this->getLicenseDetails();
	}
}