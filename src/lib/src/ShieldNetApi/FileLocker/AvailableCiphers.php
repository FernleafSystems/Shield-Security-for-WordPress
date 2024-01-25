<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2;

class AvailableCiphers extends BaseShieldNetApiV2 {

	public const API_ACTION = 'filelocker/ciphers';

	public function retrieve() :array {
		$raw = $this->sendReq();
		return \is_array( $raw ) ? ( $raw[ 'ciphers' ] ?? [] ) : [];
	}
}