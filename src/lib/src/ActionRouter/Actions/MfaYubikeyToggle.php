<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Yubikey;
use FernleafSystems\Wordpress\Services\Services;

class MfaYubikeyToggle extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_yubi_toggle';

	protected function exec() {
		/** @var Yubikey $provider */
		$provider = $this->getCon()
						 ->getModule_LoginGuard()
						 ->getMfaController()
						 ->getProvidersAvailableToUser( $this->getActiveWPUser() )[ Yubikey::ProviderSlug() ];
		$result = $provider->toggleRegisteredYubiID( (string)Services::Request()->post( 'otp', '' ) );

		$this->response()->action_response_data = [
			'success'     => $result->success,
			'message'     => $result->success ? $result->msg_text : $result->error_text,
			'page_reload' => true
		];
	}
}