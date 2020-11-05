<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

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

	/**
	 * @return array
	 */
	public function getEmail2FaRoles() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$aRoles = $this->getOpt( 'two_factor_auth_user_roles', [] );
		if ( empty( $aRoles ) || !is_array( $aRoles ) ) {
			$aRoles = $mod->getOptEmailTwoFactorRolesDefaults();
			$this->setOpt( 'two_factor_auth_user_roles', $aRoles );
		}
		if ( $this->isPremium() ) {
			$aRoles = apply_filters( 'odp-shield-2fa_email_user_roles', $aRoles );
		}
		return is_array( $aRoles ) ? $aRoles : $mod->getOptEmailTwoFactorRolesDefaults();
	}

	public function getIfCanSendEmailVerified() :bool {
		return (int)$this->getOpt( 'email_can_send_verified_at' ) > 0;
	}

	/**
	 * @return int - seconds
	 */
	public function getMfaSkip() {
		return DAY_IN_SECONDS*( $this->isPremium() ? (int)$this->getOpt( 'mfa_skip', 0 ) : 0 );
	}

	public function getYubikeyAppId() :string {
		return (string)$this->getOpt( 'yubikey_app_id', '' );
	}

	/**
	 * @return bool
	 */
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

	public function isEnabledCooldown() :bool {
		return $this->getCooldownInterval() > 0;
	}

	public function isEnabledGaspCheck() :bool {
		return $this->isOpt( 'enable_login_gasp_check', 'Y' );
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

	/**
	 * @return bool
	 */
	public function isProtectLostPassword() {
		return $this->isProtect( 'password' );
	}

	/**
	 * @return bool
	 */
	public function isProtectRegister() {
		return $this->isProtect( 'register' );
	}

	/**
	 * @param string $sLocation - see config for keys, e.g. login, register, password, checkout_woo
	 * @return bool
	 */
	public function isProtect( $sLocation ) {
		$aLocs = $this->getOpt( 'bot_protection_locations' );
		return in_array( $sLocation, is_array( $aLocs ) ? $aLocs : $this->getOptDefault( 'bot_protection_locations' ) );
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