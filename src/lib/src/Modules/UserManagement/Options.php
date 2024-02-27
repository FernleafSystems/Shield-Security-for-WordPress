<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

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

	public function isPassPreventPwned() :bool {
		return $this->isOpt( 'pass_prevent_pwned', 'Y' );
	}

	public function isPasswordPoliciesEnabled() :bool {
		return $this->isOpt( 'enable_password_policies', 'Y' );
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

	/**
	 * @deprecated 19.1
	 */
	public function isSuspendAutoPasswordEnabled() :bool {
		return $this->isPasswordPoliciesEnabled()
			   && $this->isOpt( 'auto_password', 'Y' )
			   && $this->getOpt( 'pass_expire' ) > 0;
	}

	/**
	 * @deprecated 19.1
	 */
	public function isSuspendManualEnabled() :bool {
		return $this->isOpt( 'manual_suspend', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function hasSessionIdleTimeout() :bool {
		return $this->getIdleTimeoutInterval() > 0;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getSuspendAutoIdleUserRoles() :array {
		return $this->getOpt( 'auto_idle_roles', [] );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getSuspendAutoIdleTime() :int {
		return $this->getOpt( 'auto_idle_days', 0 )*\DAY_IN_SECONDS;
	}
}