<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;

class LicenseHandshakeVerifyKeyless extends LicenseBase {

	use Traits\AuthNotRequired;

	public const SLUG = 'keyless_handshake';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONE,
		];
	}

	protected function exec() {
		$nonce = $this->fields()->string( 'nonce', '' );
		if ( !empty( $nonce ) ) {
			die( \wp_json_encode( [
				'success' => ( new HandshakingNonce() )->verify( $nonce )
			] ) );
		}

		$this->response()->setPayload()->setPayloadSuccess( true );
	}
}
