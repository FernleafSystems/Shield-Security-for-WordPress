<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/loginprotect_base.php' );

class ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 * We no longer check if recaptcha is ready, we just use run(). So check beforehand.
	 */
	public function run() {
		parent::run();
		add_action( 'wp_enqueue_scripts', array( $this, 'registerGoogleRecaptchaJs' ), 99 );
		add_action( 'login_enqueue_scripts', array( $this, 'registerGoogleRecaptchaJs' ), 99 );
	}

	/**
	 * @throws Exception
	 */
	protected function performCheckWithException() {

		if ( !$this->isFactorTested() ) {

			$this->setFactorTested( true );
			try {
				$this->checkRequestRecaptcha();
				$this->doStatIncrement( 'login.recaptcha.verified' );
			}
			catch ( Exception $oE ) {
				$this->setLoginAsFailed( 'login.recaptcha.fail' );
				throw $oE;
			}
		}
	}

	/**
	 * @return string
	 */
	public function provideLoginFormItems() {
		$this->setRecaptchaToEnqueue();
		return parent::provideLoginFormItems();
	}

	/**
	 * @return void
	 */
	public function printLoginFormItems() {
		$this->setRecaptchaToEnqueue();
		parent::printLoginFormItems();
	}

	/**
	 * @return string
	 */
	protected function buildLoginFormItems() {
		return $this->getGoogleRecaptchaHtml();
	}

	/**
	 * @return string
	 */
	private function getGoogleRecaptchaHtml() {
		$sNonInvisStyle = '<style>@media screen {#rc-imageselect, .icwpg-recaptcha iframe {transform:scale(0.90);-webkit-transform:scale(0.90);transform-origin:0 0;-webkit-transform-origin:0 0;}</style>';
		return sprintf( '%s<div class="icwpg-recaptcha"></div>', $this->isRecaptchaInvisible() ? '' : $sNonInvisStyle );
	}
}