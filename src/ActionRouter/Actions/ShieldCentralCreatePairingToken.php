<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\NonceVerifyRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\PairingTokenIssuer;

class ShieldCentralCreatePairingToken extends BaseAction {

	use NonceVerifyRequired;

	public const SLUG = 'shield_central_create_pairing_token';

	/**
	 * @throws \Exception
	 */
	protected function exec() {
		$this->response()
			 ->setPayload( ( new PairingTokenIssuer() )->issue() )
			 ->setPayloadSuccess( true );
	}

	protected function verifyNonce() :bool {
		return ActionNonce::Verify(
			static::class,
			(string)( $this->action_data[ ActionData::FIELD_NONCE ] ?? '' )
		);
	}
}
