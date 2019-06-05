<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use ReCaptcha\ReCaptcha;

class TestRequest {

	use ModConsumer;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function test() {
		/** @var \ICWP_WPSF_FeatureHandler_BaseWpsf $oMod */
		$oMod = $this->getMod();

		$sCaptchaResponse = Services::Request()->post( 'g-recaptcha-response' );

		if ( empty( $sCaptchaResponse ) ) {
			throw new \Exception( __( 'Whoops.', 'wp-simple-firewall' ).' '.__( 'Google reCAPTCHA was not submitted.', 'wp-simple-firewall' ), 1 );
		}
		else {
			$oResponse = ( new ReCaptcha( $oMod->getGoogleRecaptchaSecretKey(), new WordpressPost() ) )
				->verify( $sCaptchaResponse, Services::IP()->getRequestIp() );
			if ( empty( $oResponse ) || !$oResponse->isSuccess() ) {
				$aMsg = [
					__( 'Whoops.', 'wp-simple-firewall' ),
					__( 'Google reCAPTCHA verification failed.', 'wp-simple-firewall' ),
					Services::WpGeneral()->isAjax() ?
						__( 'Maybe refresh the page and try again.', 'wp-simple-firewall' ) : ''
				];
				throw new \Exception( implode( ' ', $aMsg ), 2 );
			}
		}
		return true;
	}
}