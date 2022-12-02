<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Sms;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

class GetAvailableCountries extends Common\BaseShieldNetApi {

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