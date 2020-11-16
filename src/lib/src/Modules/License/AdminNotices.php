<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @param NoticeVO $notice
	 * @throws \Exception
	 */
	protected function processNotice( NoticeVO $notice ) {
		switch ( $notice->id ) {
			case 'wphashes-token-fail':
				$this->buildNotice_WpHashesTokenFailure( $notice );
				break;
			default:
				parent::processNotice( $notice );
				break;
		}
	}

	private function buildNotice_WpHashesTokenFailure( NoticeVO $notice ) {
		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'           => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ),
					sprintf( __( '%s API Token Missing', 'wp-simple-firewall' ), 'WPHashes.com' ) ),
				'messages'        => [
					__( "This site appears to be activated for PRO, but there's been a problem obtaining an API token for WPHashes.com.", 'wp-simple-firewall' ),
					implode( ' ', [
						__( 'The WPHashes API is used for many premium features including Malware scanning.', 'wp-simple-firewall' ),
						__( 'Without a valid API Token, certain Premium features wont work as expected.', 'wp-simple-firewall' ),
					] ),
					__( "Please contact us in our support channel if this doesn't sound right, or upgrade to PRO.", 'wp-simple-firewall' ),
				],
				'jump_to_support' => __( 'Click to jump to the relevant option', 'wp-simple-firewall' )
			],
			'hrefs'             => [
				'jump_to_support' => $this->getMod()->getUrl_DirectLinkToSection( 'global_enable_plugin_features' )
			]
		];
	}

	/**
	 * @param NoticeVO $notice
	 * @return bool
	 */
	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		switch ( $notice->id ) {

			case 'wphashes-token-fail':
				$needed = $this->getCon()->isPremiumActive()
						  && !$mod->getWpHashesTokenManager()->hasToken();
				break;

			default:
				$needed = parent::isDisplayNeeded( $notice );
				break;
		}
		return $needed;
	}
}