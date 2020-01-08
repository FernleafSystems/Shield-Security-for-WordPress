<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return int
	 */
	public function getIdleTimeoutInterval() {
		return $this->getOpt( 'session_idle_timeout_interval' )*HOUR_IN_SECONDS;
	}

	/**
	 * @return int
	 */
	public function getMaxSessionTime() {
		return $this->getOpt( 'session_timeout_interval' )*DAY_IN_SECONDS;
	}

	/**
	 * @return bool
	 */
	public function hasMaxSessionTimeout() {
		return $this->getMaxSessionTime() > 0;
	}

	/**
	 * @return bool
	 */
	public function hasSessionIdleTimeout() {
		return $this->getIdleTimeoutInterval() > 0;
	}

	/**
	 * @return bool
	 */
	public function isLockToIp() {
		return $this->isOpt( 'session_lock_location', 'Y' );
	}
}