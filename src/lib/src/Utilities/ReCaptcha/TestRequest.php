<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use ReCaptcha\ReCaptcha;

class TestRequest {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function test() :bool {
		try {
			$this->runTest();
			self::con()->fireEvent( 'recaptcha_success' );
		}
		catch ( \Exception $e ) {
			self::con()->fireEvent( 'recaptcha_fail' );
			throw $e;
		}
		return true;
	}

	/**
	 * @throws \Exception
	 */
	protected function runTest() :bool {
		$captchaResponse = Services::Request()->post( 'g-recaptcha-response' );

		if ( empty( $captchaResponse ) ) {
			throw new \Exception( __( 'Whoops.', 'wp-simple-firewall' ).' '.__( 'CAPTCHA was not submitted.', 'wp-simple-firewall' ), 1 );
		}
		else {
			$response = ( new ReCaptcha( $this->mod()->getCaptchaCfg()->secret, new WordpressPost() ) )
				->verify( $captchaResponse, self::con()->this_req->ip );
			if ( empty( $response ) || !$response->isSuccess() ) {
				$msg = [
					__( 'Whoops.', 'wp-simple-firewall' ),
					__( 'CAPTCHA verification failed.', 'wp-simple-firewall' ),
					Services::WpGeneral()->isAjax() ?
						__( 'Maybe refresh the page and try again.', 'wp-simple-firewall' ) : ''
				];
				throw new \Exception( \implode( ' ', $msg ), 2 );
			}
		}
		return true;
	}
}