<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\HCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

class TestRequest extends ReCaptcha\TestRequest {

	const URL_VERIFY = 'https://hcaptcha.com/siteverify';

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function runTest() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$sCaptchaResponse = Services::Request()->post( 'h-captcha-response' );

		if ( empty( $sCaptchaResponse ) ) {
			throw new \Exception( __( 'Whoops.', 'wp-simple-firewall' ).' '.__( 'CAPTCHA was not submitted.', 'wp-simple-firewall' ), 1 );
		}
		else {
			$oHTTP = Services::HttpRequest();
			$bSuccess = $oHTTP->post( self::URL_VERIFY, [
					'body' => [
						'secret'   => $mod->getCaptchaCfg()->secret,
						'response' => $sCaptchaResponse,
						'remoteip' => Services::IP()->getRequestIp(),
					]
				] )
						&& !empty( $oHTTP->lastResponse->body );
			$aResponse = $bSuccess ? json_decode( $oHTTP->lastResponse->body, true ) : [];

			if ( empty( $aResponse[ 'success' ] ) ) {
				$aMsg = [
					__( 'Whoops.', 'wp-simple-firewall' ),
					__( 'CAPTCHA verification failed.', 'wp-simple-firewall' ),
					Services::WpGeneral()->isAjax() ?
						__( 'Maybe refresh the page and try again.', 'wp-simple-firewall' ) : ''
				];
				throw new \Exception( implode( ' ', $aMsg ), 2 );
			}
		}
		return true;
	}
}