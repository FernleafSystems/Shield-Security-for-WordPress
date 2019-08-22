<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @throws \Exception
	 */
	protected function processNotice( $oNotice ) {

		switch ( $oNotice->id ) {

			case 'akismet-running':
				$this->buildNotice_AkismetRunning( $oNotice );
				break;

			default:
				parent::processNotice( $oNotice );
				break;
		}
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_AkismetRunning( $oNotice ) {
		$oWpPlugins = Services::WpPlugins();

		$oNotice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'                   => ucwords( __( 'Akismet Anti-SPAM plugin is also running', 'wp-simple-firewall' ) ),
				'appears_running_akismet' => __( 'It appears you have Akismet Anti-SPAM running alongside the our human Anti-SPAM filter.', 'wp-simple-firewall' ),
				'not_recommended'         => __( 'This is not recommended and you should disable Akismet.', 'wp-simple-firewall' ),
				'click_to_deactivate'     => __( 'Click to deactivate Akismet now.', 'wp-simple-firewall' ),
			],
			'hrefs'             => [
				'deactivate' => $oWpPlugins->getUrl_Deactivate( $oWpPlugins->findPluginFileFromDirName( 'akismet' ) )
			]
		];
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @return bool
	 */
	protected function isDisplayNeeded( $oNotice ) {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();

		switch ( $oNotice->id ) {

			case 'akismet-running':
				$oWpPlugins = Services::WpPlugins();
				$sPluginFile = $oWpPlugins->findPluginFileFromDirName( 'akismet' );
				$bNeeded = $oMod->isEnabledHumanCheck()
						   && !empty( $sPluginFile ) && $oWpPlugins->isActive( $sPluginFile );
				break;

			default:
				$bNeeded = parent::isDisplayNeeded( $oNotice );
				break;
		}
		return $bNeeded;
	}
}