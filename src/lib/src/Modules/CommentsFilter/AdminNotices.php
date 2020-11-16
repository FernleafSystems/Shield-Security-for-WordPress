<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @inheritDoc
	 */
	protected function processNotice( NoticeVO $notice ) {

		switch ( $notice->id ) {

			case 'akismet-running':
				$this->buildNotice_AkismetRunning( $notice );
				break;

			default:
				parent::processNotice( $notice );
				break;
		}
	}

	private function buildNotice_AkismetRunning( NoticeVO $notice ) {
		$oWpPlugins = Services::WpPlugins();

		$notice->render_data = [
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
	 * @param Shield\Utilities\AdminNotices\NoticeVO $notice
	 * @return bool
	 */
	protected function isDisplayNeeded( Shield\Utilities\AdminNotices\NoticeVO $notice ) :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();

		switch ( $notice->id ) {

			case 'akismet-running':
				$oWpPlugins = Services::WpPlugins();
				$sPluginFile = $oWpPlugins->findPluginFileFromDirName( 'akismet' );
				$needed = $this->getMod()->isModuleEnabled()
						  && !empty( $sPluginFile )
						  && $oWpPlugins->isActive( $sPluginFile )
						  && $opts->isEnabledHumanCheck();
				break;

			default:
				$needed = parent::isDisplayNeeded( $notice );
				break;
		}
		return $needed;
	}
}