<?php

class ICWP_WPSF_AuditTrailEntryVO {

	/**
	 * @var stdClass
	 */
	protected $oRowData;

	/**
	 * @param stdClass $oRowData
	 */
	public function __construct( $oRowData ) {
		$this->oRowData = $oRowData;
	}

	/**
	 * @return int
	 */
	public function getCreatedAt() {
		return $this->getRowData()->created_at;
	}

	/**
	 * @return string
	 */
	public function getIp() {
		return $this->getRowData()->ip;
	}

	/**
	 * @return int
	 */
	public function getMessage() {
		return $this->getRowData()->message;
	}

	/**
	 * @return int
	 */
	public function getUsername() {
		return $this->getRowData()->wp_username;
	}

	/**
	 * @return int
	 */
	public function isDeleted() {
		return $this->getRowData()->deleted_at > 0;
	}

	/**
	 * @return stdClass
	 */
	public function getRowData() {
		return $this->oRowData;
	}

	/**
	 * @param stdClass $oRowData
	 * @return $this
	 */
	public function setRowData( $oRowData ) {
		$this->oRowData = $oRowData;
		return $this;
	}
}