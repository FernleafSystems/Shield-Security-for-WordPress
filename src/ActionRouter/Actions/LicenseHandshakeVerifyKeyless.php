<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;

class LicenseHandshakeVerifyKeyless extends LicenseBase {

	use Traits\NonceVerifyNotRequired;
	use Traits\AuthNotRequired;

	public const SLUG = 'keyless_handshake';

	protected function exec() {
		$nonce = $this->action_data[ 'nonce' ] ?? '';
		if ( !empty( $nonce ) ) {
			die( \wp_json_encode( [
				'success' => ( new HandshakingNonce() )->verify( $nonce )
			] ) );
		}

		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}