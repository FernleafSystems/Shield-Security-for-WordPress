<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getBotProtectionLocations() :array {
		return is_array( $this->getOpt( 'bot_protection_locations' ) ) ? $this->getOpt( 'bot_protection_locations' ) : [];
	}

	public function getLoginIntentMinutes() :int {
		return (int)max( 1, apply_filters(
			$this->getCon()->prefix( 'login_intent_timeout' ),
			$this->getDef( 'login_intent_timeout' )
		) );
	}

	public function getAntiBotFormSelectors() :array {
		$ids = $this->getOpt( 'antibot_form_ids', [] );
		return ( $this->isPremium() && is_array( $ids ) ) ? $ids : [];
	}

	public function getCooldownInterval() :int {
		return (int)$this->getOpt( 'login_limit_interval' );
	}

	public function getCustomLoginPath() :string {
		return (string)$this->getOpt( 'rename_wplogin_path', '' );
	}

	public function getEmail2FaRoles() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$roles = $this->getOpt( 'two_factor_auth_user_roles', [] );
		if ( empty( $roles ) || !is_array( $roles ) ) {
			$roles = $mod->getOptEmailTwoFactorRolesDefaults();
			$this->setOpt( 'two_factor_auth_user_roles', $roles );
		}
		if ( $this->isPremium() ) {
			$roles = apply_filters( 'odp-shield-2fa_email_user_roles', $roles );
		}
		return is_array( $roles ) ? $roles : $mod->getOptEmailTwoFactorRolesDefaults();
	}

	public function getIfCanSendEmailVerified() :bool {
		return (int)$this->getOpt( 'email_can_send_verified_at' ) > 0;
	}

	public function getMfaSkip() :int { // seconds
		return DAY_IN_SECONDS*( $this->isPremium() ? (int)$this->getOpt( 'mfa_skip', 0 ) : 0 );
	}

	public function getYubikeyAppId() :string {
		return (string)$this->getOpt( 'yubikey_app_id', '' );
	}

	public function isMfaSkip() :bool {
		return $this->getMfaSkip() > 0;
	}

	public function isChainedAuth() :bool {
		return $this->isOpt( 'enable_chained_authentication', 'Y' );
	}

	public function isEmailAuthenticationActive() :bool {
		return $this->getIfCanSendEmailVerified() && $this->isEnabledEmailAuth();
	}

	public function isEnabledEmailAuth() :bool {
		return $this->isOpt( 'enable_email_authentication', 'Y' );
	}

	public function isEnabledSmsAuth() :bool {
		return $this->isOpt( 'enable_sms_auth', 'Y' );
	}

	public function isEnabledCooldown() :bool {
		return $this->getCooldownInterval() > 0;
	}

	public function isEnabledGaspCheck() :bool {
		return $this->isOpt( 'enable_login_gasp_check', 'Y' )
			   && !$this->isEnabledAntiBot();
	}

	public function isEnabledAntiBot() :bool {
		return $this->isOpt( 'enable_antibot_check', 'Y' );
	}

	public function isEnabledEmailAuthAnyUserSet() :bool {
		return $this->isEmailAuthenticationActive()
			   && $this->isOpt( 'email_any_user_set', 'Y' ) && $this->isPremium();
	}

	public function isEnabledBackupCodes() :bool {
		return $this->isPremium() && $this->isOpt( 'allow_backupcodes', 'Y' );
	}

	public function isEnabledGoogleAuthenticator() :bool {
		return $this->isOpt( 'enable_google_authenticator', 'Y' );
	}

	public function isEnabledU2F() :bool {
		return $this->isPremium() && $this->isOpt( 'enable_u2f', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isProtectLogin() {
		return $this->isProtect( 'login' );
	}

	public function isProtectLostPassword() :bool {
		return $this->isProtect( 'password' );
	}

	public function isProtectRegister() :bool {
		return $this->isProtect( 'register' );
	}

	/**
	 * @param string $location - see config for keys, e.g. login, register, password, checkout_woo
	 * @return bool
	 */
	public function isProtect( $location ) :bool {
		$locs = $this->getOpt( 'bot_protection_locations' );
		return in_array( $location, is_array( $locs ) ? $locs : $this->getOptDefault( 'bot_protection_locations' ) );
	}

	public function isUseLoginIntentPage() :bool {
		return $this->isOpt( 'use_login_intent_page', true );
	}

	public function isEnabledYubikey() :bool {
		return $this->isOpt( 'enable_yubikey', 'Y' ) && $this->isYubikeyConfigReady();
	}

	private function isYubikeyConfigReady() :bool {
		return !empty( $this->getOpt( 'yubikey_app_id' ) ) && !empty( $this->getOpt( 'yubikey_api_key' ) );
	}
}