<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @throws \Exception
	 */
	protected function processNotice( $oNotice ) {

		switch ( $oNotice->id ) {

			case 'admin-users-restricted':
				$this->adminUsersRestricted( $oNotice );
				break;

			default:
				parent::processNotice( $oNotice );
				break;
		}
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function adminUsersRestricted( $oNotice ) {
		$oCon = $this->getCon();
		/** @var Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		$sName = $oCon->getHumanName();

		$oNotice->display = in_array(
								Services::WpPost()->getCurrentPage(), $oOpts->getDef( 'restricted_pages_users' )
							)
							&& !$oCon->isPluginAdmin();

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
					$this->getMod()->getUrl_AdminPage(),
					__( 'Security Admin Login', 'wp-simple-firewall' ),
					sprintf( __( 'Go here to manage settings and authenticate with the %s plugin.', 'wp-simple-firewall' ), $sName )
				)
			]
		];
	}
}