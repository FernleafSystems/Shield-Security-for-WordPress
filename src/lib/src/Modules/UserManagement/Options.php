<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return array
	 */
	public function getSuspendAutoIdleUserRoles() {
		$aRoles = $this->getOpt( 'auto_idle_roles', [] );
		return is_array( $aRoles ) ? $aRoles : [];
	}

	/**
	 * @return bool
	 */
	public function isSuspendAutoIdleEnabled() {
		return ( $this->getSuspendAutoIdleTime() > 0 )
			   && ( count( $this->getSuspendAutoIdleUserRoles() ) > 0 );
	}

	/**
	 * @return int
	 */
	public function getSuspendAutoIdleTime() {
		return $this->getOpt( 'auto_idle_days', 0 )*DAY_IN_SECONDS;
	}

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
	 * @return int days
	 */
	public function getPassExpireDays() {
		return ( $this->isPasswordPoliciesEnabled() && $this->isPremium() ) ? (int)$this->getOpt( 'pass_expire' ) : 0;
	}

	/**
	 * @return int seconds
	 */
	public function getPassExpireTimeout() {
		return $this->getPassExpireDays()*DAY_IN_SECONDS;
	}

	/**
	 * @return int
	 */
	public function getPassMinLength() {
		return $this->isPremium() ? (int)$this->getOpt( 'pass_min_length' ) : 0;
	}

	/**
	 * @return int
	 */
	public function getPassMinStrength() {
		return $this->isPremium() ? (int)$this->getOpt( 'pass_min_strength' ) : 0;
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

	/**
	 * @return bool
	 */
	public function isPassExpirationEnabled() {
		return $this->isPasswordPoliciesEnabled() && ( $this->getPassExpireTimeout() > 0 );
	}

	/**
	 * @return bool
	 */
	public function isPassPreventPwned() {
		return $this->isOpt( 'pass_prevent_pwned', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isPassForceUpdateExisting() {
		return $this->isOpt( 'pass_force_existing', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isPasswordPoliciesEnabled() {
		return $this->isOpt( 'enable_password_policies', 'Y' )
			   && $this->isOptReqsMet( 'enable_password_policies' );
	}

	/**
	 * @return bool
	 */
	public function isSuspendEnabled() {
		return $this->isPremium() &&
			   ( $this->isSuspendManualEnabled()
				 || $this->isSuspendAutoIdleEnabled()
				 || $this->isSuspendAutoPasswordEnabled()
			   );
	}

	/**
	 * @return bool
	 */
	public function isSuspendAutoPasswordEnabled() {
		return $this->isOpt( 'auto_password', 'Y' )
			   && $this->isPasswordPoliciesEnabled() && $this->getPassExpireTimeout();
	}

	/**
	 * @return bool
	 */
	public function isSuspendManualEnabled() {
		return $this->isOpt( 'manual_suspend', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isValidateEmailOnRegistration() {
		return !$this->isOpt( 'enable_email_validate', 'disabled' );
	}
}