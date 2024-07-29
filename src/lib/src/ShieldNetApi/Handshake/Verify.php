<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Handshake;

class Verify extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2 {

	public const API_ACTION = 'handshake/verify';

	public function run() :bool {
		$raw = $this->sendReq();
		return \is_array( $raw ) && !empty( $raw[ 'success' ] );
	}
}