<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @throws \Exception
	 */
	protected function processNotice( $oNotice ) {

		switch ( $oNotice->id ) {

			case 'email-verification-sent':
				$this->buildNotice_EmailVerificationSent( $oNotice );
				break;

			default:
				parent::processNotice( $oNotice );
				break;
		}
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_EmailVerificationSent( $oNotice ) {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();

		$oNotice->render_data = [
			'notice_attributes' => [],
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
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @return bool
	 */
	protected function isDisplayNeeded( $oNotice ) {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		switch ( $oNotice->id ) {

			case 'email-verification-sent':
				$bNeeded = $oOpts->isEnabledEmailAuth()
						   && !$oOpts->isEmailAuthenticationActive() && !$oOpts->getIfCanSendEmailVerified();
				break;

			default:
				$bNeeded = parent::isDisplayNeeded( $oNotice );
				break;
		}
		return $bNeeded;
	}
}