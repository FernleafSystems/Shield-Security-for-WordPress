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
		return (string)$this->sTerm;
	}

	/**
	 * @return array
	 */
	public function getColumns() {
		if ( empty( $this->aColumns ) || !is_array( $this->aColumns ) ) {
			$this->aColumns = $this->getDefaultColumns();
		}
		return $this->aColumns;
	}

	/**
	 * @return array
	 */
	protected function getDefaultColumns() {
		return array( 'wp_username', 'message' );
	}

	/**
	 * @return bool
	 */
	public function hasSearchTerm() {
		return strlen( $this->getTerm() ) > 0;
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
	 * @param string $sTerm
	 * @return $this
	 */
	public function setTerm( $sTerm ) {
		$this->sTerm = $sTerm;
		return $this;
	}
}