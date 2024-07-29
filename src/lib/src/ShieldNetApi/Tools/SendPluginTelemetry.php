<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Tools;

class SendPluginTelemetry extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2 {

	public const API_ACTION = 'telemetry/receive';

	public function send( array $data ) :bool {
		$this->shield_net_params_required = false;
		$this->request_method = 'post';
		$this->params_body = [
			'telemetry' => $data,
		];
		return ( $this->sendReq()[ 'error_code' ] ?? 1 ) === 0;
	}
}