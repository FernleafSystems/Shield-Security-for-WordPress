<?php

if ( class_exists( 'ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_commentsfilter.php' );

class ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha extends ICWP_WPSF_Processor_CommentsFilter_Base {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();
		if ( !$oFO->getIsGoogleRecaptchaReady() ) {
			return;
		}
		parent::run();

		add_action( 'wp', array( $this, 'setup' ) );
	}

	/**
	 * The WP Query is alive and well at this stage so we can assume certain data is available.
	 */
	public function setup() {
		if ( $this->loadWpComments()->isCommentsOpen() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'registerGoogleRecaptchaJs' ), 99 );
			add_action( 'comment_form_after_fields', array( $this, 'printGoogleRecaptchaCheck' ) );
		}
	}

	/**
	 * @return string
	 */
	public function printGoogleRecaptchaCheck_Filter() {
		$this->setRecaptchaToEnqueue();
		return $this->getGoogleRecaptchaHtml();
	}

	/**
	 */
	public function printGoogleRecaptchaCheck() {
		$this->setRecaptchaToEnqueue();
		echo $this->getGoogleRecaptchaHtml();
	}

	/**
	 * @return string
	 */
	protected function getGoogleRecaptchaHtml() {
		return '<div class="icwpg-recaptcha" style="margin: 10px 0; clear:both;"></div>';
	}

	/**
	 * @param array $aCommentData
	 * @return array
	 */
	public function doCommentChecking( $aCommentData ) {
		parent::doCommentChecking( $aCommentData );

		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getMod();
		if ( !$oFO->getIfDoCommentsCheck() ) {
			return $aCommentData;
		}

		$bIsSpam = false;
		$sStatKey = '';
		$sExplanation = '';
		try {
			$this->checkRequestRecaptcha();
		}
		catch ( Exception $oE ) {
			$sStatKey = ( $oE->getCode() == 1 ) ? 'empty' : 'failed';
			$sExplanation = $oE->getMessage();
			$bIsSpam = true;
		}

		// Now we check whether comment status is to completely reject and then we simply redirect to "home"

		if ( $bIsSpam ) {
			$this->doStatIncrement( sprintf( 'spam.recaptcha.%s', $sStatKey ) );
			self::$sCommentStatus = $this->getOption( 'comments_default_action_spam_bot' );
			$this->setCommentStatusExplanation( $sExplanation );

			$oFO->setOptInsightsAt( 'last_comment_block_at' );
			$this->setIpTransgressed(); // black mark this IP


			if ( self::$sCommentStatus == 'reject' ) {
				$oWp = $this->loadWp();
				$oWp->doRedirect( $oWp->getHomeUrl(), array(), true, false );
			}
		}
		return $aCommentData;
	}
}