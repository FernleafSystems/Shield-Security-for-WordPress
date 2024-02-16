<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\MfaController;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	public function preSave() :void {

		if ( $this->isEnabledAntiBot() ) {
			$this->setOpt( 'enable_login_gasp_check', 'N' );
		}

		$this->setOpt( 'two_factor_auth_user_roles', $this->getEmail2FaRoles() );

		$redirect = \preg_replace( '#[^\da-z_\-/.]#i', '', (string)$this->getOpt( 'rename_wplogin_redirect' ) );
		if ( !empty( $redirect ) ) {
			$redirect = \preg_replace( '#^http(s)?//.*/#iU', '', $redirect );
			if ( !empty( $redirect ) ) {
				$redirect = '/'.\ltrim( $redirect, '/' );
			}
		}
		$this->setOpt( 'rename_wplogin_redirect', $redirect );

		if ( empty( $this->getOpt( 'mfa_user_setup_pages' ) ) ) {
			$this->setOpt( 'mfa_user_setup_pages', [ 'profile' ] );
		}
		if ( $this->isOptChanged( 'antibot_form_ids' ) ) {
			$this->setOpt( 'antibot_form_ids',
				\array_values( \array_unique( \array_filter(
					$this->getOpt( 'antibot_form_ids', [] ),
					function ( $id ) {
						return \trim( strip_tags( (string)$id ) );
					}
				) ) )
			);
		}
		if ( $this->isOptChanged( 'rename_wplogin_path' ) ) {
			$path = $this->getCustomLoginPath();
			if ( !empty( $path ) ) {
				$path = \preg_replace( '#[^\da-zA-Z-]#', '', \trim( $path, '/' ) );
				$this->setOpt( 'rename_wplogin_path', $path );
			}
		}
	}

	public function canAutoLoginURL() :bool {
		return $this->isOpt( 'enable_email_auto_login', 'Y' );
	}

	public function getBotProtectionLocations() :array {
		return $this->getOpt( 'bot_protection_locations' );
	}

	public function getHiddenLoginRedirect() :string {
		return $this->getOpt( 'rename_wplogin_redirect' );
	}

	public function getLoginIntentMaxAttempts() :int {
		return (int)\max( 1, apply_filters( 'shield/2fa_max_attempts', $this->getDef( 'login_intent_max_attempts' ) ) );
	}

	public function getLoginIntentMinutes() :int {
		return (int)\max( 1, apply_filters(
			self::con()->prefix( 'login_intent_timeout' ),
			$this->getDef( 'login_intent_timeout' )
		) );
	}

	public function getAntiBotFormSelectors() :array {
		$ids = $this->getOpt( 'antibot_form_ids', [] );
		return \array_merge( [
			'#loginform',
		], self::con()->isPremiumActive() ? $ids : [] );
	}

	public function getCooldownInterval() :int {
		return $this->getOpt( 'login_limit_interval' );
	}

	public function getCustomLoginPath() :string {
		return $this->getOpt( 'rename_wplogin_path', '' );
	}

	public function getEmail2FaRoles() :array {
		$roles = apply_filters(
			'shield/2fa_email_enforced_user_roles',
			apply_filters( 'odp-shield-2fa_email_user_roles', $this->getOpt( 'two_factor_auth_user_roles' ) )
		);
		return \array_unique( \array_filter( \array_map( 'sanitize_key',
			\is_array( $roles ) ? $roles : $this->getOptDefault( 'two_factor_auth_user_roles' )
		) ) );
	}

	public function getIfCanSendEmailVerified() :bool {
		return (int)$this->getOpt( 'email_can_send_verified_at' ) > 0;
	}

	public function getMfaLoginIntentFormat() :string {
		return $this->getOpt( 'mfa_verify_page', MfaController::LOGIN_INTENT_PAGE_FORMAT_SHIELD );
	}

	public function getMfaSkip() :int { // seconds
		return \DAY_IN_SECONDS*( $this->getOpt( 'mfa_skip', 0 ) );
	}

	public function getYubikeyAppId() :string {
		return $this->getOpt( 'yubikey_app_id', '' );
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
		return $this->isOpt( 'enable_login_gasp_check', 'Y' ) && !$this->isEnabledAntiBot();
	}

	public function isEnabledAntiBot() :bool {
		return $this->isOpt( 'enable_antibot_check', 'Y' );
	}

	public function isEnabledEmailAuthAnyUserSet() :bool {
		return $this->isEmailAuthenticationActive()
			   && $this->isOpt( 'email_any_user_set', 'Y' ) && self::con()->isPremiumActive();
	}

	public function isEnabledGoogleAuthenticator() :bool {
		return $this->isOpt( 'enable_google_authenticator', 'Y' );
	}

	public function isProtectLogin() :bool {
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
	 */
	public function isProtect( string $location ) :bool {
		$locs = $this->getOpt( 'bot_protection_locations' );
		return \in_array( $location, \is_array( $locs ) ? $locs : $this->getOptDefault( 'bot_protection_locations' ) );
	}

	public function isEnabledYubikey() :bool {
		return $this->isOpt( 'enable_yubikey', 'Y' ) && $this->isYubikeyConfigReady();
	}

	private function isYubikeyConfigReady() :bool {
		return !empty( $this->getOpt( 'yubikey_app_id' ) ) && !empty( $this->getOpt( 'yubikey_api_key' ) );
	}
}