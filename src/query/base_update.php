<?php

if ( class_exists( 'ICWP_WPSF_Query_BaseUpdate', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_insert.php' );

class ICWP_WPSF_Query_BaseUpdate extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @var array
	 */
	protected $aUpdateWheres;

	/**
	 * @return array
	 */
	public function getUpdateData() {
		return $this->getInsertData();
	}

	/**
	 * @return array
	 */
	public function getUpdateWheres() {
		return is_array( $this->aUpdateWheres ) ? $this->aUpdateWheres : array();
	}

	/**
	 * @param array $aSetData
	 * @return $this
	 */
	public function setUpdateData( $aSetData ) {
		return $this->setInsertData( $aSetData );
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
	 * @return int|false
	 */
	public function query() {
		return $this->loadDbProcessor()
					->updateRowsFromTableWhere(
						$this->getTable(),
						$this->getUpdateData(),
						$this->getUpdateWheres()
					);
	}
}