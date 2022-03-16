<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * @property string ip
 * @property string browser
 * @property string wp_username
 * @property int    last_activity_at
 * @property int    logged_in_at
 * @property string session_id
 * @property int    secadmin_at
 */
class EntryVO extends Base\EntryVO {

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