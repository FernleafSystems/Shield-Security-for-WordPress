<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;

class LicenseCheck extends Base {

	protected function process() :array {
		try {
			self::con()
				->getModule_License()
				->getLicenseHandler()
				->verify( true );
		}
		catch ( \Exception $e ) {
			throw new ApiException( $e->getMessage() );
		}
		return $this->getLicenseDetails();
	}
}