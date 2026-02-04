<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

abstract class LicenseBase extends Base {

	protected function getLicenseDetails() :array {
		$details = [ false ];
		if ( self::con()->comps->license->hasValidWorkingLicense() ) {
			$details = \array_intersect_key(
				self::con()->comps->license->getLicense()->getRawData(),
				\array_flip( $this->getDefaultFilterFields() )
			);
		}
		return $details;
	}

	protected function getDefaultFilterFields() :array {
		return [
			'license',
			'item_name',
			'url',
			'customer_email',
			'expires',
			'expires_at',
			'is_trial',
			'install_id',
			'last_verified_at',
		];
	}
}