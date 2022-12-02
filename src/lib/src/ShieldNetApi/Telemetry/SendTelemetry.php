<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Telemetry;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

class SendTelemetry extends Common\BaseShieldNetApi {

	public const API_ACTION = 'telemetry/receive';

	public function sendData( array $data ) :bool {
		$this->shield_net_params_required = false;
		$this->request_method = 'post';
		$this->params_body = [
			'telemetry' => $data,
		];
		$raw = $this->sendReq();
		return !( !is_array( $raw ) || $raw[ 'error' ] ?? false );
	}
}