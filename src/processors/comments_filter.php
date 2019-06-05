<?php

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

class ICWP_WPSF_Processor_CommentsFilter extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
	}

	public function onWpInit() {
		parent::onWpInit();
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();

		if ( !$oFO->isUserTrusted( Services::WpUsers()->getCurrentWpUser() ) ) {

			if ( $oFO->isEnabledGaspCheck() ) {
				$this->getSubProGasp()->run();
			}
			if ( $oFO->isGoogleRecaptchaEnabled() ) {
				$this->getSubProRecaptcha()->run();
			}

			( new CommentsFilter\Scan\Scanner() )
				->setMod( $oFO )
				->run();

			add_filter( 'comment_notification_recipients', [ $this, 'clearCommentNotificationEmail' ], 100, 1 );
		}
	}

	public function runHourlyCron() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();
		if ( $oMod->isEnabledGaspCheck() ) {
			delete_expired_transients(); // cleanup unused comment tokens
		}
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'gasp'      => 'ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam',
			'recaptcha' => 'ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha',
		];
	}

	/**
	 * @return ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam
	 */
	private function getSubProGasp() {
		return $this->getSubPro( 'gasp' );
	}

	/**
	 * @return ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam
	 */
	private function getSubProRecaptcha() {
		return $this->getSubPro( 'recaptcha' );
	}

	/**
	 * @param array $aNoticeAttributes
	 */
	protected function addNotice_akismet_running( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();

		// We only warn when the human spam filter is running
		if ( $oFO->isEnabledHumanCheck() ) {

			$oWpPlugins = Services::WpPlugins();
			$sPluginFile = $oWpPlugins->findPluginBy( 'Akismet', 'Name' );
			if ( $oWpPlugins->isActive( $sPluginFile ) ) {
				$aRenderData = [
					'notice_attributes' => $aNoticeAttributes,
					'strings'           => [
						'title'                   => 'Akismet is Running',
						'appears_running_akismet' => __( 'It appears you have Akismet Anti-SPAM running alongside the our human Anti-SPAM filter.', 'wp-simple-firewall' ),
						'not_recommended'         => __( 'This is not recommended and you should disable Akismet.', 'wp-simple-firewall' ),
						'click_to_deactivate'     => __( 'Click to deactivate Akismet now.', 'wp-simple-firewall' ),
					],
					'hrefs'             => [
						'deactivate' => $oWpPlugins->getUrl_Deactivate( $sPluginFile )
					]
				];
				$this->insertAdminNotice( $aRenderData );
			}
		}
	}

	/**
	 * When you set a new comment as anything but 'spam' a notification email is sent to the post author.
	 * We suppress this for when we mark as trash by emptying the email notifications list.
	 * @param array $aEmails
	 * @return array
	 */
	public function clearCommentNotificationEmail( $aEmails ) {
		$sStatus = apply_filters( $this->getMod()->prefix( 'cf_status' ), '' );
		if ( in_array( $sStatus, [ 'reject', 'trash' ] ) ) {
			$aEmails = [];
		}
		return $aEmails;
	}
}