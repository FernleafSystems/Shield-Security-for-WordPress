<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Sms;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaSmsVerify extends MfaBase {

	public const SLUG = 'mfa_profile_sms_verify';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$req = Services::Request();

		$available = $mod->getMfaController()->getProvidersAvailableToUser( Services::WpUsers()->getCurrentWpUser() );
		/** @var Sms $provider */
		$provider = $available[ Sms::ProviderSlug() ];

		$countryCode = $req->post( 'sms_country' );
		$phoneNum = $req->post( 'sms_phone' );
		$verifyCode = $req->post( 'sms_code' );

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