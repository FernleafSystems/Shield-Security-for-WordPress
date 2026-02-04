<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation;

class SendIPReputation extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi {

	public const API_ACTION = 'ip/reputation/receive';

	public function send( array $signalsData ) :bool {
		$this->request_method = 'post';
		$this->shield_net_params_required = false;
		$this->params_body = [
			'ip_signals' => $signalsData,
		];
		$raw = $this->sendReq();
		return \is_array( $raw ) && empty( $raw[ 'error' ] );
	}
}