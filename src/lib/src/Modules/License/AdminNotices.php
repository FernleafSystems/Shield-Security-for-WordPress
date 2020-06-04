<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @throws \Exception
	 */
	protected function processNotice( $oNotice ) {
		switch ( $oNotice->id ) {
			case 'wphashes-token-fail':
				$this->buildNotice_WpHashesTokenFailure( $oNotice );
				break;
			default:
				parent::processNotice( $oNotice );
				break;
		}
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_WpHashesTokenFailure( $oNotice ) {
		$oNotice->render_data = [
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
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @return bool
	 */
	protected function isDisplayNeeded( $oNotice ) {
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();

		switch ( $oNotice->id ) {

			case 'wphashes-token-fail':
				$bNeeded = $this->getCon()->isPremiumActive()
						   && !$oMod->getWpHashesTokenManager()->hasToken();
				break;

			default:
				$bNeeded = parent::isDisplayNeeded( $oNotice );
				break;
		}
		return $bNeeded;
	}
}