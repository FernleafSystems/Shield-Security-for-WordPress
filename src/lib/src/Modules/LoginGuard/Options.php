<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @deprecated 19.1
	 */
	public function getAntiBotFormSelectors() :array {
		$ids = $this->getOpt( 'antibot_form_ids', [] );
		return \array_merge( [
			'#loginform',
		], self::con()->isPremiumActive() ? $ids : [] );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getEmail2FaRoles() :array {
		$roles = apply_filters( 'shield/2fa_email_enforced_user_roles', $this->getOpt( 'two_factor_auth_user_roles' ) );
		return \array_unique( \array_filter( \array_map( 'sanitize_key',
			\is_array( $roles ) ? $roles : self::con()->opts->optDefault( 'two_factor_auth_user_roles' )
		) ) );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getIfCanSendEmailVerified() :bool {
		return (int)$this->getOpt( 'email_can_send_verified_at' ) > 0;
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEmailAuthenticationActive() :bool {
		return $this->getIfCanSendEmailVerified() && $this->isEnabledEmailAuth();
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledEmailAuth() :bool {
		return $this->isOpt( 'enable_email_authentication', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledGaspCheck() :bool {
		return $this->isOpt( 'enable_login_gasp_check', 'Y' ) && !$this->isEnabledAntiBot();
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledAntiBot() :bool {
		return $this->isOpt( 'enable_antibot_check', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledEmailAuthAnyUserSet() :bool {
		return $this->isEmailAuthenticationActive()
			   && $this->isOpt( 'email_any_user_set', 'Y' ) && self::con()->isPremiumActive();
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledGoogleAuthenticator() :bool {
		return $this->isOpt( 'enable_google_authenticator', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isProtectLogin() :bool {
		return $this->isProtect( 'login' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isProtectLostPassword() :bool {
		return $this->isProtect( 'password' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isProtectRegister() :bool {
		return $this->isProtect( 'register' );
	}

	/**
	 * @param string $location - see config for keys, e.g. login, register, password, checkout_woo
	 * @deprecated 19.1
	 */
	public function isProtect( string $location ) :bool {
		$locs = $this->getOpt( 'bot_protection_locations' );
		return \in_array( $location, \is_array( $locs ) ? $locs : $this->getOptDefault( 'bot_protection_locations' ) );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledYubikey() :bool {
		return $this->isOpt( 'enable_yubikey', 'Y' )
			   && !empty( $this->getOpt( 'yubikey_app_id' ) ) && !empty( $this->getOpt( 'yubikey_api_key' ) );
	}

	/**
	 * @deprecated 19.1
	 */
	private function isYubikeyConfigReady() :bool {
		return !empty( $this->getOpt( 'yubikey_app_id' ) ) && !empty( $this->getOpt( 'yubikey_api_key' ) );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getCooldownInterval() :int {
		return $this->getOpt( 'login_limit_interval' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getCustomLoginPath() :string {
		return $this->getOpt( 'rename_wplogin_path', '' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getMfaSkip() :int { // seconds
		return \DAY_IN_SECONDS*( $this->getOpt( 'mfa_skip', 0 ) );
	}
}