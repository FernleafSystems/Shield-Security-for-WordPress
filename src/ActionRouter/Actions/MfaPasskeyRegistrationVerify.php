<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;

class MfaPasskeyRegistrationVerify extends MfaUserConfigBase {

	public const SLUG = 'mfa_passkey_registration_verify';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$fields = $this->fields();
		$wanAuth = $fields->string( 'reg', '' );
		$label = $fields->string( 'label', '' );

		$available = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var ?Passkey $provider */
		$provider = $available[ Passkey::ProviderSlug() ] ?? null;

		if ( empty( $provider ) ) {
			$response = [
				'success'     => false,
				'message'     => __( "Passkey registration isn't available for this user.", 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		elseif ( empty( $wanAuth ) ) {
			$response = [
				'success'     => false,
				'message'     => __( 'Passkey registration details were missing in the request.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		else {
			$result = $provider->verifyRegistrationResponse( $wanAuth, $label );
			$response = [
				'success' => $result->success,
				'message' => $result->success ? $result->msg_text : $result->error_text,
			];
		}

		$payloadSuccess = $response[ 'success' ];
		unset( $response[ 'success' ] );

		$this->response()
			 ->setPayload( $response )
			 ->setPayloadSuccess( $payloadSuccess );
	}
}
