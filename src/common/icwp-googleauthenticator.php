<?php
if ( class_exists( 'ICWP_WPSF_GoogleAuthenticator', false ) ) {
	return;
}

class ICWP_WPSF_GoogleAuthenticator {

	/**
	 * @var ICWP_WPSF_GoogleAuthenticator
	 */
	protected static $oInstance = null;

	/**
	 * @var PHPGangsta_GoogleAuthenticator
	 */
	protected static $oGA;

	/**
	 * @return ICWP_WPSF_GoogleAuthenticator
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @return string
	 */
	public function generateNewSecret() {
		return $this->getGoogleAuthenticatorLib()->createSecret();
	}

	/**
	 * @param string $sSecret
	 * @param string $sName
	 * @return string
	 */
	public function getGoogleQrChartUrl( $sSecret, $sName = 'icwp' ) {
		return $this->getGoogleAuthenticatorLib()->getQRCodeGoogleUrl( $sName, $sSecret );
	}

	/**
	 * @param string $sSecret
	 * @param string $sPassword
	 * @return bool
	 */
	public function verifyOtp( $sSecret, $sPassword ) {
		return $this->getGoogleAuthenticatorLib()->verifyCode( $sSecret, $sPassword );
	}

	/**
	 */
	protected function loadGoogleAuthenticatorLib() {
		if ( !class_exists( 'PHPGangsta_GoogleAuthenticator', false ) ) {
			require_once( __DIR__.'/googleauthenticator/googleauthenticator.php' );
		}
		return class_exists( 'PHPGangsta_GoogleAuthenticator', false );
	}

	/**
	 * @return PHPGangsta_GoogleAuthenticator
	 */
	protected function getGoogleAuthenticatorLib() {
		if ( !isset( self::$oGA ) ) {
			if ( $this->loadGoogleAuthenticatorLib() ) {
				self::$oGA = new PHPGangsta_GoogleAuthenticator();
			}
		}
		return self::$oGA;
	}
}