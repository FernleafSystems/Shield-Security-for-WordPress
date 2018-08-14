<?php

if ( class_exists( 'ICWP_WPSF_Query_BaseUpdate', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_query.php' );

class ICWP_WPSF_Query_BaseUpdate extends ICWP_WPSF_Query_BaseQuery {

	/**
	 * @var array
	 */
	protected $aUpdateData;

	/**
	 * @var array
	 */
	protected $aUpdateWheres;

	/**
	 * @return array
	 */
	public function getUpdateWheres() {
		return is_array( $this->aUpdateWheres ) ? $this->aUpdateWheres : array();
	}

	/**
	 * @param array $aUpdateWheres
	 * @return $this
	 */
	public function setUpdateWheres( $aUpdateWheres ) {
		$this->aUpdateWheres = $aUpdateWheres;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getUpdateData() {
		return is_array( $this->aUpdateData ) ? $this->aUpdateData : array();
	}

	/**
	 * @param array $aSetData
	 * @return $this
	 */
	public function setUpdateData( $aSetData ) {
		$this->aUpdateData = $aSetData;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function query() {
		$mResult = $this->loadDbProcessor()
						->updateRowsFromTableWhere(
							$this->getTable(),
							$this->getUpdateData(),
							$this->getUpdateWheres()
						);
		return is_numeric( $mResult );
	}

	/**
	 * Offset never applies to DELETE
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return '';
	}
}