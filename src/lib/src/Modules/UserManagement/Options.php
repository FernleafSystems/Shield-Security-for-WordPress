<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getSuspendAutoIdleUserRoles() :array {
		return $this->getOpt( 'auto_idle_roles', [] );
	}

	public function isSuspendAutoIdleEnabled() :bool {
		return $this->getSuspendAutoIdleTime() > 0 && \count( $this->getSuspendAutoIdleUserRoles() ) > 0;
	}

	public function getSuspendAutoIdleTime() :int {
		return $this->getOpt( 'auto_idle_days', 0 )*\DAY_IN_SECONDS;
	}

	public function getIdleTimeoutInterval() :int {
		return $this->getOpt( 'session_idle_timeout_interval' )*\HOUR_IN_SECONDS;
	}

	public function getMaxSessionTime() :int {
		return $this->getOpt( 'session_timeout_interval' )*\DAY_IN_SECONDS;
	}

	public function getPassExpireTimeout() :int {
		return $this->getOpt( 'pass_expire' )*\DAY_IN_SECONDS;
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

	public function isPassPreventPwned() :bool {
		return $this->isOpt( 'pass_prevent_pwned', 'Y' );
	}

	public function isPasswordPoliciesEnabled() :bool {
		return $this->isOpt( 'enable_password_policies', 'Y' );
	}

	public function isSuspendEnabled() :bool {
		return $this->isSuspendManualEnabled()
			   || $this->isSuspendAutoIdleEnabled()
			   || $this->isSuspendAutoPasswordEnabled();
	}

	public function isSuspendAutoPasswordEnabled() :bool {
		return $this->isPasswordPoliciesEnabled()
			   && $this->isOpt( 'auto_password', 'Y' )
			   && $this->getOpt( 'pass_expire' ) > 0;
	}

	public function isSuspendManualEnabled() :bool {
		return $this->isOpt( 'manual_suspend', 'Y' );
	}

	public function getValidateEmailOnRegistration() :string {
		return self::con()->isPremiumActive() ?
			(string)$this->getOpt( 'reg_email_validate', 'disabled' ) : 'disabled';
	}

	public function getEmailValidationChecks() :array {
		return $this->getOpt( 'email_checks', [] );
	}

	public function isValidateEmailOnRegistration() :bool {
		return $this->getValidateEmailOnRegistration() !== 'disabled' && !empty( $this->getEmailValidationChecks() );
	}
}