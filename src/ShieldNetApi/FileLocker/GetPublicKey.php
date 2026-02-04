<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker;

class GetPublicKey extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2 {

	public const API_ACTION = 'filelocker/public_key';

	public function retrieve() :?array {
		$key = null;
		$raw = $this->sendReq();
		if ( \is_array( $raw ) && !empty( $raw[ 'key_id' ] ) ) {
			$key = [
				$raw[ 'key_id' ] => $raw[ 'pub_key' ]
			];
		}
		return $key;
	}
}