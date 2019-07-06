<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @throws \Exception
	 */
	protected function processNotice( $oNotice ) {

		switch ( $oNotice->id ) {
			default:
				parent::processNotice( $oNotice );
				break;
		}
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNoticeEmailVerificationSent( $oNotice ) {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();

		$oNotice->display = $oMod->isEmailAuthenticationOptionOn()
							&& !$oMod->isEmailAuthenticationActive() && !$oMod->getIfCanSendEmailVerified();

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
	 */
	private function buildNoticeAdminUsersRestricted( $oNotice ) {
		$oMod = $this->getMod();
		/** @var Options $oOpts */
		$oOpts = $oMod->getOptions();
		$sName = $this->getCon()->getHumanName();

		$oNotice->display = in_array(
			Services::WpPost()->getCurrentPage(), $oOpts->getDef( 'restricted_pages_users' )
		);

		$oNotice->render_data = [
			'notice_attributes' => [], // TODO
			'strings'           => [
				'title'          => sprintf( __( '%s Security Restrictions Applied', 'wp-simple-firewall' ), $sName ),
				'notice_message' => __( 'Editing existing administrators, promoting existing users to the administrator role, or deleting administrator users is currently restricted.', 'wp-simple-firewall' )
									.' '.__( 'Please authenticate with the Security Admin system before attempting any administrator user modifications.', 'wp-simple-firewall' ),
				'unlock_link'    => sprintf(
					'<a href="%1$s" title="%2$s" class="thickbox">%3$s</a>',
					'#TB_inline?width=400&height=180&inlineId=WpsfAdminAccessLogin',
					__( 'Security Admin Login', 'wp-simple-firewall' ),
					__( 'Unlock Now', 'wp-simple-firewall' )
				),
			],
			'hrefs'             => [
				'setting_page' => sprintf(
					'<a href="%s" title="%s">%s</a>',
					$oMod->getUrl_AdminPage(),
					__( 'Security Admin Login', 'wp-simple-firewall' ),
					sprintf( __( 'Go here to manage settings and authenticate with the %s plugin.', 'wp-simple-firewall' ), $sName )
				)
			]
		];
	}
}