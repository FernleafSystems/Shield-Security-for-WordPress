<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Sms;

class MfaSmsVerify extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_sms_verify';

	protected function exec() {
		$available = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var Sms $provider */
		$provider = $available[ Sms::ProviderSlug() ];

		$countryCode = $this->action_data[ 'sms_country' ] ?? '';
		$phoneNum = $this->action_data[ 'sms_phone' ] ?? '';
		$verifyCode = $this->action_data[ 'sms_code' ] ?? '';

		$response = [
			'success'     => false,
			'message'     => __( 'SMS Verification Failed.', 'wp-simple-firewall' ),
			'page_reload' => true
		];

		if ( empty( $verifyCode ) ) {
			$response[ 'message' ] = __( 'The code provided was empty.', 'wp-simple-firewall' );
		}
		elseif ( empty( $countryCode ) || empty( $phoneNum ) ) {
			$response[ 'message' ] = __( 'The data provided was inconsistent.', 'wp-simple-firewall' );
		}
		else {
			try {
				$response = [
					'success'     => true,
					'message'     => __( 'Phone verified and registered successfully for SMS Two-Factor Authentication.', 'wp-simple-firewall' ),
					'code'        => $provider->verifyProvisionalRegistration( $countryCode, $phoneNum, $verifyCode ),
					'page_reload' => false
				];
			}
			catch ( \Exception $e ) {
				$response = [
					'success'     => false,
					'message'     => esc_html( $e->getMessage() ),
					'page_reload' => false
				];
			}
		}

		$this->response()->action_response_data = $response;
	}
}