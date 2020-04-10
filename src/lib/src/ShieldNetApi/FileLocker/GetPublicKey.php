<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi;

class GetPublicKey extends BaseShieldNetApi {

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