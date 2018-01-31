<?php

class ICWP_WPSF_SessionVO {

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
	public function getBrowser() {
		return $this->getRowData()->browser;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->getRowData()->session_id;
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
	public function getLastActivityAt() {
		return $this->getRowData()->last_activity_at;
	}

	/**
	 * @return int
	 */
	public function getLoggedInAt() {
		return $this->getRowData()->logged_in_at;
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
	public function getSecAdminAt() {
		return $this->getRowData()->secadmin_at;
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