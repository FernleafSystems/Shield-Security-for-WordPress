<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

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
	 * @param int $nId
	 * @return $this
	 */
	public function setUpdateId( $nId ) {
		$this->aUpdateWheres = array( 'id' => $nId );
		return $this;
	}

	/**
	 * @param ICWP_WPSF_BaseEntryVO $oEntry
	 * @param array                 $aUpdateData
	 * @return bool
	 */
	public function updateEntry( $oEntry, $aUpdateData = array() ) {
		return false;
	}
}