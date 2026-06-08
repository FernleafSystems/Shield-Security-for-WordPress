<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2;

abstract class BaseIPLookup extends BaseShieldNetApiV2 {

	use IpAddressConsumer;

	public function retrieve() :array {
		$this->shield_net_params_required = false;
		$raw = $this->sendReq();
		return \is_array( $raw ) && ( $raw[ 'error_code' ] ?? null ) === 0 ? $raw : [];
	}

	protected function getApiRequestUrl() :string {
		return \sprintf( '%s/%s', parent::getApiRequestUrl(), $this->getIP() );
	}
}
