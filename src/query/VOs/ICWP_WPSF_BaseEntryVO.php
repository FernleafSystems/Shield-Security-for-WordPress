<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

/**
 * @deprecated v7.0.0
 * Class ICWP_WPSF_BaseEntryVO
 * @property int created_at
 * @property int deleted_at
 * @property int id
 */
class ICWP_WPSF_BaseEntryVO {

	/**
	 * @var stdClass
	 */
	protected $oRowData;

	/**
	 * @param stdClass $oRowData
	 */
	public function __construct( $oRowData = null ) {
		$this->oRowData = ( $oRowData instanceof stdClass ) ? $oRowData : new stdClass();
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
	 * @return stdClass
	 */
	public function getRawData() {
		return $this->oRowData;
	}

	/**
	 * @param stdClass $oRowData
	 * @return $this
	 */
	public function setRawData( $oRowData ) {
		$this->oRowData = $oRowData;
		return $this;
	}

	/**
	 * @param string $sKey
	 * @return mixed
	 */
	public function __get( $sKey ) {
		$oD = $this->getRawData();
		return isset( $oD->{$sKey} ) ? $oD->{$sKey} : null;
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function __isset( $sKey ) {
		return isset( $this->getRawData()->{$sKey} );
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function __set( $sKey, $mValue ) {
		$this->getRawData()->{$sKey} = $mValue;
		return $this;
	}
}