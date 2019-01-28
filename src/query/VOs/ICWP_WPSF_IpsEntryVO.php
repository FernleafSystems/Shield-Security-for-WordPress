<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

/**
 * @deprecated v7.0.0
 * Class ICWP_WPSF_IpsEntryVO
 * @property string ip
 * @property bool   is_range
 * @property string label
 * @property string list
 * @property int    last_access_at
 * @property int    transgressions
 */
class ICWP_WPSF_IpsEntryVO extends ICWP_WPSF_BaseEntryVO {

	/**
	 * @return string
	 */
	public function getIp() {
		return (string)$this->ip;
	}

	/**
	 * @return string
	 */
	public function getLabel() {
		return (string)$this->label;
	}

	/**
	 * @return int
	 */
	public function getLastAccessAt() {
		return (int)$this->last_access_at;
	}

	/**
	 * @return string
	 */
	public function getList() {
		return (string)$this->list;
	}

	/**
	 * @return int
	 */
	public function getTransgressions() {
		return (int)$this->transgressions;
	}

	/**
	 * @return bool
	 */
	public function hasTransgressions() {
		return $this->getTransgressions() > 0;
	}

	/**
	 * @return bool
	 */
	public function isIpRange() {
		return (bool)$this->is_range;
	}
}