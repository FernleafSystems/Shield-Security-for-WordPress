<?php

if ( class_exists( 'ICWP_WPSF_Query_Base', false ) ) {
	return;
}

class ICWP_WPSF_Query_Base extends ICWP_WPSF_Foundation {

	/**
	 * @var bool
	 */
	protected $bResultsAsVo;

	/**
	 * @var int
	 */
	protected $nLimit = 0;

	/**
	 * @var string
	 */
	protected $sTable;

	/**
	 * @return int
	 */
	public function getLimit() {
		return max( (int)$this->nLimit, 0 );
	}

	/**
	 * @return string
	 */
	public function getTable() {
		return $this->sTable;
	}

	/**
	 * @return bool
	 */
	public function hasLimit() {
		return $this->getLimit() > 0;
	}

	/**
	 * @return bool
	 */
	public function isResultsAsVo() {
		return $this->bResultsAsVo;
	}

	/**
	 * @param int $nLimit
	 * @return $this
	 */
	public function setLimit( $nLimit ) {
		$this->nLimit = $nLimit;
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
	 * @param string $sTable
	 * @return $this
	 */
	public function setTable( $sTable ) {
		$this->sTable = $sTable;
		return $this;
	}
}