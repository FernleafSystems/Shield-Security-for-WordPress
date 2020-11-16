<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @param NoticeVO $notice
	 * @throws \Exception
	 */
	protected function processNotice( NoticeVO $notice ) {

		switch ( $notice->id ) {

			case 'admin-users-restricted':
				$this->buildNotice_AdminUsersRestricted( $notice );
				break;

			case 'certain-options-restricted':
				$this->buildNotice_CertainOptionsRestricted( $notice );
				break;

			default:
				parent::processNotice( $notice );
				break;
		}
	}

	private function buildNotice_CertainOptionsRestricted( NoticeVO $notice ) {
		$oMod = $this->getMod();
		$sName = $this->getCon()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'          => sprintf( __( '%s Security Restrictions Applied', 'wp-simple-firewall' ), $sName ),
				'notice_message' => __( 'Altering certain options has been restricted by your WordPress security administrator.', 'wp-simple-firewall' )
									.' '.__( 'Repeated failed attempts to authenticate will probably lock you out of this site.', 'wp-simple-firewall' )
			],
			'hrefs'             => [
				'setting_page' => sprintf(
					'<a href="%s" title="%s">%s</a>',
					$oMod->getUrl_AdminPage(),
					__( 'Admin Access Login', 'wp-simple-firewall' ),
					sprintf( __( 'Go here to manage settings and authenticate with the %s plugin.', 'wp-simple-firewall' ), $sName )
				)
			]
		];
	}

	private function buildNotice_AdminUsersRestricted( NoticeVO $notice ) {
		$oMod = $this->getMod();
		$sName = $this->getCon()->getHumanName();

		$notice->render_data = [
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

	/**
	 * @param NoticeVO $notice
	 * @return bool
	 */
	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		$sCurrentPage = Services::WpPost()->getCurrentPage();

		switch ( $notice->id ) {

			case 'admin-users-restricted':
				$needed = in_array( $sCurrentPage, $oOpts->getDef( 'restricted_pages_users' ) );
				break;

			case 'certain-options-restricted':
				$sCurrentGetPage = Services::Request()->query( 'page' );
				$needed = empty( $sCurrentGetPage ) && in_array( $sCurrentPage, $oOpts->getOptionsPagesToRestrict() );
				break;

			default:
				$needed = parent::isDisplayNeeded( $notice );
				break;
		}
		return $needed;
	}
}