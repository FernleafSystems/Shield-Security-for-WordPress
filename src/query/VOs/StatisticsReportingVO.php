<?php

class StatisticsReportingVO {

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
	 * @return int
	 */
	public function getKey() {
		return $this->getRowData()->stat_key;
	}

	/**
	 * @return int
	 */
	public function getTally() {
		return $this->getRowData()->tally;
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
	 * @return StatisticsReportingVO
	 */
	public function setRowData( $oRowData ) {
		$this->oRowData = $oRowData;
		return $this;
	}
}