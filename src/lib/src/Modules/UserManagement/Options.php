<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	/**
	 * @deprecated 13.1
	 */
	public function getSuspendHardUserIds() :array {
		return array_filter(
			$this->getOpt( 'hard_suspended_userids', [] ),
			function ( $ts ) {
				return is_int( $ts ) && $ts > 0;
			}
		);
	}

	public function getSuspendAutoIdleUserRoles() :array {
		return $this->getOpt( 'auto_idle_roles', [] );
	}

	public function isSuspendAutoIdleEnabled() :bool {
		return ( $this->getSuspendAutoIdleTime() > 0 )
			   && ( count( $this->getSuspendAutoIdleUserRoles() ) > 0 );
	}

	public function getSuspendAutoIdleTime() :int {
		return $this->getOpt( 'auto_idle_days', 0 )*DAY_IN_SECONDS;
	}

	public function getIdleTimeoutInterval() :int {
		return $this->getOpt( 'session_idle_timeout_interval' )*HOUR_IN_SECONDS;
	}

	public function getMaxSessionTime() :int {
		return $this->getOpt( 'session_timeout_interval' )*DAY_IN_SECONDS;
	}

	public function getPassExpireDays() :int {
		return ( $this->isPasswordPoliciesEnabled() && $this->isPremium() ) ? (int)$this->getOpt( 'pass_expire' ) : 0;
	}

	/**
	 * @return int seconds
	 */
	public function getPassExpireTimeout() {
		return $this->getPassExpireDays()*DAY_IN_SECONDS;
	}

	public function getPassMinLength() :int {
		return $this->isPremium() ? (int)$this->getOpt( 'pass_min_length' ) : 0;
	}

	public function getPassMinStrength() :int {
		return $this->isPremium() ? (int)$this->getOpt( 'pass_min_strength' ) : 0;
	}

	public function hasMaxSessionTimeout() :bool {
		return $this->getMaxSessionTime() > 0;
	}

	public function hasSessionIdleTimeout() :bool {
		return $this->getIdleTimeoutInterval() > 0;
	}

	public function isLockToIp() :bool {
		return $this->isOpt( 'session_lock_location', 'Y' );
	}

	public function isPassExpirationEnabled() :bool {
		return $this->isPasswordPoliciesEnabled() && ( $this->getPassExpireTimeout() > 0 );
	}

	public function isPassPreventPwned() :bool {
		return $this->isOpt( 'pass_prevent_pwned', 'Y' );
	}

	public function isPasswordPoliciesEnabled() :bool {
		return $this->isOpt( 'enable_password_policies', 'Y' )
			   && $this->isOptReqsMet( 'enable_password_policies' );
	}

	public function isSuspendEnabled() :bool {
		return $this->isSuspendManualEnabled()
			   || $this->isSuspendAutoIdleEnabled()
			   || $this->isSuspendAutoPasswordEnabled();
	}

	public function isSuspendAutoPasswordEnabled() :bool {
		return $this->isOpt( 'auto_password', 'Y' )
			   && $this->isPasswordPoliciesEnabled() && $this->getPassExpireTimeout();
	}

	public function isSuspendManualEnabled() :bool {
		return $this->isOpt( 'manual_suspend', 'Y' );
	}

	/**
	 * @return string
	 */
	public function getValidateEmailOnRegistration() {
		return $this->isPremium() ?
			$this->getOpt( 'reg_email_validate', 'disabled' )
			: 'disabled';
	}

	/**
	 * @return string[]
	 */
	public function getEmailValidationChecks() {
		return $this->getOpt( 'email_checks', [] );
	}
}