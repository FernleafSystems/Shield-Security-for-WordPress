<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\{
	AbstractShieldProvider,
	Email,
	GoogleAuth,
	Passkey,
	Yubikey
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\EmailDeliveryVerification;
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
				/* translators: %s: name of the 2FA provider */
				__( 'Only 1 usable primary 2FA provider is available: %s.', 'wp-simple-firewall' ),
				$providers[ 0 ]
			);
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}

		return $status;
	}

	/**
	 * @return array{level:string,exp:list<string>}
	 */
	private function emailConfigureStatus() :array {
		$status = parent::status();
		$emailVerificationStatus = ( new EmailDeliveryVerification() )->status();

		if ( $emailVerificationStatus === EmailDeliveryVerification::STATUS_DISABLED ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = $this->explanationFixedBy( __( 'Email-based verification is disabled.', 'wp-simple-firewall' ), 'enable_email_authentication' );
		}
		elseif ( $this->isTakenOut( Email::class, 'enable_email_authentication' ) ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = $this->takenOutExplanation( Email::class );
		}
		elseif ( $emailVerificationStatus !== EmailDeliveryVerification::STATUS_VERIFIED ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = __( 'Email-based verification cannot be relied on until email delivery has been verified.', 'wp-simple-firewall' );
		}
		elseif ( !$this->hasEmailAudience() ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = $this->explanationFixedBy( __( 'Email-based verification is enabled, but no user audience is allowed or enforced to use it.', 'wp-simple-firewall' ), 'two_factor_auth_user_roles' );
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
		$takenOut = [];

		if ( $this->isUsable( GoogleAuth::class ) ) {
			$usableProviders[] = GoogleAuth::ProviderName();
		}
		elseif ( $this->isTakenOut( GoogleAuth::class, 'enable_google_authenticator' ) ) {
			$takenOut[] = $this->takenOutExplanation( GoogleAuth::class );
		}
		else {
			$reasons[] = $this->explanationFixedBy( __( 'TOTP authenticator apps are disabled.', 'wp-simple-firewall' ), 'enable_google_authenticator' );
		}

		if ( $this->isUsable( Yubikey::class ) ) {
			$usableProviders[] = Yubikey::ProviderName();
		}
		elseif ( $this->isTakenOut( Yubikey::class, 'enable_yubikey' ) ) {
			$takenOut[] = $this->takenOutExplanation( Yubikey::class );
		}
		elseif ( self::con()->opts->optIs( 'enable_yubikey', 'Y' ) ) {
			$reasons[] = $this->explanationFixedBy( __( 'Yubikey OTP is enabled but the Yubico application credentials are incomplete.', 'wp-simple-firewall' ), 'yubikey_app_id' );
		}
		else {
			$reasons[] = $this->explanationFixedBy( __( 'Yubikey OTP is disabled.', 'wp-simple-firewall' ), 'enable_yubikey' );
		}

		if ( $this->isUsable( Passkey::class ) ) {
			$usableProviders[] = Passkey::ProviderName();
		}
		elseif ( $this->isTakenOut( Passkey::class, 'enable_passkeys' ) ) {
			$takenOut[] = $this->takenOutExplanation( Passkey::class );
		}
		elseif ( self::con()->opts->optIs( 'enable_passkeys', 'Y' ) ) {
			$reasons[] = ( new PasskeyCompatibilityCheck() )->run()
				? __( 'Passkeys are enabled but not currently usable.', 'wp-simple-firewall' )
				: __( 'Passkeys are enabled but the current server does not support them.', 'wp-simple-firewall' );
		}
		else {
			$reasons[] = $this->explanationFixedBy( __( 'Passkeys are disabled.', 'wp-simple-firewall' ), 'enable_passkeys' );
		}

		// A method that's switched off is a choice, so it doesn't matter while another works. One that something else
		// has taken out isn't, so it's always said.
		if ( empty( $usableProviders ) ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ] = \array_merge( $takenOut, $reasons );
		}
		elseif ( !empty( $takenOut ) ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ] = $takenOut;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}

		return $status;
	}

	private function hasEmailAudience() :bool {
		return Email::AnyUserCanTurnOn()
			   || \count( self::con()->comps->opts_lookup->getLoginGuardEmailAuth2FaRoles() ) > 0;
	}

	private function isEmailProviderUsable() :bool {
		return $this->isUsable( Email::class ) && $this->hasEmailAudience();
	}

	/**
	 * Enabled here, and offered at login.
	 * @param class-string<AbstractShieldProvider> $provider
	 */
	private function isUsable( string $provider ) :bool {
		return $provider::ProviderEnabled() && self::con()->comps->mfa->isProviderOffered( $provider );
	}

	/**
	 * Switched on here, but another plugin or custom code has left it out of the methods login offers. The admin
	 * two-factor drawer says the same of the same methods.
	 * @param class-string<AbstractShieldProvider> $provider
	 */
	private function isTakenOut( string $provider, string $switchOptKey ) :bool {
		return self::con()->opts->optIs( $switchOptKey, 'Y' ) && !self::con()->comps->mfa->isProviderOffered( $provider );
	}

	/**
	 * Configure can't put back a method that something else has taken out, so there's nothing here to link to.
	 * @param class-string<AbstractShieldProvider> $provider
	 */
	private function takenOutExplanation( string $provider ) :string {
		return sprintf(
			/* translators: %s: name of a two-factor method, such as "Email" */
			__( 'Another plugin or custom code has removed %s from the methods offered at login.', 'wp-simple-firewall' ),
			$provider::ProviderName()
		);
	}

	/**
	 * @return list<string>
	 */
	private function usablePrimaryProviderNames() :array {
		$providers = [];

		if ( $this->isEmailProviderUsable() ) {
			$providers[] = Email::ProviderName();
		}
		foreach ( [ GoogleAuth::class, Yubikey::class, Passkey::class ] as $provider ) {
			if ( $this->isUsable( $provider ) ) {
				$providers[] = $provider::ProviderName();
			}
		}

		return $providers;
	}
}
