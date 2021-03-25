<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi;

class GetPublicKey extends BaseShieldNetApi {

	const API_ACTION = 'filelocker/public_key';

	/**
	 * @return array|null
	 */
	public function retrieve() {
		$key = null;
		$raw = $this->sendReq();
		if ( is_array( $raw ) && !empty( $raw[ 'data' ][ 'key_id' ] ) ) {
			$key[ $raw[ 'data' ][ 'key_id' ] ] = $raw[ 'data' ][ 'pub_key' ];
		}
		return $key;
	}
}