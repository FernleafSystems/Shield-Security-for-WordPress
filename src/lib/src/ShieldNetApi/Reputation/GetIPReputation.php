<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

class GetIPReputation extends Common\BaseShieldNetApi {

	const API_ACTION = 'ip/reputation';
	use IpAddressConsumer;

	public function retrieve() :array {
		$this->shield_net_params_required = false;
		$raw = $this->sendReq();
		return ( is_array( $raw ) && empty( $raw[ 'error' ] ) ) ? $raw[ 'data' ] : [];
	}

	protected function getApiRequestUrl() :string {
		return sprintf( '%s/%s', parent::getApiRequestUrl(), $this->getIP() );
	}
}