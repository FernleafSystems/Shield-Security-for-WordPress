<?php

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
	 * @throws \Exception
	 */
	protected function performCheckWithException() {

		if ( !$this->isFactorTested() ) {

			try {
				$this->checkRequestRecaptcha();
				$this->setFactorTested( true );
				$this->doStatIncrement( 'login.recaptcha.verified' );
			}
			catch ( \Exception $oE ) {
				$this->setLoginAsFailed( 'login.recaptcha.fail' );
				throw $oE;
			}
		}
	}

	/**
	 * @return string
	 */
	protected function buildFormItems() {
		return $this->getGoogleRecaptchaHtml();
	}

	/**
	 * @return void
	 */
	public function printFormItems() {
		$this->setRecaptchaToEnqueue();
		parent::printFormItems();
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
	public function provideLoginFormItems() {
		$this->setRecaptchaToEnqueue();
		return parent::provideLoginFormItems();
	}

	/**
	 * We add the hidden input because the WooCommerce login processing doesn't fire unless
	 * $_POST['login'] is set. But this is put on the form button and so doesn't get submitted using JQuery
	 * @return void
	 */
	public function printLoginFormItems_Woo() {
		parent::printLoginFormItems_Woo();
		if ( $this->isRecaptchaInvisible() ) {
			echo '<input type="hidden" name="login" value="Log in" />';
		}
	}

	/**
	 * We add the hidden input because the WooCommerce register processing doesn't fire unless
	 * $_POST['register'] is set. But this is put on the form button and so doesn't get submitted using JQuery
	 * @return void
	 */
	public function printRegisterFormItems_Woo() {
		parent::printRegisterFormItems_Woo();
		if ( $this->isRecaptchaInvisible() ) {
			echo '<input type="hidden" name="register" value="Register" />';
		}
	}

	/**
	 * @return string
	 */
	private function getGoogleRecaptchaHtml() {
		$sNonInvisStyle = '<style>@media screen {#rc-imageselect, .icwpg-recaptcha iframe {transform:scale(0.90);-webkit-transform:scale(0.90);transform-origin:0 0;-webkit-transform-origin:0 0;}</style>';
		return sprintf( '%s<div class="icwpg-recaptcha"></div>', $this->isRecaptchaInvisible() ? '' : $sNonInvisStyle );
	}
}