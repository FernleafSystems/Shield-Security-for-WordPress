<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_LoginProtect extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oWp = $this->loadWp();

		// XML-RPC Compatibility
		if ( $oWp->getIsXmlrpc() && $oFO->isXmlrpcBypass() ) {
			return;
		}

		if ( $oFO->getIsCustomLoginPathEnabled() ) {
			$this->getProcessorWpLogin()->run();
		}

		// Add GASP checking to the login form.
		if ( $oFO->isEnabledGaspCheck() ) {
			$this->getProcessorGasp()->run();
		}

		if ( $oFO->isCooldownEnabled() && $this->loadDP()->isMethodPost() ) {
			$this->getProcessorCooldown()->run();
		}

		if ( $oFO->getIsGoogleRecaptchaEnabled() && $oFO->getIsGoogleRecaptchaReady() ) {
			$this->getProcessorGoogleRecaptcha()->run();
		}

		$this->getProcessorLoginIntent()->run();
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

		if ( $oFO->getIsEmailAuthenticationOptionOn() && !$oFO->getIsEmailAuthenticationEnabled() && !$oFO->getIfCanSendEmailVerified() ) {
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => array(
					'title'             => $this->getController()->getHumanName()
										   .': '._wpsf__( 'Please verify email has been received' ),
					'need_you_confirm'  => _wpsf__( "Before we can activate email 2-factor authentication, we need you to confirm your website can send emails." ),
					'please_click_link' => _wpsf__( "Please click the link in the email you received." ),
					'email_sent_to'     => sprintf(
						_wpsf__( "The email has been sent to you at blog admin address: %s" ),
						'<strong>'.get_bloginfo( 'admin_email' ).'</strong>'
					),
					'how_resend_email'  => _wpsf__( "To resend the email, re-save your Login Guard settings." ),
					'how_turn_off'      => _wpsf__( "To turn this notice off, disable 2-Factor Authentication." ),
				)
			);
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Intent
	 */
	public function getProcessorLoginIntent() {
		require_once( dirname( __FILE__ ).'/loginprotect_intent.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Intent( $this->getFeature() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Cooldown
	 */
	protected function getProcessorCooldown() {
		require_once( dirname( __FILE__ ).'/loginprotect_cooldown.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Cooldown( $this->getFeature() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Gasp
	 */
	protected function getProcessorGasp() {
		require_once( dirname( __FILE__ ).'/loginprotect_gasp.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Gasp( $this->getFeature() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_WpLogin
	 */
	protected function getProcessorWpLogin() {
		require_once( dirname( __FILE__ ).'/loginprotect_wplogin.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_WpLogin( $this->getFeature() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha
	 */
	protected function getProcessorGoogleRecaptcha() {
		require_once( dirname( __FILE__ ).'/loginprotect_googlerecaptcha.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha( $this->getFeature() );
		return $oProc;
	}
}