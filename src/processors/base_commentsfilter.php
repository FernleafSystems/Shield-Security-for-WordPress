<?php

if ( class_exists( 'ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_CommentsFilter_Base extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var array
	 */
	static protected $aRawCommentData;

	/**
	 * @var string
	 */
	static protected $sCommentStatus;

	/**
	 * @var string
	 */
	static protected $sCommentStatusExplanation = '';

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();
		self::$sCommentStatus = '';
		self::$sCommentStatusExplanation = '';
	}

	/**
	 */
	public function run() {
		$oFO = $this->getFeature();
		add_filter( 'preprocess_comment', array( $this, 'doCommentChecking' ), 1, 1 );
		add_filter( $oFO->prefix( 'cf_status' ), array( $this, 'getCommentStatus' ), 1 );
		add_filter( $oFO->prefix( 'cf_status_expl' ), array( $this, 'getCommentStatusExplanation' ), 1 );
	}

	/**
	 * @param array $aCommentData
	 * @return array
	 */
	public function doCommentChecking( $aCommentData ) {
		if ( empty( self::$aRawCommentData ) ) {
			self::$aRawCommentData = $aCommentData;
		}
		return $aCommentData;
	}

	/**
	 * A private plugin filter that lets us return up the newly set comment status.
	 * @param $sCurrentCommentStatus
	 * @return string
	 */
	public function getCommentStatus( $sCurrentCommentStatus ) {
		return empty( $sCurrentCommentStatus ) ? self::$sCommentStatus : $sCurrentCommentStatus;
	}

	/**
	 * @return string
	 */
	protected function getExpanation() {
		return self::$sCommentStatusExplanation;
	}

	/**
	 * @param string $sKey
	 * @return string|array|null
	 */
	protected function getRawCommentData( $sKey = '' ) {
		if ( !isset( self::$aRawCommentData ) ) {
			self::$aRawCommentData = array();
		}
		if ( !empty( $sKey ) ) {
			return isset( self::$aRawCommentData[ $sKey ] ) ? self::$aRawCommentData[ $sKey ] : null;
		}
		return self::$aRawCommentData;
	}

	/**
	 * @return string
	 */
	protected function getStatus() {
		return self::$sCommentStatus;
	}

	/**
	 * A private plugin filter that lets us return up the newly set comment status explanation
	 * @param $sCurrentCommentStatusExplanation
	 * @return string
	 */
	public function getCommentStatusExplanation( $sCurrentCommentStatusExplanation ) {
		return empty( $sCurrentCommentStatusExplanation ) ? self::$sCommentStatusExplanation : $sCurrentCommentStatusExplanation;
	}

	/**
	 * @param string $sStatus
	 * @return $this
	 */
	protected function setCommentStatus( $sStatus ) {
		self::$sCommentStatus = $sStatus;
		return $this;
	}

	/**
	 * @param $sExplanation
	 */
	protected function setCommentStatusExplanation( $sExplanation ) {
		self::$sCommentStatusExplanation =
			'[* '.sprintf(
				_wpsf__( '%s plugin marked this comment as "%s".' ).' '._wpsf__( 'Reason: %s' ),
				$this->getController()->getHumanName(),
				self::$sCommentStatus,
				$sExplanation
			)." *]\n";
	}
}