<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	protected function processNotice( NoticeVO $notice ) {
		switch ( $notice->id ) {
			case 'email-verification-sent':
				$this->buildNotice_EmailVerificationSent( $notice );
				break;
			default:
				parent::processNotice( $notice );
				break;
		}
	}

	private function buildNotice_EmailVerificationSent( NoticeVO $notice ) {
		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'             => self::con()->getHumanName()
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
				'resend_verification_email' => ActionData::BuildJson( Actions\MfaEmailSendVerification::class ),
				'profile_email2fa_disable'  => ActionData::BuildJson( Actions\MfaEmailDisable::class ),
			],
		];
	}

	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		/** @var Options $opts */
		$opts = $this->opts();

		switch ( $notice->id ) {

			case 'email-verification-sent':
				$needed = $opts->isEnabledEmailAuth()
						  && !$opts->isEmailAuthenticationActive() && !$opts->getIfCanSendEmailVerified();
				break;

			default:
				$needed = parent::isDisplayNeeded( $notice );
				break;
		}
		return $needed;
	}
}