<?php

if ( class_exists( 'ICWP_WPSF_Query_Base_Find', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_Base_Find extends ICWP_WPSF_Query_Base {

	/**
	 * @var int
	 */
	protected $nLimit = 0;

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
	 * @return int
	 */
	public function getLimit() {
		return max( (int)$this->nLimit, 0 );
	}

	/**
	 * @return bool
	 */
	public function hasLimit() {
		return $this->getLimit() > 0;
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
	public function hasSearchTerm() {
		return strlen( $this->getTerm() ) > 0;
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

	/**
	 * @param int $nLimit
	 * @return $this
	 */
	public function setLimit( $nLimit ) {
		$this->nLimit = $nLimit;
		return $this;
	}
}