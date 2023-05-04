<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getSuspendAutoIdleUserRoles() :array {
		return $this->getOpt( 'auto_idle_roles', [] );
	}

	public function isSuspendAutoIdleEnabled() :bool {
		return $this->getSuspendAutoIdleTime() > 0 && count( $this->getSuspendAutoIdleUserRoles() ) > 0;
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
		return ( $this->isPasswordPoliciesEnabled() && $this->con()->isPremiumActive() )
			? (int)$this->getOpt( 'pass_expire' )
			: 0;
	}

	public function getPassExpireTimeout() :int {
		return $this->getPassExpireDays()*DAY_IN_SECONDS; /* seconds */
	}

	public function getPassMinStrength() :int {
		return $this->con()->isPremiumActive() ? (int)$this->getOpt( 'pass_min_strength' ) : 0;
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
		return $this->isOpt( 'enable_password_policies', 'Y' ) && $this->isOptReqsMet( 'enable_password_policies' );
	}

	public function isSuspendEnabled() :bool {
		return $this->isSuspendManualEnabled()
			   || $this->isSuspendAutoIdleEnabled()
			   || $this->isSuspendAutoPasswordEnabled();
	}

	public function isSuspendAutoPasswordEnabled() :bool {
		return $this->isOpt( 'auto_password', 'Y' )
			   && $this->isPasswordPoliciesEnabled() && $this->getPassExpireTimeout() > 0;
	}

	public function isSuspendManualEnabled() :bool {
		return $this->isOpt( 'manual_suspend', 'Y' );
	}

	public function getValidateEmailOnRegistration() :string {
		return $this->con()->isPremiumActive() ?
			(string)$this->getOpt( 'reg_email_validate', 'disabled' ) : 'disabled';
	}

	public function getEmailValidationChecks() :array {
		return $this->getOpt( 'email_checks', [] );
	}

	public function isValidateEmailOnRegistration() :bool {
		return $this->getValidateEmailOnRegistration() !== 'disabled' && !empty( $this->getEmailValidationChecks() );
	}
}