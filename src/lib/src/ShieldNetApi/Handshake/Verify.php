<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Handshake;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

class Verify extends Common\BaseShieldNetApi {

	const API_ACTION = 'handshake/verify';

	/**
	 * @return bool
	 */
	public function run() {
		$aRaw = $this->sendReq();
		return is_array( $aRaw ) && !empty( $aRaw[ 'data' ][ 'success' ] );
	}
}