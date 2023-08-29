<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;
use FernleafSystems\Wordpress\Services\Services;

class MfaGoogleAuthToggle extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_toggle_ga';

	protected function exec() {
		$available = self::con()
						 ->getModule_LoginGuard()
						 ->getMfaController()
						 ->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var GoogleAuth $provider */
		$provider = $available[ GoogleAuth::ProviderSlug() ];

		$otp = Services::Request()->post( 'ga_otp', '' );
		$result = empty( $otp ) ? $provider->removeGA() : $provider->activateGA( $otp );

		$this->response()->action_response_data = [
			'success'     => $result->success,
			'message'     => $result->success ? $result->msg_text : $result->error_text,
			'page_reload' => true
		];
	}
}