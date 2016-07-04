<?php

if ( !class_exists( 'ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha', false ) ):

require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_commentsfilter.php' );

class ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha extends ICWP_WPSF_Processor_CommentsFilter_Base {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oFO */
		$oFO = $this->getFeatureOptions();
		if ( !$oFO->getIsGoogleRecaptchaReady() ) {
			return;
		}

		parent::run();

		add_action( 'wp_enqueue_scripts',		array( $this, 'loadGoogleRecaptchaJs' ), 99 );
		add_action( 'comment_form',				array( $this, 'printGoogleRecaptchaCheck' ) );
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
			'<div class="g-recaptcha" data-sitekey="%s" style="margin: 10px 0;"></div>',
			$sSiteKey
		);
	}

	/**
	 * @param array $aCommentData
	 * @return array
	 */
	public function doCommentChecking( $aCommentData ) {
		parent::doCommentChecking( $aCommentData );

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
			$sExplanation = _wpsf__( 'Google reCAPTCHA was not submitted.' );
		}
		else {
			$oRecaptcha = $this->loadGoogleRecaptcha()->getGoogleRecaptchaLib( $oFO->getGoogleRecaptchaSecretKey() );
			$oResponse = $oRecaptcha->verify( $sCaptchaResponse, $this->human_ip() );
			if ( empty( $oResponse ) || !$oResponse->isSuccess() ) {
				$bIsSpam = true;
				$sStatKey = 'failed';
				$sExplanation = _wpsf__( 'Google reCAPTCHA verification failed.' );
			}
		}

		// Now we check whether comment status is to completely reject and then we simply redirect to "home"

		if ( $bIsSpam ) {
			$this->doStatIncrement( sprintf( 'spam.recaptcha.%s', $sStatKey ) );
			self::$sCommentStatus = $this->getOption( 'comments_default_action_spam_bot' );
			$this->setCommentStatusExplanation( $sExplanation );

			// We now black mark this IP
			add_filter( $oFO->doPluginPrefix( 'ip_black_mark' ), '__return_true' );

			if ( self::$sCommentStatus == 'reject' ) {
				$oWp = $this->loadWpFunctionsProcessor();
				$oWp->doRedirect( $oWp->getHomeUrl(), array(), true, false );
			}
		}
		return $aCommentData;
	}
}
endif;