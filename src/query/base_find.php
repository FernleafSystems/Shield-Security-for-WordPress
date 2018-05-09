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
	 * @var array
	 */
	protected $aColumns;

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
	 * @return array
	 */
	public function getColumns() {
		if ( empty( $this->aColumns ) || !is_array( $this->aColumns ) ) {
			$this->aColumns = array( 'wp_username', 'message' );
		}
		return $this->aColumns;
	}

	/**
	 * @return bool
	 */
	public function isResultsAsVo() {
		return $this->bResultsAsVo;
	}

	/**
	 * @param array $aColumns
	 * @return $this
	 */
	public function setColumns( $aColumns ) {
		$this->aColumns = $aColumns;
		return $this;
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