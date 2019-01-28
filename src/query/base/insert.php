<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_BaseInsert extends ICWP_WPSF_Query_BaseQuery {

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
	 * @param ICWP_WPSF_BaseEntryVO $oEntry
	 * @return bool
	 */
	public function insert( $oEntry ) {
		return false;
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
	 * Offset never applies
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return '';
	}
}