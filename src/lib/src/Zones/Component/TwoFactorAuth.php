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

	public function enabledStatus() :string {
		$providers = \array_filter( self::con()->comps->mfa->collateMfaProviderClasses(), function ( $c ) {
			return $c::ProviderEnabled();
		} );
		if ( empty( $providers ) ) {
			$status = EnumEnabledStatus::BAD;
		}
		else {
			$status = \count( $providers ) > 1 ? EnumEnabledStatus::GOOD : EnumEnabledStatus::OKAY;
		}
		return $status;
	}
}