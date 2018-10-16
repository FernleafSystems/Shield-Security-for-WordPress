<?php

require_once( dirname( dirname( __FILE__ ) ).'/base/ICWP_WPSF_BaseEntryVO.php' );

/**
 * Class ICWP_WPSF_AuditTrailEntryVO
 * @property string ip
 * @property string message
 * @property string wp_username
 */
class ICWP_WPSF_AuditTrailEntryVO extends ICWP_WPSF_BaseEntryVO {

	/**
	 * @return string
	 */
	public function getIp() {
		return (string)$this->ip;
	}

	/**
	 * @return string
	 */
	public function getMessage() {
		return (string)$this->message;
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return (string)$this->wp_username;
	}
}