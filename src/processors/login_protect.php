<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect', false ) ):

require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_LoginProtect extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$oWp = $this->loadWpFunctionsProcessor();

		// XML-RPC Compatibility
		if ( $oWp->getIsXmlrpc() && $this->getIsOption( 'enable_xmlrpc_compatibility', 'Y' ) ) {
			return true;
		}

		// Add GASP checking to the login form.
		if ( $this->getIsOption( 'enable_login_gasp_check', 'Y' ) ) {
			$this->getProcessorGasp()->run();
		}

		if ( $oFO->getIsCustomLoginPathEnabled() ) {
			$this->getProcessorWpLogin()->run();
		}

		if ( $this->getOption( 'login_limit_interval' ) > 0 && ( $oWp->getIsLoginRequest() || $oWp->getIsRegisterRequest() ) ) {
			$this->getProcessorCooldown()->run();
		}

		// check for Yubikey auth after user is authenticated with WordPress.
		if ( $this->getIsOption( 'enable_google_authenticator', 'Y' ) ) {
			$this->getProcessorGoogleAuthenticator()->run();
		}

		// check for Yubikey auth after user is authenticated with WordPress.
		if ( $this->getIsOption( 'enable_yubikey', 'Y' ) ) {
			$this->getProcessorYubikey()->run();
		}

		if ( $oFO->getIsEmailAuthenticationEnabled() ) {
			$this->getProcessorTwoFactor()->run();
		}

		if ( $oFO->getIsGoogleRecaptchaEnabled() ) {
			$this->getProcessorGoogleRecaptcha()->run();
		}

		add_filter( 'wp_login_errors', array( $this, 'addLoginMessage' ) );
		return true;
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		$aData[ 'options' ][ 'email_can_send_verified_at' ]
			= ( $aData[ 'options' ][ 'email_can_send_verified_at' ] > 0 ) ? 1 : 0;
		return $aData;
	}

	/**
	 * @param array $aNoticeAttributes
	 */
	public function addNotice_email_verification_sent( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		if ( $oFO->getIsEmailAuthenticationOptionOn() && !$oFO->getIsEmailAuthenticationEnabled() && !$oFO->getIfCanSendEmail() ) {
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings' => array(
					'need_you_confirm' => _wpsf__( "Before completing activation of email-based two-factor authentication we need you to confirm your site can send emails." ),
					'please_click_link' => _wpsf__( "Please click the link in the email you received." ),
					'email_sent_to' => sprintf( _wpsf__( "The email has been sent to you at blog admin address: %s" ), get_bloginfo( 'admin_email' ) ),
					'how_resend_email' => _wpsf__( "To have this email resent, re-save your Login Protection settings." ),
					'how_turn_off' => _wpsf__( "To turn this notice off, disable Two Factor authentication." ),
				)
			);
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * @param WP_Error $oError
	 * @return WP_Error
	 */
	public function addLoginMessage( $oError ) {

		if ( ! $oError instanceof WP_Error ) {
			$oError = new WP_Error();
		}

		$oDp = $this->loadDataProcessor();
		$sForceLogout = $oDp->FetchGet( 'wpsf-forcelogout' );
		if ( $sForceLogout == 6 ) {
			$oError->add( 'wpsf-forcelogout', _wpsf__('Your Two-Factor Authentication was un-verified or invalidated by a login from another location or browser.').'<br />'._wpsf__('Please login again.') );
		}
		return $oError;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Cooldown
	 */
	protected function getProcessorCooldown() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_cooldown.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Cooldown( $this->getFeatureOptions() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth
	 */
	protected function getProcessorTwoFactor() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_twofactorauth.php' );
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$oProc = new ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth( $oFO );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Gasp
	 */
	protected function getProcessorGasp() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_gasp.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Gasp( $this->getFeatureOptions() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_WpLogin
	 */
	protected function getProcessorWpLogin() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_wplogin.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_WpLogin( $this->getFeatureOptions() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha
	 */
	protected function getProcessorGoogleRecaptcha() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_googlerecaptcha.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha( $this->getFeatureOptions() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Yubikey
	 */
	protected function getProcessorYubikey() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_yubikey.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Yubikey( $this->getFeatureOptions() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator
	 */
	protected function getProcessorGoogleAuthenticator() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_googleauthenticator.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator( $this->getFeatureOptions() );
		return $oProc;
	}
}
endif;