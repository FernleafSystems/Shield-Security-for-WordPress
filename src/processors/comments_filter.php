<?php

if ( class_exists( 'ICWP_WPSF_Processor_CommentsFilter', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_CommentsFilter extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();
		add_filter( $oFO->prefix( 'if-do-comments-check' ), array( $this, 'getIfDoCommentsCheck' ) );

		if ( $oFO->isEnabledGaspCheck() ) {
			require_once( dirname( __FILE__ ).'/commentsfilter_antibotspam.php' );
			$oBotSpamProcessor = new ICWP_WPSF_Processor_CommentsFilter_AntiBotSpam( $oFO );
			$oBotSpamProcessor->run();
		}

		if ( $this->getIsOption( 'enable_comments_human_spam_filter', 'Y' ) && $this->loadWpComments()
																					->isCommentPost() ) {
			require_once( dirname( __FILE__ ).'/commentsfilter_humanspam.php' );
			$oHumanSpamProcessor = new ICWP_WPSF_Processor_CommentsFilter_HumanSpam( $oFO );
			$oHumanSpamProcessor->run();
		}

		if ( $oFO->getIsGoogleRecaptchaEnabled() ) {
			require_once( dirname( __FILE__ ).'/commentsfilter_googlerecaptcha.php' );
			$oHumanSpamProcessor = new ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha( $oFO );
			$oHumanSpamProcessor->run();
		}

		add_filter( 'pre_comment_approved', array( $this, 'doSetCommentStatus' ), 1 );
		add_filter( 'pre_comment_content', array( $this, 'doInsertCommentStatusExplanation' ), 1, 1 );
		add_filter( 'comment_notification_recipients', array(
			$this,
			'doClearCommentNotificationEmail_Filter'
		), 100, 1 );
	}

	/**
	 * Always default to true, and if false, return that.
	 * @param boolean $bDoCheck
	 * @return boolean
	 */
	public function getIfDoCommentsCheck( $bDoCheck ) {
		return $bDoCheck && !$this->loadWpUsers()->isUserLoggedIn();
	}

	/**
	 * @param array $aNoticeAttributes
	 */
	protected function addNotice_akismet_running( $aNoticeAttributes ) {

		// We only warn when the human spam filter is running
		if ( $this->getIsOption( 'enable_comments_human_spam_filter', 'Y' ) && $this->getController()
																					->getIsValidAdminArea() ) {

			$oWpPlugins = $this->loadWpPlugins();
			$sPluginFile = $oWpPlugins->findPluginBy( 'Akismet', 'Name' );
			if ( $oWpPlugins->isActive( $sPluginFile ) ) {
				$aRenderData = array(
					'notice_attributes' => $aNoticeAttributes,
					'strings'           => array(
						'title' => 'Akismet is Running',
						'appears_running_akismet' => _wpsf__( 'It appears you have Akismet Anti-SPAM running alongside the our human Anti-SPAM filter.' ),
						'not_recommended' => _wpsf__( 'This is not recommended and you should disable Akismet.' ),
						'click_to_deactivate' => _wpsf__( 'Click to deactivate Akismet now.' ),
					),
					'hrefs'             => array(
						'deactivate' => $oWpPlugins->getLinkPluginDeactivate( $sPluginFile )
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
	public function doClearCommentNotificationEmail_Filter( $aEmails ) {
		$sStatus = apply_filters( $this->getMod()->prefix( 'cf_status' ), '' );
		if ( in_array( $sStatus, array( 'reject', 'trash' ) ) ) {
			$aEmails = array();
		}
		return $aEmails;
	}
}