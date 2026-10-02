<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\NonceVerifyRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\CentralDisabledException;

class ShieldCentralCreatePairingToken extends BaseAction {

	use NonceVerifyRequired;

	public const SLUG = 'shield_central_create_pairing_token';

	/**
	 * @throws \Exception
	 */
	protected function exec() {
		try {
			$issued = self::con()->comps->central->issuePairingToken();
		}
		catch ( CentralDisabledException $e ) {
			throw new ActionException( $e->getMessage(), 0, $e );
		}
		$this->response()
			 ->setPayload( $issued )
			 ->setPayloadSuccess( true );
	}

	protected function verifyNonce() :bool {
		return ActionNonce::Verify(
			static::class,
			(string)( $this->action_data[ ActionData::FIELD_NONCE ] ?? '' )
		);
	}
}
