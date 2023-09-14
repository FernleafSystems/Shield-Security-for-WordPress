<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\PushSignalsFailedException;

class PushSignals extends BaseAuth {

	public const API_ACTION = 'signals';

	/**
	 * @throws PushSignalsFailedException
	 */
	public function run( array $signals ) :bool {
		$this->request_method = 'post';
		$this->params_body = \array_filter( $signals );
		$raw = $this->sendReq();
		if ( !\is_array( $raw ) || ( $raw[ 'message' ] ?? '' ) !== 'OK' ) {
			throw new PushSignalsFailedException( sprintf( 'Failed to push signals: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}
		return true;
	}
}