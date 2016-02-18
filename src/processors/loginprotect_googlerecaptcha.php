<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base_wpsf.php' );

class ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		if ( !$this->loadDataProcessor()->getPhpSupportsNamespaces()
			|| !$this->loadWpFunctionsProcessor()->getIsLoginUrl()
			|| !$oFO->getIsGoogleRecaptchaReady() ) {
			return;
		}

		add_action( 'login_enqueue_scripts',	array( $this, 'loadGoogleRecaptchaJs' ), 99 );

		add_action( 'login_form',				array( $this, 'printGoogleRecaptchaCheck' ) );
		add_action( 'woocommerce_login_form',	array( $this, 'printGoogleRecaptchaCheck' ) );
		add_filter( 'login_form_middle',		array( $this, 'printGoogleRecaptchaCheck_Filter' ) );

		// after GASP but before email-based two-factor auth
		add_filter( 'authenticate',				array( $this, 'checkLoginForGoogleRecaptcha_Filter' ), 24, 3 );
	}

	public function loadGoogleRecaptchaJs() {
		wp_register_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js' );
		wp_enqueue_script( 'google-recaptcha' );
	}

	/**
	 * @return string
	 */
	public function printGoogleRecaptchaCheck_Filter() {
		return $this->getGoogleRecaptchaHtml();
	}

	/**
	 */
	public function printGoogleRecaptchaCheck() {
		echo $this->getGoogleRecaptchaHtml();
	}

	/**
	 * @return string
	 */
	protected function getGoogleRecaptchaHtml() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$sSiteKey = $oFO->getGoogleRecaptchaSiteKey();
		return sprintf(
			'%s<div class="g-recaptcha" data-sitekey="%s"></div>',
			'<style>@media screen and (max-height: 575px){
#rc-imageselect, .g-recaptcha iframe {transform:scale(0.90);-webkit-transform:scale(0.90);transform-origin:0 0;-webkit-transform-origin:0 0;}</style>',
			$sSiteKey
		);
	}

	/**
	 * @param WP_User $oUser
	 * @return WP_Error
	 */
	public function checkLoginForGoogleRecaptcha_Filter( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		$oError = new WP_Error();
		$bIsUser = is_object( $oUser ) && ( $oUser instanceof WP_User );

		if ( $bIsUser ) {

			$sCaptchaResponse = $this->loadDataProcessor()->FetchPost( 'g-recaptcha-response' );

			if ( empty( $sCaptchaResponse ) ) {
				$oError->add( 'shield_google_recaptcha_empty', _wpsf__( 'Whoops.' )
					.' '. _wpsf__( 'Google Recaptcha was not submitted.' ) );
				$oUser = $oError;
			}
			else {
				$oRecaptcha = $this->loadGoogleRecaptcha()->getGoogleRecaptchaLib( $oFO->getGoogleRecaptchaSecretKey() );
				$oResponse = $oRecaptcha->verify( $sCaptchaResponse, $this->human_ip() );
				if ( empty( $oResponse ) || !$oResponse->isSuccess() ) {
					$oError->add( 'shield_google_recaptcha_failed', _wpsf__( 'Whoops.' )
						.' '. _wpsf__( 'Google Recaptcha verification failed.' ) );
					$oUser = $oError;
				}
			}
		}
		return $oUser;
	}
}
endif;