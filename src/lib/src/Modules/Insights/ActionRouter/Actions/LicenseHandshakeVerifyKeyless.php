<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

class LicenseHandshakeVerifyKeyless extends LicenseBase {

	use Traits\NonceVerifyNotRequired;
	use Traits\AuthNotRequired;

	public const SLUG = 'keyless_handshake';

	protected function exec() {
		$nonce = Services::Request()->query( 'nonce' );
		if ( !empty( $nonce ) ) {
			die( json_encode( [
				'success' => ( new HandshakingNonce() )
					->setCon( $this->getCon() )
					->verify( $nonce )
			] ) );
		}

		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}