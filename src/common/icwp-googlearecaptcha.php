<?php

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
	 * @param string $sSecret
	 * @return \ReCaptcha\ReCaptcha
	 */
	public function getGoogleRecaptchaLib( $sSecret ) {
		if ( !isset( self::$oGR ) ) {
			self::$oGR = new \ReCaptcha\ReCaptcha( $sSecret, new \ReCaptcha\RequestMethod\WordpressPost() );
		}
		return self::$oGR;
	}
}