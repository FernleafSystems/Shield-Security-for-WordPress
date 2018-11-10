<?php

/**
 * Class ICWP_WPSF_BaseEntryVO
 * @property int created_at
 * @property int deleted_at
 * @property int id
 */
class ICWP_WPSF_BaseEntryVO {

	/**
	 * @var array
	 */
	protected $aRecord;

	/**
	 * @param array $oRowData
	 */
	public function __construct( $oRowData = null ) {
		$this->aRecord = is_array( $oRowData ) ? $oRowData : array();
	}

	/**
	 * @return int
	 */
	public function getCreatedAt() {
		return (int)$this->created_at;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return (int)$this->id;
	}

	/**
	 * @return bool
	 */
	public function isDeleted() {
		return $this->deleted_at > 0;
	}

	/**
	 * @return array
	 */
	public function getRawData() {
		if ( !is_array( $this->aRecord ) ) {
			$this->aRecord = array();
		}
		return $this->aRecord;
	}

	/**
	 * @param array $aRow
	 * @return $this
	 */
	public function setRawData( $aRow ) {
		$this->aRecord = $aRow;
		return $this;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	public function __get( $sKey ) {
		return $this->getRawKey( $sKey );
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function __isset( $sKey ) {
		$aR = $this->getRawData();
		return isset( $aR[ $sKey ] );
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function __set( $sKey, $mValue ) {
		$aR = $this->getRawData();
		$aR[ $sKey ] = $mValue;
		return $this->setRawData( $aR );
	}

	/**
	 * Use this to by-pass __get() to prevent infinite loops.
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getRawKey( $sKey ) {
		$aR = $this->getRawData();
		return isset( $aR[ $sKey ] ) ? $aR[ $sKey ] : null;
	}
}