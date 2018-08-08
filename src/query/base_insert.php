<?php

if ( class_exists( 'ICWP_WPSF_Query_BaseInsert', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_BaseInsert extends ICWP_WPSF_Query_Base {

	/**
	 * @var array
	 */
	protected $aInsertData;

	/**
	 * @return array
	 */
	public function getInsertData() {
		return is_array( $this->aInsertData ) ? $this->aInsertData : array();
	}

	/**
	 * @param array $aInsertData
	 * @return $this
	 */
	public function setInsertData( $aInsertData ) {
		$this->aInsertData = $aInsertData;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function query() {
		$mResult = $this->loadDbProcessor()
						->insertDataIntoTable( $this->getTable(), $this->getInsertData() );
		return ( $mResult === false ) ? false : $mResult > 0;
	}
}