<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Sms;

class GetAvailableCountries extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi {

	public const API_ACTION = 'sms/countries';

	public function run() :array {
		$this->shield_net_params_required = false;
		$raw = $this->sendReq();
		$countries = [];
		if ( empty( $raw[ 'error' ] ) && !empty( $raw[ 'data' ] ) ) {
			$countries = $raw[ 'data' ];
		}
		return $countries;
	}
}