<?php

if ( class_exists( 'ICWP_WPSF_Query_Base', false ) ) {
	return;
}

class ICWP_WPSF_Query_Base extends ICWP_WPSF_Foundation {

	/**
	 * @var string
	 */
	protected $sTable;

	/**
	 * @return string
	 */
	public function getTable() {
		return $this->sTable;
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