<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
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
		try {
			$this->runTest();
			$this->getCon()->fireEvent( 'recaptcha_success' );
		}
		catch ( \Exception $oE ) {
			$this->getCon()->fireEvent( 'recaptcha_fail' );
			throw $oE;
		}
		return true;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function runTest() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$sCaptchaResponse = Services::Request()->post( 'g-recaptcha-response' );

		if ( empty( $sCaptchaResponse ) ) {
			throw new \Exception( __( 'Whoops.', 'wp-simple-firewall' ).' '.__( 'CAPTCHA was not submitted.', 'wp-simple-firewall' ), 1 );
		}
		else {
			$oResponse = ( new ReCaptcha( $mod->getCaptchaCfg()->secret, new WordpressPost() ) )
				->verify( $sCaptchaResponse, Services::IP()->getRequestIp() );
			if ( empty( $oResponse ) || !$oResponse->isSuccess() ) {
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