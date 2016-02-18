<?php

if ( !class_exists( 'ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base_wpsf.php' );

class ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha extends ICWP_WPSF_Processor_BaseWpsf {
	/**
	 * @var string
	 */
	protected $sCommentStatus;
	/**
	 * @var string
	 */
	protected $sCommentStatusExplanation = '';

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getFeatureOptions();
		if ( !$this->loadDataProcessor()->getPhpSupportsNamespaces() || !$oFO->getIsGoogleRecaptchaReady() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts',		array( $this, 'loadGoogleRecaptchaJs' ), 99 );
		add_action( 'comment_form',				array( $this, 'printGoogleRecaptchaCheck' ) );
		add_filter( 'preprocess_comment',		array( $this, 'doCommentChecking' ), 1, 1 );

		add_filter( $this->getFeatureOptions()->doPluginPrefix( 'comments_filter_status' ), array( $this, 'getCommentStatus' ), 1 );
		add_filter( $this->getFeatureOptions()->doPluginPrefix( 'comments_filter_status_explanation' ), array( $this, 'getCommentStatusExplanation' ), 1 );
	}

	public function loadGoogleRecaptchaJs() {
		wp_register_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js' );
		wp_enqueue_script( 'google-recaptcha' );
	}

	/**
	 * @return string
	 */
	public function printGoogleRecaptchaCheck_Filter() {
		return $this->getGoogleRecaptchaHtml();
	}

	/**
	 */
	public function printGoogleRecaptchaCheck() {
		echo $this->getGoogleRecaptchaHtml();
	}

	/**
	 * @return string
	 */
	protected function getGoogleRecaptchaHtml() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getFeatureOptions();
		$sSiteKey = $oFO->getGoogleRecaptchaSiteKey();
		return sprintf(
			'%s<div class="g-recaptcha" data-sitekey="%s" style="margin: 10px 0;"></div>',
			'<style>@media screen and (max-height: 575px){
#rc-imageselect, .g-recaptcha iframe {transform:scale(0.90);-webkit-transform:scale(0.90);transform-origin:0 0;-webkit-transform-origin:0 0;}</style>',
			$sSiteKey
		);
	}

	/**
	 * @param array $aCommentData
	 * @return array
	 */
	public function doCommentChecking( $aCommentData ) {

		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getFeatureOptions();
		if ( !$oFO->getIfDoCommentsCheck() ) {
			return $aCommentData;
		}

		$sCaptchaResponse = $this->loadDataProcessor()->FetchPost( 'g-recaptcha-response' );

		$bIsSpam = false;
		$sStatKey = '';
		$sExplanation = '';
		if ( empty( $sCaptchaResponse ) ) {
			$bIsSpam = true;
			$sStatKey = 'empty';
			$sExplanation = _wpsf__( 'Google Recaptcha was not submitted.' );
		}
		else {
			$oRecaptcha = $this->loadGoogleRecaptcha()->getGoogleRecaptchaLib( $oFO->getGoogleRecaptchaSecretKey() );
			$oResponse = $oRecaptcha->verify( $sCaptchaResponse, $this->human_ip() );
			if ( empty( $oResponse ) || !$oResponse->isSuccess() ) {
				$bIsSpam = true;
				$sStatKey = 'failed';
				$sExplanation = _wpsf__( 'Google Recaptcha verification failed.' );
			}
		}

		// Now we check whether comment status is to completely reject and then we simply redirect to "home"

		if ( $bIsSpam ) {
			$this->doStatIncrement( sprintf( 'spam.recaptcha.%s', $sStatKey ) );
			$this->sCommentStatus = $this->getOption( 'comments_default_action_spam_bot' );
			$this->setCommentStatusExplanation( $sExplanation );

			// We now black mark this IP
			add_filter( $oFO->doPluginPrefix( 'ip_black_mark' ), '__return_true' );
		}

		if ( $this->getOption( 'comments_default_action_spam_bot' ) == 'reject' ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oWp->doRedirect( $oWp->getHomeUrl(), array(), true, false );
		}

		return $aCommentData;
	}

	/**
	 * A private plugin filter that lets us return up the newly set comment status.
	 *
	 * @param $sCurrentCommentStatus
	 * @return string
	 */
	public function getCommentStatus( $sCurrentCommentStatus ) {
		return empty( $sCurrentCommentStatus )? $this->sCommentStatus : $sCurrentCommentStatus;
	}

	/**
	 * A private plugin filter that lets us return up the newly set comment status explanation
	 *
	 * @param $sCurrentCommentStatusExplanation
	 * @return string
	 */
	public function getCommentStatusExplanation( $sCurrentCommentStatusExplanation ) {
		return empty( $sCurrentCommentStatusExplanation )? $this->sCommentStatusExplanation : $sCurrentCommentStatusExplanation;
	}

	/**
	 * @param $sExplanation
	 */
	protected function setCommentStatusExplanation( $sExplanation ) {
		$this->sCommentStatusExplanation =
			'[* '.sprintf(
				_wpsf__('%s plugin marked this comment as "%s".').' '._wpsf__( 'Reason: %s' ),
				$this->getController()->getHumanName(),
				$this->sCommentStatus,
				$sExplanation
			)." *]\n";
	}
}
endif;