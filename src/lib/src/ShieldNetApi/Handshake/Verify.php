<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Handshake;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

class Verify extends Common\BaseShieldNetApi {

	const API_ACTION = 'handshake/verify';

	public function run() :bool {
		$raw = $this->sendReq();
		return is_array( $raw ) && !empty( $raw[ 'data' ][ 'success' ] );
	}
}