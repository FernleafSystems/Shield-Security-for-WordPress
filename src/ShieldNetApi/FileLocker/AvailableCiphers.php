<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker;

class AvailableCiphers extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2 {

	public const API_ACTION = 'filelocker/ciphers';

	public function retrieve() :array {
		$raw = $this->sendReq();
		return \is_array( $raw ) ? ( $raw[ 'ciphers' ] ?? [] ) : [];
	}
}