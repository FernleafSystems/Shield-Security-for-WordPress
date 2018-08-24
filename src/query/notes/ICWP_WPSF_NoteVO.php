<?php

class ICWP_WPSF_NoteVO {

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
	 * @return int
	 */
	public function getId() {
		return $this->getRowData()->id;
	}

	/**
	 * @return string
	 */
	public function getNote() {
		return $this->getRowData()->note;
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