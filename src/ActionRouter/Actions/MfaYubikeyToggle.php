<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Yubikey;

class MfaYubikeyToggle extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_yubi_toggle';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$fields = $this->fields();
		$otp = $fields->string( 'otp', '' );
		$label = $fields->string( 'label', '' );

		/** @var Yubikey $provider */
		$provider = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() )[ Yubikey::ProviderSlug() ];
		$result = $provider->toggleRegisteredYubiID( $otp, $label );

		$this->response()->setPayload( [
			'message' => $result->success ? $result->msg_text : $result->error_text,
		] )->setPayloadSuccess( (bool)( $result->success ) );
	}
}
