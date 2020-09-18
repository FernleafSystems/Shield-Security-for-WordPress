<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Options
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard
 */
class Options extends Base\ShieldOptions {

	/**
	 * @return int
	 */
	public function getLoginIntentTimeoutMinutes() {
		return (int)max( 1, apply_filters(
			$this->getCon()->prefix( 'login_intent_timeout' ),
			$this->getDef( 'login_intent_timeout' )
		) );
	}

	/**
	 * @return array
	 */
	public function getAntiBotFormSelectors() {
		$aIds = $this->getOpt( 'antibot_form_ids', [] );
		return ( $this->isPremium() && is_array( $aIds ) ) ? $aIds : [];
	}

	/**
	 * @return int
	 */
	public function getCooldownInterval() {
		return (int)$this->getOpt( 'login_limit_interval' );
	}

	/**
	 * @return string
	 */
	public function getCustomLoginPath() {
		return $this->getOpt( 'rename_wplogin_path', '' );
	}

	/**
	 * @return array
	 */
	public function getEmail2FaRoles() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		$aRoles = $this->getOpt( 'two_factor_auth_user_roles', [] );
		if ( empty( $aRoles ) || !is_array( $aRoles ) ) {
			$aRoles = $oMod->getOptEmailTwoFactorRolesDefaults();
			$this->setOpt( 'two_factor_auth_user_roles', $aRoles );
		}
		if ( $this->isPremium() ) {
			$aRoles = apply_filters( 'odp-shield-2fa_email_user_roles', $aRoles );
		}
		return is_array( $aRoles ) ? $aRoles : $oMod->getOptEmailTwoFactorRolesDefaults();
	}

	/**
	 * @return bool
	 */
	public function getIfCanSendEmailVerified() {
		return (int)$this->getOpt( 'email_can_send_verified_at' ) > 0;
	}

	/**
	 * @return int - seconds
	 */
	public function getMfaSkip() {
		return DAY_IN_SECONDS*( $this->isPremium() ? (int)$this->getOpt( 'mfa_skip', 0 ) : 0 );
	}

	/**
	 * @return string
	 */
	public function getYubikeyAppId() {
		return (string)$this->getOpt( 'yubikey_app_id', '' );
	}

	/**
	 * @return bool
	 */
	public function isMfaSkip() {
		return $this->getMfaSkip() > 0;
	}

	/**
	 * @return bool
	 */
	public function isChainedAuth() {
		return $this->isOpt( 'enable_chained_authentication', 'Y' );
	}

	/**
	 * Also considers whether email sending ability has been verified
	 * @return bool
	 */
	public function isEmailAuthenticationActive() {
		return $this->getIfCanSendEmailVerified() && $this->isEnabledEmailAuth();
	}

	/**
	 * @return bool
	 */
	public function isEnabledEmailAuth() {
		return $this->isOpt( 'enable_email_authentication', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledCooldown() {
		return $this->getCooldownInterval() > 0;
	}

	/**
	 * @return bool
	 */
	public function isEnabledGaspCheck() {
		return $this->isOpt( 'enable_login_gasp_check', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledEmailAuthAnyUserSet() {
		return $this->isEmailAuthenticationActive() && $this->isOpt( 'email_any_user_set', 'Y' ) && $this->isPremium();
	}

	/**
	 * @return bool
	 */
	public function isEnabledBackupCodes() {
		return $this->isPremium() && $this->isOpt( 'allow_backupcodes', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledGoogleAuthenticator() {
		return $this->isOpt( 'enable_google_authenticator', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledU2F() {
		return Services::Data()->getPhpVersionIsAtLeast( '7.0' )
			   && $this->isPremium() && $this->isOpt( 'enable_u2f', 'Y' );
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

	/**
	 * @return bool
	 */
	public function isUseLoginIntentPage() {
		return $this->isOpt( 'use_login_intent_page', true );
	}

	/**
	 * @return bool
	 */
	public function isEnabledYubikey() {
		return $this->isOpt( 'enable_yubikey', 'Y' ) && $this->isYubikeyConfigReady();
	}

	/**
	 * @return bool
	 */
	private function isYubikeyConfigReady() {
		$sAppId = $this->getOpt( 'yubikey_app_id' );
		$sApiKey = $this->getOpt( 'yubikey_api_key' );
		return !empty( $sAppId ) && !empty( $sApiKey );
	}
}