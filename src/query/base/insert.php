<?php

if ( class_exists( 'ICWP_WPSF_Query_BaseInsert', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/query.php' );

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
		$oDP = $this->loadDP();
		$aData = array_merge(
			array(
				'created_at' => $oDP->time(),
			),
			$oDP->convertStdClassToArray( $oEntry->getRawData() )
		);
		return $this->setInsertData( $aData )->query() === 1;
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
	 * @return false|int
	 */
	public function query() {
		return $this->loadDbProcessor()
					->insertDataIntoTable(
						$this->getTable(),
						$this->getInsertData()
					);
	}

	/**
	 * Offset never applies
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return '';
	}
}