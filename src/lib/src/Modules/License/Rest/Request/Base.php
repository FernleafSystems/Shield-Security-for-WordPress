<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;

abstract class Base extends Process {

	protected function newReqVO() {
		return new RequestVO();
	}

	protected function getLicenseDetails() :array {
		/** @var ModCon $mod */
		$mod = $this->mod();
		/** @var RequestVO $req */
		$req = $this->getRequestVO();
		$licHandler = $mod->getLicenseHandler();

		$details = [ false ];
		if ( $licHandler->hasValidWorkingLicense() ) {
			$lic = $licHandler->getLicense()->getRawData();
			$details = [];
			foreach ( \array_keys( $req->filter_fields ) as $field ) {
				$details[ $field ] = $lic[ $field ];
			}
		}

		return $details;
	}
}