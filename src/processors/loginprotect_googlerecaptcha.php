<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/loginprotect_base.php' );

class ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		if ( !$oFO->getIsGoogleRecaptchaReady() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'registerGoogleRecaptchaJs' ), 99 );
		add_action( 'login_enqueue_scripts', array( $this, 'registerGoogleRecaptchaJs' ), 99 );

		add_action( 'login_form', array( $this, 'printGoogleRecaptchaCheck' ), 100 );
		add_filter( 'login_form_middle', array( $this, 'printGoogleRecaptchaCheck_Filter' ), 100 );

		if ( $oFO->getIfSupport3rdParty() ) {
			add_action( 'woocommerce_login_form', array( $this, 'printGoogleRecaptchaCheck' ), 100 );
			add_action( 'bp_before_registration_submit_buttons', array( $this, 'printGoogleRecaptchaCheck' ), 10 );
			add_action( 'bp_signup_validate', array( $this, 'checkGoogleRecaptcha_Action' ), 10 );
		}

		// before username/password check (20)
		add_filter( 'authenticate', array( $this, 'checkLoginForGoogleRecaptcha_Filter' ), 15, 3 );
	}

	/**
	 * @return string
	 */
	public function printGoogleRecaptchaCheck_Filter() {
		$this->setRecaptchaToEnqueue();
		return $this->getGoogleRecaptchaHtml();
	}

	/**
	 */
	public function printGoogleRecaptchaCheck() {
		$this->setRecaptchaToEnqueue();
		echo $this->getGoogleRecaptchaHtml();
	}

	/**
	 * @return string
	 */
	protected function getGoogleRecaptchaHtml() {
		$sNonInvisStyle = '<style>@media screen {#rc-imageselect, .icwpg-recaptcha iframe {transform:scale(0.90);-webkit-transform:scale(0.90);transform-origin:0 0;-webkit-transform-origin:0 0;}</style>';
		return sprintf( '%s<div class="icwpg-recaptcha"></div>', $this->isRecaptchaInvisible() ? '' : $sNonInvisStyle );
	}

	public function checkGoogleRecaptcha_Action() {
		try {
			$this->checkRequestRecaptcha();
		}
		catch ( Exception $oE ) {
			$this->loadWp()
				 ->wpDie( 'Google reCAPTCHA checking failed.' );
		}
	}

	/**
	 * This jumps in before user password is tested. If we fail the ReCaptcha check, we'll
	 * block testing of username and password
	 * @param WP_User|WP_Error $oUser
	 * @return WP_Error
	 */
	public function checkLoginForGoogleRecaptcha_Filter( $oUser ) {
		if ( !$this->loadWp()->isRequestUserLogin() ) {
			return $oUser;
		}

		// we haven't already failed before now
		if ( !is_wp_error( $oUser ) ) {

			try {
				$this->checkRequestRecaptcha();
			}
			catch ( Exception $oE ) {
				$sCode = ( $oE->getCode() == 1 ) ? 'shield_google_recaptcha_empty' : 'shield_google_recaptcha_failed';
				$oUser = new WP_Error( $sCode, $oE->getMessage() );
			}

			if ( is_wp_error( $oUser ) ) {
				$this->setLoginAsFailed( 'login.recaptcha.fail' );
			}
			else {
				$this->doStatIncrement( 'login.recaptcha.verified' );
			}
		}
		return $oUser;
	}
}