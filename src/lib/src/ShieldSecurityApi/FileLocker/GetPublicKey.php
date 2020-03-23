<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\FileLocker;

class GetPublicKey extends BaseFileLockerApi {

	const API_ACTION = 'public_key';

	/**
	 * @return array|null
	 */
	public function retrieve() {
		$aKey = null;
		$aRaw = $this->sendReq();
		if ( is_array( $aRaw ) && !empty( $aRaw[ 'key' ] ) ) {
			$aKey[ $aRaw[ 'key' ][ 'key_id' ] ] = $aRaw[ 'key' ][ 'pub_key' ];
		}
		return $aKey;
	}
}