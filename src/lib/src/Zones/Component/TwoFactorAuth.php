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
}