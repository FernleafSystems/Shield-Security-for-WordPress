<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

class GetIPReputation extends Common\BaseShieldNetApiV2 {

	const API_ACTION = 'ip/reputation';
	use IpAddressConsumer;

	public function retrieve() :array {
		$this->shield_net_params_required = false;
		$raw = $this->sendReq();
		return ( is_array( $raw ) && $raw[ 'error_code' ] === 0 ) ? $raw : [];
	}

	protected function getApiRequestUrl() :string {
		return sprintf( '%s/%s', parent::getApiRequestUrl(), $this->getIP() );
	}
}