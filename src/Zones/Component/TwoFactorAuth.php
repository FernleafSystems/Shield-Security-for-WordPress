<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class TwoFactorAuth extends Base {

	public function title() :string {
		return __( '2-Factor Authentication', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( "It's best practice to protect user access with at least one 2FA method.", 'wp-simple-firewall' );
	}

	public function configureRows() :array {
		return [
			$this->buildConfigureRowInput(
				'two_factor_general',
				__( '2FA General Settings', 'wp-simple-firewall' ),
				$this->enabledStatus(),
				__( 'Configure the core login-verification flow and backup access behaviour.', 'wp-simple-firewall' ),
				$this->explanation(),
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
				EnumEnabledStatus::NEUTRAL,
				__( 'Configure email-based verification and role enforcement.', 'wp-simple-firewall' ),
				[],
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
				EnumEnabledStatus::NEUTRAL,
				__( 'Configure authenticator apps, Yubikey OTP, and passkey support.', 'wp-simple-firewall' ),
				[],
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

		$providers = \array_filter( self::con()->comps->mfa->collateMfaProviderClasses(), function ( $c ) {
			return $c::ProviderEnabled();
		} );
		if ( empty( $providers ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "There are no active 2FA providers.", 'wp-simple-firewall' );
		}
		elseif ( \count( $providers ) === 1 ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = __( "Consider activating at another 2FA provider, as there is only 1 available for users to choose from.", 'wp-simple-firewall' );
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}

		return $status;
	}

	protected function postureWeight() :int {
		return 5;
	}
}
