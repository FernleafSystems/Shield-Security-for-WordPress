<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha', false ) ):

require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_base.php' );

class ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		if ( !$this->loadWpFunctionsProcessor()->getIsLoginUrl() || !$oFO->getIsGoogleRecaptchaReady() ) {
			return;
		}

		add_action( 'login_enqueue_scripts',	array( $this, 'loadGoogleRecaptchaJs' ), 99 );

		add_action( 'login_form',				array( $this, 'printGoogleRecaptchaCheck' ) );
		add_action( 'woocommerce_login_form',	array( $this, 'printGoogleRecaptchaCheck' ) );
		add_filter( 'login_form_middle',		array( $this, 'printGoogleRecaptchaCheck_Filter' ) );

			// before username/password check (20)
			add_filter( 'authenticate',				array( $this, 'checkLoginForGoogleRecaptcha_Filter' ), 15, 3 );
	}

	public function loadGoogleRecaptchaJs() {
		wp_register_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?hl='
			. $this->getGoogleRecaptchaLocale(get_locale()) );
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
	protected function getGoogleRecaptchaLocale($siteLocale)
	{
		// this is crap.. but based on..https://developers.google.com/recaptcha/docs/language
		// supports https://en.wikipedia.org/wiki/ISO_639 but also https://en.wikipedia.org/wiki/ISO_3166-1

		// site locale to iso locale (wp en_GB -> iso en-GB)
		$locale = str_replace('_', '-', $siteLocale);

		// these derivative locales should be used... https://developers.google.com/recaptcha/docs/language
		static $subs = [
			'zh-HK',
			'zh-CN',
			'zh-TW',
			'en-GB',
			'fr-CA',
			'pt-PT',
			'es-419',
		];
		if (in_array($locale, $subs, true)) {
			return $subs[$locale];
		}
		// else make it compatible (en-GB -> en)
		return strpos($locale, '-') ? explode('-', $locale)[0] : $locale;
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
			'<style>@media screen {
#rc-imageselect, .g-recaptcha iframe {transform:scale(0.90);-webkit-transform:scale(0.90);transform-origin:0 0;-webkit-transform-origin:0 0;}</style>',
			$sSiteKey
		);
	}

	/**
	 * This jumps in before user password is tested. If we fail the ReCaptcha check, we'll
	 * block testing of username and password
	 *
	 * @param WP_User|WP_Error $oUser
	 * @return WP_Error
	 */
	public function checkLoginForGoogleRecaptcha_Filter( $oUser ) {
		if ( !$this->loadWpFunctionsProcessor()->getIsLoginRequest() ) {
			return $oUser;
		}

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		// we haven't already failed before now
		if ( !is_wp_error( $oUser ) ) {

			$oError = new WP_Error();
			$sCaptchaResponse = $this->loadDataProcessor()->FetchPost( 'g-recaptcha-response' );

			if ( empty( $sCaptchaResponse ) ) {
				$oError->add( 'shield_google_recaptcha_empty', _wpsf__( 'Whoops.' )
					.' '. _wpsf__( 'Google reCAPTCHA was not submitted.' ) );
				$oUser = $oError;
			}
			else {
				$oRecaptcha = $this->loadGoogleRecaptcha()->getGoogleRecaptchaLib( $oFO->getGoogleRecaptchaSecretKey() );
				$oResponse = $oRecaptcha->verify( $sCaptchaResponse, $this->human_ip() );
				if ( empty( $oResponse ) || !$oResponse->isSuccess() ) {
					$oError->add( 'shield_google_recaptcha_failed', _wpsf__( 'Whoops.' )
						.' '. _wpsf__( 'Google reCAPTCHA verification failed.' ) );
					$oUser = $oError;
				}
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
endif;
