<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_LoginProtect extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		// XML-RPC Compatibility
		if ( Services::WpGeneral()->isXmlrpc() && $oFO->isXmlrpcBypass() ) {
			return;
		}

		if ( $oFO->isCustomLoginPathEnabled() ) {
			$this->getSubProRename()->run();
		}

		// Add GASP checking to the login form.
		if ( $oFO->isEnabledGaspCheck() ) {
			$this->getSubProGasp()->run();
		}

		if ( $oFO->isCooldownEnabled() && Services::Request()->isPost() ) {
			$this->getSubProCooldown()->run();
		}

		if ( $oFO->isGoogleRecaptchaEnabled() ) {
			$this->getSubProRecaptcha()->run();
		}

		$this->getSubProIntent()->run();
	}

	public function onWpEnqueueJs() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isEnabledBotJs() ) {
			$oConn = $this->getCon();

			$sAsset = 'shield-antibot';
			$sUnique = $this->prefix( $sAsset );
			wp_register_script(
				$sUnique,
				$oConn->getPluginUrl_Js( $sAsset.'.js' ),
				[ 'jquery' ],
				$oConn->getVersion(),
				true
			);
			wp_enqueue_script( $sUnique );

			wp_localize_script(
				$sUnique,
				'icwp_wpsf_vars_lpantibot',
				[
					'form_selectors' => implode( ',', $oFO->getAntiBotFormSelectors() ),
					'uniq'           => preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) ),
					'cbname'         => $oFO->getGaspKey(),
					'strings'        => [
						'label' => $oFO->getTextImAHuman(),
						'alert' => $oFO->getTextPleaseCheckBox(),
					],
					'flags'          => [
						'gasp'  => $oFO->isEnabledGaspCheck(),
						'recap' => $oFO->isGoogleRecaptchaEnabled(),
					]
				]
			);

			if ( $oFO->isGoogleRecaptchaEnabled() ) {
				$this->setRecaptchaToEnqueue();
			}
		}
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
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();

		if ( $oMod->isEmailAuthenticationOptionOn()
			 && !$oMod->isEmailAuthenticationActive() && !$oMod->getIfCanSendEmailVerified() ) {
			$aRenderData = [
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => [
					'title'             => $this->getCon()->getHumanName()
										   .': '.__( 'Please verify email has been received', 'wp-simple-firewall' ),
					'need_you_confirm'  => __( "Before we can activate email 2-factor authentication, we need you to confirm your website can send emails.", 'wp-simple-firewall' ),
					'please_click_link' => __( "Please click the link in the email you received.", 'wp-simple-firewall' ),
					'email_sent_to'     => sprintf(
						__( "The email has been sent to you at blog admin address: %s", 'wp-simple-firewall' ),
						get_bloginfo( 'admin_email' )
					),
					'how_resend_email'  => __( "Resend verification email", 'wp-simple-firewall' ),
					'how_turn_off'      => __( "Disable 2FA by email", 'wp-simple-firewall' ),
				],
				'ajax'              => [
					'resend_verification_email' => $oMod->getAjaxActionData( 'resend_verification_email', true ),
					'disable_2fa_email'         => $oMod->getAjaxActionData( 'disable_2fa_email', true ),
				]
			];
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'cooldown'  => 'ICWP_WPSF_Processor_LoginProtect_Cooldown',
			'gasp'      => 'ICWP_WPSF_Processor_LoginProtect_Gasp',
			'intent'    => 'ICWP_WPSF_Processor_LoginProtect_Intent',
			'recaptcha' => 'ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha',
			'rename'    => 'ICWP_WPSF_Processor_LoginProtect_WpLogin',
		];
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Cooldown
	 */
	private function getSubProCooldown() {
		return $this->getSubPro( 'cooldown' );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Gasp
	 */
	private function getSubProGasp() {
		return $this->getSubPro( 'gasp' );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Intent
	 */
	public function getSubProIntent() {
		return $this->getSubPro( 'intent' );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha
	 */
	private function getSubProRecaptcha() {
		return $this->getSubPro( 'recaptcha' );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_WpLogin
	 */
	private function getSubProRename() {
		return $this->getSubPro( 'rename' );
	}
}