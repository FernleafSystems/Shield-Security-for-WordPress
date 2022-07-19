<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;

class LicenseCheck extends Base {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		try {
			$mod->getLicenseHandler()->verify( true );
		}
		catch ( \Exception $e ) {
			throw new ApiException( $e->getMessage() );
		}
		return $this->getLicenseDetails();
	}
}