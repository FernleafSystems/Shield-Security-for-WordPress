<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;

class LicenseCheck extends LicenseBase {

	protected function process() :array {
		try {
			self::con()->comps->license->verify( true );
		}
		catch ( \Exception $e ) {
			throw new ApiException( $e->getMessage() );
		}
		return $this->getLicenseDetails();
	}
}