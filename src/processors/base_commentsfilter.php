<?php

class ICWP_WPSF_Processor_CommentsFilter_Base extends ICWP_WPSF_Processor_BaseWpsf {

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
		$oFO = $this->getMod();
		add_filter( 'preprocess_comment', array( $this, 'doCommentChecking' ), 5 );
		add_filter( $oFO->prefix( 'cf_status' ), array( $this, 'getCommentStatus' ), 1 );
		add_filter( $oFO->prefix( 'cf_status_expl' ), array( $this, 'getCommentStatusExplanation' ), 1 );
	}

	/**
	 * @param array $aCommentData
	 * @return array
	 */
	public function doCommentChecking( $aCommentData ) {
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
				$this->getCon()->getHumanName(),
				self::$sCommentStatus,
				$sExplanation
			)." *]\n";
	}
}