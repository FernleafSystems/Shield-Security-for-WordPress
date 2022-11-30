<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Sms;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaSmsAdd extends MfaBase {

	public const SLUG = 'mfa_profile_sms_add';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$req = Services::Request();
		/** @var Sms $provider */
		$provider = $mod->getMfaController()->getProviders()[ Sms::SLUG ];

		$countryCode = $req->post( 'sms_country' );
		$phoneNum = $req->post( 'sms_phone' );

		$response = [
			'success'     => false,
			'message'     => __( 'Either the country code or phone number were missing.', 'wp-simple-firewall' ),
			'page_reload' => true
		];

		if ( empty( $countryCode ) ) {
			$response[ 'message' ] = __( 'The country code was missing.', 'wp-simple-firewall' );
		}
		elseif ( empty( $phoneNum ) ) {
			$response[ 'message' ] = __( 'The phone number was missing.', 'wp-simple-firewall' );
		}
		else {
			$user = Services::WpUsers()->getCurrentWpUser();
			try {
				$response = [
					'success'     => true,
					'message'     => __( 'Please confirm the 6-digit code sent to your phone.', 'wp-simple-firewall' ),
					'code'        => $provider->setUser( $user )
											  ->addProvisionalRegistration( $countryCode, $phoneNum ),
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