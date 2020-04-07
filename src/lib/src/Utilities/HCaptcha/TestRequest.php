<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\HCaptcha;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

class TestRequest extends ReCaptcha\TestRequest {

	const URL_VERIFY = 'https://hcaptcha.com/siteverify';

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function runTest() {
		/** @var \ICWP_WPSF_FeatureHandler_BaseWpsf $oMod */
		$oMod = $this->getMod();

		$sCaptchaResponse = Services::Request()->post( 'h-captcha-response' );

		if ( empty( $sCaptchaResponse ) ) {
			throw new \Exception( __( 'Whoops.', 'wp-simple-firewall' ).' '.__( 'hCaptcha was not submitted.', 'wp-simple-firewall' ), 1 );
		}
		else {
			$oHTTP = Services::HttpRequest();
			$bSuccess = $oHTTP->post( self::URL_VERIFY, [
					'body' => [
						'secret'   => $oMod->getCaptchaCfg()->secret,
						'response' => $sCaptchaResponse,
						'remoteip' => Services::IP()->getRequestIp(),
					]
				] )
						&& !empty( $oHTTP->lastResponse->body );
			$aResponse = $bSuccess ? json_decode( $oHTTP->lastResponse->body, true ) : [];

			if ( empty( $aResponse[ 'success' ] ) ) {
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