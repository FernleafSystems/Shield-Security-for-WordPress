<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Request;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process {

	protected function newReqVO() {
		return new RequestVO();
	}

	protected function getLicenseDetails() :array {
		/** @var RequestVO $req */
		$req = $this->getRequestVO();
		$licHandler = self::con()->comps->license;

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