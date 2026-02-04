<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

class LicenseStatus extends LicenseBase {

	protected function process() :array {
		return $this->getLicenseDetails();
	}
}