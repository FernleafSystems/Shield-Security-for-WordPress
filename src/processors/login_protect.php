<?php

class ICWP_WPSF_Processor_LoginProtect extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		$oWp = $this->loadWp();

		// XML-RPC Compatibility
		if ( $oWp->isXmlrpc() && $oFO->isXmlrpcBypass() ) {
			return;
		}

		if ( $oFO->isCustomLoginPathEnabled() ) {
			$this->getProcessorWpLogin()->run();
		}

		// Add GASP checking to the login form.
		if ( $oFO->isEnabledGaspCheck() ) {
			$this->getProcessorGasp()->run();
		}

		if ( $oFO->isCooldownEnabled() && $this->loadRequest()->isMethodPost() ) {
			$this->getProcessorCooldown()->run();
		}

		if ( $oFO->isGoogleRecaptchaEnabled() ) {
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
		$sSlug = $this->getMod()->getSlug();
		$aData[ $sSlug ][ 'options' ][ 'email_can_send_verified_at' ]
			= ( $aData[ $sSlug ][ 'options' ][ 'email_can_send_verified_at' ] > 0 ) ? 1 : 0;
		return $aData;
	}

	/**
	 * @param array $aNoticeAttributes
	 */
	public function addNotice_email_verification_sent( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isEmailAuthenticationOptionOn() && !$oFO->isEmailAuthenticationActive() && !$oFO->getIfCanSendEmailVerified() ) {
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => array(
					'title'             => $this->getCon()->getHumanName()
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
		require_once( __DIR__.'/loginprotect_intent.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Intent( $this->getMod() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Cooldown
	 */
	protected function getProcessorCooldown() {
		require_once( __DIR__.'/loginprotect_cooldown.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Cooldown( $this->getMod() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Gasp
	 */
	protected function getProcessorGasp() {
		require_once( __DIR__.'/loginprotect_gasp.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Gasp( $this->getMod() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_WpLogin
	 */
	protected function getProcessorWpLogin() {
		require_once( __DIR__.'/loginprotect_wplogin.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_WpLogin( $this->getMod() );
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha
	 */
	protected function getProcessorGoogleRecaptcha() {
		require_once( __DIR__.'/loginprotect_googlerecaptcha.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha( $this->getMod() );
		return $oProc;
	}
}