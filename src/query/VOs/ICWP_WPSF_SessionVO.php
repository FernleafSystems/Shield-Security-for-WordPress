<?php

require_once( dirname( __FILE__ ).'/ICWP_WPSF_BaseEntryVO.php' );

/**
 * Class ICWP_WPSF_SessionVO
 * @property string ip
 * @property string browser
 * @property string wp_username
 * @property int    last_activity_at
 * @property int    logged_in_at
 * @property int    login_intent_expires_at
 * @property string li_code_email
 * @property string session_id
 * @property int    secadmin_at
 */
class ICWP_WPSF_SessionVO extends ICWP_WPSF_BaseEntryVO {

	/**
	 * @return string
	 */
	public function getBrowser() {
		return (string)$this->browser;
	}

	/**
	 * @return string
	 */
	public function getIp() {
		return (string)$this->ip;
	}

	/**
	 * @return int
	 */
	public function getLastActivityAt() {
		return (int)$this->last_activity_at;
	}

	/**
	 * @return int
	 */
	public function getLoggedInAt() {
		return (int)$this->logged_in_at;
	}

	/**
	 * @return int
	 */
	public function getLoginIntentExpiresAt() {
		return (int)$this->login_intent_expires_at;
	}

	/**
	 * @return string
	 */
	public function getLoginIntentCodeEmail() {
		return (string)$this->li_code_email;
	}

	/**
	 * @return string
	 */
	public function getSessionId() {
		return (string)$this->session_id;
	}

	/**
	 * @return int
	 */
	public function getSecAdminAt() {
		return (int)$this->secadmin_at;
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return (string)$this->wp_username;
	}
}