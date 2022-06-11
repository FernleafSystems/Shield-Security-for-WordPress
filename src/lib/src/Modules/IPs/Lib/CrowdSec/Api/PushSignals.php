<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\FailedToPushSignalsException;

class PushSignals extends BaseAuth {

	const API_ACTION = 'signals';

	/**
	 * @throws FailedToPushSignalsException
	 */
	public function run( array $signals ) :array {
		$this->request_method = 'post';
		$this->params_body = $signals;
		$raw = $this->sendReq();
		if ( !is_array( $raw ) ) {
			throw new FailedToPushSignalsException( sprintf( 'Failed to push signals: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}
		return $raw;
	}
}