<?php
if ( class_exists( 'ICWP_WPSF_GoogleRecaptcha', false ) ) {
	return;
}

class ICWP_WPSF_GoogleRecaptcha {

	/**
	 * @var ICWP_WPSF_GoogleRecaptcha
	 */
	protected static $oInstance = null;

	/**
	 * @var \ReCaptcha\ReCaptcha
	 */
	protected static $oGR;

	/**
	 * @return ICWP_WPSF_GoogleRecaptcha
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 */
	protected function loadGoogleRecaptchaLib() {
		return require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'googlerecaptcha/autoload.php' );
	}

	/**
	 * @param string $sSecret
	 * @return \ReCaptcha\ReCaptcha
	 */
	public function getGoogleRecaptchaLib( $sSecret ) {
		if ( !isset( self::$oGR ) ) {
			if ( $this->loadGoogleRecaptchaLib() ) {
				self::$oGR = new \ReCaptcha\ReCaptcha( $sSecret, new \ReCaptcha\RequestMethod\WordpressPost() );
			}
		}
		return self::$oGR;
	}
}