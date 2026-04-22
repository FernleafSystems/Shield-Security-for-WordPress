<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\{
	Email,
	GoogleAuth,
	Passkey,
	Yubikey
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeyCompatibilityCheck;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class TwoFactorAuth extends Base {

	public function title() :string {
		return __( '2-Factor Authentication', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( "It's best practice to protect user access with at least one 2FA method.", 'wp-simple-firewall' );
	}

	public function configureRows() :array {
		$generalStatus = $this->configureStatus();
		$emailStatus = $this->emailConfigureStatus();
		$otpPasskeyStatus = $this->otpPasskeysConfigureStatus();
		return [
			$this->buildConfigureRowInput(
				'two_factor_general',
				__( '2FA General Settings', 'wp-simple-firewall' ),
				$generalStatus[ 'level' ],
				__( 'Configure the core login-verification flow and backup access behaviour.', 'wp-simple-firewall' ),
				$generalStatus[ 'exp' ],
				$this->buildConfigureRowScope(
					$this->configZoneComponentSlugs(),
					$this->configureRowOptionsForSections( [ 'section_twofactor_auth' ] ),
					'',
					__( 'Edit 2FA general settings', 'wp-simple-firewall' )
				)
			),
			$this->buildConfigureRowInput(
				'two_factor_email',
				__( 'Email Authentication', 'wp-simple-firewall' ),
				$emailStatus[ 'level' ],
				__( 'Configure email-based verification and role enforcement.', 'wp-simple-firewall' ),
				$emailStatus[ 'exp' ],
				$this->buildConfigureRowScope(
					$this->configZoneComponentSlugs(),
					$this->configureRowOptionsForSections( [ 'section_2fa_email' ] ),
					'',
					__( 'Edit email authentication settings', 'wp-simple-firewall' )
				)
			),
			$this->buildConfigureRowInput(
				'two_factor_otp_passkeys',
				__( 'OTP & Passkeys', 'wp-simple-firewall' ),
				$otpPasskeyStatus[ 'level' ],
				__( 'Configure authenticator apps, Yubikey OTP, and passkey support.', 'wp-simple-firewall' ),
				$otpPasskeyStatus[ 'exp' ],
				$this->buildConfigureRowScope(
					$this->configZoneComponentSlugs(),
					$this->configureRowOptionsForSections( [ 'section_2fa_otp', 'section_2fa_passkeys' ] ),
					'',
					__( 'Edit OTP and passkey settings', 'wp-simple-firewall' )
				)
			),
		];
	}

	protected function tooltip() :string {
		return __( 'Edit settings for the most common 2FA factors', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();
		$providers = $this->usablePrimaryProviderNames();
		$count = \count( $providers );

		if ( $count === 0 ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( 'There are no usable primary 2FA providers.', 'wp-simple-firewall' );
		}
		elseif ( $count === 1 ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = sprintf(
				__( 'Only 1 usable primary 2FA provider is available: %s.', 'wp-simple-firewall' ),
				$providers[ 0 ]
			);
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}

		return $status;
	}

	protected function postureWeight() :int {
		return 5;
	}

	/**
	 * @return array{level:string,exp:list<string>}
	 */
	private function emailConfigureStatus() :array {
		$status = parent::status();
		$con = self::con();

		if ( !$con->opts->optIs( 'enable_email_authentication', 'Y' ) ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = __( 'Email-based verification is disabled.', 'wp-simple-firewall' );
		}
		elseif ( $con->opts->optGet( 'email_can_send_verified_at' ) < 1 ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = __( 'Email-based verification cannot be relied on until email delivery has been verified.', 'wp-simple-firewall' );
		}
		elseif ( !$this->hasEmailAudience() ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = __( 'Email-based verification is enabled, but no user audience is allowed or enforced to use it.', 'wp-simple-firewall' );
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}

		return $status;
	}

	/**
	 * @return array{level:string,exp:list<string>}
	 */
	private function otpPasskeysConfigureStatus() :array {
		$status = parent::status();
		$usableProviders = [];
		$reasons = [];

		if ( GoogleAuth::ProviderEnabled() ) {
			$usableProviders[] = GoogleAuth::ProviderName();
		}
		else {
			$reasons[] = __( 'Google Authenticator is disabled.', 'wp-simple-firewall' );
		}

		if ( Yubikey::ProviderEnabled() ) {
			$usableProviders[] = Yubikey::ProviderName();
		}
		elseif ( self::con()->opts->optIs( 'enable_yubikey', 'Y' ) ) {
			$reasons[] = __( 'Yubikey OTP is enabled but the Yubico application credentials are incomplete.', 'wp-simple-firewall' );
		}
		else {
			$reasons[] = __( 'Yubikey OTP is disabled.', 'wp-simple-firewall' );
		}

		if ( Passkey::ProviderEnabled() ) {
			$usableProviders[] = Passkey::ProviderName();
		}
		elseif ( self::con()->opts->optIs( 'enable_passkeys', 'Y' ) ) {
			$reasons[] = ( new PasskeyCompatibilityCheck() )->run()
				? __( 'Passkeys are enabled but not currently usable.', 'wp-simple-firewall' )
				: __( 'Passkeys are enabled but the current server does not support them.', 'wp-simple-firewall' );
		}
		else {
			$reasons[] = __( 'Passkeys are disabled.', 'wp-simple-firewall' );
		}

		if ( empty( $usableProviders ) ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ] = $reasons;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}

		return $status;
	}

	private function hasEmailAudience() :bool {
		return self::con()->opts->optIs( 'email_any_user_set', 'Y' )
			   || \count( self::con()->comps->opts_lookup->getLoginGuardEmailAuth2FaRoles() ) > 0;
	}

	private function isEmailProviderUsable() :bool {
		return Email::ProviderEnabled() && $this->hasEmailAudience();
	}

	/**
	 * @return list<string>
	 */
	private function usablePrimaryProviderNames() :array {
		$providers = [];

		if ( $this->isEmailProviderUsable() ) {
			$providers[] = Email::ProviderName();
		}
		if ( GoogleAuth::ProviderEnabled() ) {
			$providers[] = GoogleAuth::ProviderName();
		}
		if ( Yubikey::ProviderEnabled() ) {
			$providers[] = Yubikey::ProviderName();
		}
		if ( Passkey::ProviderEnabled() ) {
			$providers[] = Passkey::ProviderName();
		}

		return $providers;
	}
}
