<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_LoginProtect extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oWp = $this->loadWp();

		// XML-RPC Compatibility
		if ( $oWp->getIsXmlrpc() && $this->getIsOption( 'enable_xmlrpc_compatibility', 'Y' ) ) {
			return;
		}

		if ( $oFO->getIsCustomLoginPathEnabled() ) {
			$this->getProcessorWpLogin()->run();
		}

		// Add GASP checking to the login form.
		if ( $oFO->isEnabledGaspCheck() ) {
			$this->getProcessorGasp()->run();
		}

		if ( $this->getOption( 'login_limit_interval' ) > 0 && ( $oWp->isRequestUserLogin() || $oWp->isRequestUserRegister() ) ) {
			$this->getProcessorCooldown()->run();
		}

		if ( $oFO->getIsGoogleRecaptchaEnabled() ) {
			$this->getProcessorGoogleRecaptcha()->run();
		}

		$this->getProcessorLoginIntent()->run();

		add_filter( 'wp_login_errors', array( $this, 'addLoginMessage' ) );

		switch ( (string)$this->loadDP()->query( 'shield_action', '' ) ) {

			case 'wizard':
				if ( $oFO->getCanRunWizards() ) {
					$this->getWizardProcessor()->run();
				}
				break;

			default:
				break;
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Wizard
	 */
	public function getWizardProcessor() {
		/** @var ICWP_WPSF_FeatureHandler_BaseWpsf $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->getCanRunWizards() && !isset( $this->oWizProcessor ) ) {
			require_once( dirname( __FILE__ ).'/loginprotect_wizard.php' );
			$this->oWizProcessor = new ICWP_WPSF_Processor_LoginProtect_Wizard( $this->getFeature() );
		}
		return $this->oWizProcessor;
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		$sSlug = $this->getFeature()->getFeatureSlug();
		$aData[ $sSlug ][ 'options' ][ 'email_can_send_verified_at' ]
			= ( $aData[ $sSlug ][ 'options' ][ 'email_can_send_verified_at' ] > 0 ) ? 1 : 0;
		return $aData;
	}

	/**
	 * @param array $aNoticeAttributes
	 */
	public function addNotice_email_verification_sent( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		if ( $oFO->getIsEmailAuthenticationOptionOn() && !$oFO->getIsEmailAuthenticationEnabled() && !$oFO->getIfCanSendEmail() ) {
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => array(
					'need_you_confirm'  => _wpsf__( "Before completing activation of email-based two-factor authentication we need you to confirm your site can send emails." ),
					'please_click_link' => _wpsf__( "Please click the link in the email you received." ),
					'email_sent_to'     => sprintf( _wpsf__( "The email has been sent to you at blog admin address: %s" ), get_bloginfo( 'admin_email' ) ),
					'how_resend_email'  => _wpsf__( "To have this email resent, re-save your Login Protection settings." ),
					'how_turn_off'      => _wpsf__( "To turn this notice off, disable Two Factor authentication." ),
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

		if ( !$oError instanceof WP_Error ) {
			$oError = new WP_Error();
		}

		$oDp = $this->loadDataProcessor();
		$sForceLogout = $oDp->FetchGet( 'wpsf-forcelogout' );
		if ( $sForceLogout == 6 ) {
			$oError->add( 'wpsf-forcelogout', _wpsf__( 'Your Two-Factor Authentication was un-verified or invalidated by a login from another location or browser.' ).'<br />'._wpsf__( 'Please login again.' ) );
		}
		return $oError;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Intent
	 */
	public function getProcessorLoginIntent() {
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'loginprotect_intent.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Intent( $this->getFeature() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Cooldown
	 */
	protected function getProcessorCooldown() {
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'loginprotect_cooldown.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Cooldown( $this->getFeature() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Gasp
	 */
	protected function getProcessorGasp() {
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'loginprotect_gasp.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Gasp( $this->getFeature() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_WpLogin
	 */
	protected function getProcessorWpLogin() {
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'loginprotect_wplogin.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_WpLogin( $this->getFeature() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha
	 */
	protected function getProcessorGoogleRecaptcha() {
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'loginprotect_googlerecaptcha.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha( $this->getFeature() );
		return $oProc;
	}
}