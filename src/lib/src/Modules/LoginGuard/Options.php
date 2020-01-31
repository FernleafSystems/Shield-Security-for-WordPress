<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

/**
 * Class Options
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard
 */
class Options extends Base\ShieldOptions {

	/**
	 * @return int
	 */
	public function getCooldownInterval() {
		return (int)$this->getOpt( 'login_limit_interval' );
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
	 * @return bool
	 */
	public function isCooldownEnabled() {
		return $this->getCooldownInterval() > 0;
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
	public function isEmailAuthAnyUserEnable() {
		return $this->isEmailAuthenticationActive() && $this->isOpt( 'email_any_user_enable', 'Y' ) && $this->isPremium();
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