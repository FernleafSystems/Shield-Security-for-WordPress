<?php

class ICWP_WPSF_Processor_CommentsFilter extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
	}

	public function onWpInit() {
		parent::onWpInit();

		if ( $this->loadWpUsers()->isUserLoggedIn() ) {
			return;
		}

		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isEnabledGaspCheck() ) {
			require_once( __DIR__.'/commentsfilter_antibotspam.php' );
			$oBotSpamProcessor = new ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam( $oFO );
			$oBotSpamProcessor->run();
		}

		if ( $oFO->isEnabledHumanCheck() && $this->loadWpComments()->isCommentPost() ) {
			require_once( __DIR__.'/commentsfilter_humanspam.php' );
			$oHumanSpamProcessor = new ICWP_WPSF_Processor_CommentsFilter_HumanSpam( $oFO );
			$oHumanSpamProcessor->run();
		}

		if ( $oFO->isGoogleRecaptchaEnabled() ) {
			require_once( __DIR__.'/commentsfilter_googlerecaptcha.php' );
			$oReCap = new ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha( $oFO );
			$oReCap->run();
		}

		add_filter( 'pre_comment_approved', array( $this, 'doSetCommentStatus' ), 1 );
		add_filter( 'pre_comment_content', array( $this, 'doInsertCommentStatusExplanation' ), 1, 1 );
		add_filter( 'comment_notification_recipients', array( $this, 'clearCommentNotificationEmail' ), 100, 1 );
	}

	/**
	 * @param array $aNoticeAttributes
	 */
	protected function addNotice_akismet_running( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();

		// We only warn when the human spam filter is running
		if ( $oFO->isEnabledHumanCheck() ) {

			$oWpPlugins = $this->loadWpPlugins();
			$sPluginFile = $oWpPlugins->findPluginBy( 'Akismet', 'Name' );
			if ( $oWpPlugins->isActive( $sPluginFile ) ) {
				$aRenderData = array(
					'notice_attributes' => $aNoticeAttributes,
					'strings'           => array(
						'title'                   => 'Akismet is Running',
						'appears_running_akismet' => _wpsf__( 'It appears you have Akismet Anti-SPAM running alongside the our human Anti-SPAM filter.' ),
						'not_recommended'         => _wpsf__( 'This is not recommended and you should disable Akismet.' ),
						'click_to_deactivate'     => _wpsf__( 'Click to deactivate Akismet now.' ),
					),
					'hrefs'             => array(
						'deactivate' => $oWpPlugins->getUrl_Deactivate( $sPluginFile )
					)
				);
				$this->insertAdminNotice( $aRenderData );
			}
		}
	}

	/**
	 * We set the final approval status of the comments if we've set it in our scans, and empties the notification email
	 * in case we "trash" it (since WP sends out a notification email if it's anything but SPAM)
	 * @param $sApprovalStatus
	 * @return string
	 */
	public function doSetCommentStatus( $sApprovalStatus ) {
		$sStatus = apply_filters( $this->getMod()->prefix( 'cf_status' ), '' );
		return empty( $sStatus ) ? $sApprovalStatus : $sStatus;
	}

	/**
	 * @param string $sCommentContent
	 * @return string
	 */
	public function doInsertCommentStatusExplanation( $sCommentContent ) {

		$sExplanation = apply_filters( $this->getMod()->prefix( 'cf_status_expl' ), '' );

		// If either spam filtering process left an explanation, we add it here
		if ( !empty( $sExplanation ) ) {
			$sCommentContent = $sExplanation.$sCommentContent;
		}
		return $sCommentContent;
	}

	/**
	 * When you set a new comment as anything but 'spam' a notification email is sent to the post author.
	 * We suppress this for when we mark as trash by emptying the email notifications list.
	 * @param array $aEmails
	 * @return array
	 */
	public function clearCommentNotificationEmail( $aEmails ) {
		$sStatus = apply_filters( $this->getMod()->prefix( 'cf_status' ), '' );
		if ( in_array( $sStatus, array( 'reject', 'trash' ) ) ) {
			$aEmails = array();
		}
		return $aEmails;
	}
}