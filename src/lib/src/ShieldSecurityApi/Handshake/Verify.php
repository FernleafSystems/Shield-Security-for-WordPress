<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\Handshake;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\Common;

class Verify extends Common\BaseShieldSecurityApi {

	const API_ACTION = 'handshake/verify';

	/**
	 * @return bool
	 */
	public function run() {
		$aRaw = $this->sendReq();
		return is_array( $aRaw ) && !empty( $aRaw[ 'data' ][ 'success' ] );
	}
}