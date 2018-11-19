<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;

/**
 * Class EntryVO
 * @property string ip
 * @property string message
 * @property string wp_username
 * @property string rid
 * @property string event
 */
class EntryVO extends Databases\Base\BaseEntryVO {

	/**
	 * @return string
	 */
	public function getIp() {
		return (string)$this->ip;
	}

	/**
	 * @return string
	 */
	public function getEvent() {
		return (string)$this->event;
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
	public function getRid() {
		return (string)$this->rid;
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return (string)$this->wp_username;
	}
}