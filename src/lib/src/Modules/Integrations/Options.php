<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function isEnabledMainWP() :bool {
		return $this->isOpt( 'enable_mainwp', 'Y' );
	}

	public function getUserFormProviders() :array {
		$userForms = $this->getOpt( 'user_form_providers' );
		if ( !is_array( $userForms ) ) {
			$userForms = [];
		}
		if ( !in_array( 'wordpress', $userForms ) ) {
			$userForms[] = 'wordpress';
			$this->setOpt( 'user_form_providers', $userForms );
		}
		return array_unique( $userForms );
	}
}