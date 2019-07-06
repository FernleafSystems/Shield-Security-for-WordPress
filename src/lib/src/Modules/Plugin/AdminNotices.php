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

			case 'override-forceoff':
				$this->buildNotice_OverrideForceoff( $oNotice );
				break;

			case 'plugin-mailing-list-signup':
				$this->buildNotice_PluginMailingListSignup( $oNotice );
				break;

			default:
				parent::processNotice( $oNotice );
				break;
		}
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_OverrideForceoff( $oNotice ) {
		$oCon = $this->getCon();
		$oMod = $this->getMod();

		$oNotice->display = $oCon->getIfForceOffActive();

		$oNotice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'   => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), sprintf( __( '%s is not protecting your site', 'wp-simple-firewall' ), $oCon->getHumanName() ) ),
				'message' => sprintf(
					__( 'Please delete the "%s" file to reactivate %s protection', 'wp-simple-firewall' ),
					'forceOff',
					$oCon->getHumanName()
				),
				'delete'  => __( 'Click here to automatically delete the file', 'wp-simple-firewall' )
			],
			'ajax'              => [
				'delete_forceoff' => $oMod->getAjaxActionData( 'delete_forceoff', true )
			]
		];
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_PluginMailingListSignup( $oNotice ) {
		$oMod = $this->getMod();
		$oOpts = $oMod->getOptions();

		$sName = $this->getCon()->getHumanName();
		$oUser = Services::WpUsers()->getCurrentWpUser();

		$oNotice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'yes'            => "Yes please! I'd love to join in and learn more",
				'no'             => "No thanks, I'm not interested in such groups",
				'your_name'      => __( 'Your Name', 'wp-simple-firewall' ),
				'your_email'     => __( 'Your Email', 'wp-simple-firewall' ),
				'signup'         => __( 'Sign-Up', 'wp-simple-firewall' ),
				'dismiss'        => "No thanks, I'm not interested in such informative groups",
				'summary'        => sprintf( 'The %s team is helping raise awareness of WP Security issues
				and to provide guidance with the %s plugin.', $sName, $sName ),
				'privacy_policy' => sprintf(
					'I certify that I have read and agree to the <a href="%s" target="_blank">Privacy Policy</a>',
					$oMod->getDef( 'href_privacy_policy' )
				),
				'consent'        => sprintf( __( 'I agree to Ts & Cs', 'wp-simple-firewall' ) )
			],
			'hrefs'             => [
				'privacy_policy' => $oOpts->getDef( 'href_privacy_policy' )
			],
			'install_days'      => $oOpts->getInstallationDays(),
			'vars'              => [
				'name'         => $oUser->first_name,
				'user_email'   => $oUser->user_email,
				'drip_form_id' => $oNotice->drip_form_id
			]
		];
	}
}