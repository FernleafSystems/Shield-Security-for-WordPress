<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\Common\BaseShieldSecurityApi;

class GetPublicKey extends BaseShieldSecurityApi {

	const API_ACTION = 'filelocker/public_key';

	/**
	 * @return array|null
	 */
	public function retrieve() {
		$aKey = null;
		$aRaw = $this->sendReq();
		if ( is_array( $aRaw ) && !empty( $aRaw[ 'data' ][ 'key_id' ] ) ) {
			$aKey[ $aRaw[ 'data' ][ 'key_id' ] ] = $aRaw[ 'data' ][ 'pub_key' ];
		}
		return $aKey;
	}
}