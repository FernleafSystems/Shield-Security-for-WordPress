<?php

if ( class_exists( 'ICWP_WPSF_Query_Base_Find', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_Base_Find extends ICWP_WPSF_Query_Base {

	/**
	 * @var string
	 */
	protected $sTerm;

	/**
	 * @var bool
	 */
	protected $bResultsAsVo;

	/**
	 * @return string
	 */
	public function getTerm() {
		return $this->sTerm;
	}

	/**
	 * @return bool
	 */
	public function isResultsAsVo() {
		return $this->bResultsAsVo;
	}

	/**
	 * @param bool $bResultsAsVo
	 * @return $this
	 */
	public function setResultsAsVo( $bResultsAsVo ) {
		$this->bResultsAsVo = $bResultsAsVo;
		return $this;
	}

	/**
	 * @param string $sTerm
	 * @return $this
	 */
	public function setTerm( $sTerm ) {
		$this->sTerm = $sTerm;
		return $this;
	}
}